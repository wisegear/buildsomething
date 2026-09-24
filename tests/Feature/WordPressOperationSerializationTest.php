<?php

namespace Tests\Feature;

use App\Jobs\CreateCustomerBlog;
use App\Jobs\DeleteCustomerBlog;
use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Jobs\ResetWordPressPassword;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressOperationSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    private function blog(string $name, string $ip = '192.0.2.10', string $status = 'pending'): CustomerBlog
    {
        $server = Server::create(['name' => $name, 'ip_address' => $ip, 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 10, 'active' => true]);

        return CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'server_id' => $server->id, 'subdomain' => $name, 'status' => $status]);
    }

    public function test_create_and_delete_wait_for_the_same_host_even_with_duplicate_server_records(): void
    {
        config(['blogshed.lock_store' => 'database']);
        Bus::fake();
        $first = $this->blog('first');
        $second = $this->blog('second');
        $deleting = $this->blog('third', status: 'deleting');
        $middleware = new SerializeWordPressOperations;
        $firstJob = new CreateCustomerBlog($first->id);
        $middleware->handle($firstJob, function () use ($middleware, $second, $deleting) {
            foreach ([new CreateCustomerBlog($second->id), new DeleteCustomerBlog($deleting->id)] as $job) {
                $job->onConnection('database')->onQueue('wordpress');
                $middleware->handle($job, fn () => $this->fail('Same-host operation overlapped.'));
            }
        });

        foreach ([CreateCustomerBlog::class => $second, DeleteCustomerBlog::class => $deleting] as $class => $blog) {
            Bus::assertDispatched($class, fn ($job) => $job->blogId === $blog->id && $job->delay === 15
                && $job->tries === 1 && $job->connection === 'database' && $job->queue === 'wordpress');
        }
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame('deleting', $deleting->fresh()->status);
        $ran = false;
        $middleware->handle(new CreateCustomerBlog($second->id), function () use (&$ran) {
            $ran = true;
        });
        $this->assertTrue($ran, 'Successful operations release the server lease.');
    }

    public function test_different_hosts_can_run_at_the_same_time(): void
    {
        $first = $this->blog('first');
        $second = $this->blog('second', '192.0.2.11');
        $middleware = new SerializeWordPressOperations;
        $ran = false;
        $middleware->handle(new CreateCustomerBlog($first->id), function () use ($middleware, $second, &$ran) {
            $middleware->handle(new CreateCustomerBlog($second->id), function () use (&$ran) {
                $ran = true;
            });
        });
        $this->assertTrue($ran);
    }

    public function test_equivalent_ip_spellings_share_a_lock(): void
    {
        $this->assertSame(SerializeWordPressOperations::key('2001:db8::1'), SerializeWordPressOperations::key('2001:0db8:0:0:0:0:0:1'));
        $this->assertSame(SerializeWordPressOperations::key('192.0.2.10'), SerializeWordPressOperations::key('::ffff:192.0.2.10'));
    }

    public function test_failed_remote_operation_is_not_retried_and_keeps_the_host_lease(): void
    {
        config(['blogshed.ssh_key' => '/tmp/test-key']);
        Process::fake(['*' => Process::result(exitCode: 255)]);
        Bus::fake();
        $blog = $this->blog('first');
        $middleware = new SerializeWordPressOperations;
        $job = new CreateCustomerBlog($blog->id);
        $middleware->handle($job, fn ($job) => $job->handle(app(WordPressProvisioner::class)));
        $this->assertSame('failed', $blog->fresh()->status);
        Process::assertRanTimes(fn ($process) => $process->timeout === 300, 1);
        Bus::assertNothingDispatched();

        $other = $this->blog('second');
        $middleware->handle(new CreateCustomerBlog($other->id), fn () => $this->fail('Uncertain operation released its lease.'));
        Bus::assertDispatched(CreateCustomerBlog::class, fn ($job) => $job->blogId === $other->id);
        Process::assertRanTimes(fn ($process) => $process->timeout === 300, 1);

        $this->travel(SerializeWordPressOperations::LOCK_SECONDS + 1)->seconds();
        $lock = Cache::store(config('blogshed.lock_store'))->lock(SerializeWordPressOperations::key('192.0.2.10'), 1);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_lock_tracks_remote_execution_for_create_delete_and_reset(): void
    {
        foreach (['create', 'delete', 'reset'] as $operation) {
            foreach (['preflight', 'complete_failure', 'invalid_response', 'disconnect', 'exception', 'signal'] as $scenario) {
                config(['blogshed.ssh_key' => $scenario === 'preflight' ? null : '/tmp/test-key']);
                $blog = $this->blog('example', status: match ($operation) {
                    'create' => 'pending', 'delete' => 'deleting', 'reset' => 'password_reset_pending',
                });
                $blog->update(['wp_admin_username' => 'admin_01234567',
                    'pending_wp_admin_password' => 'New password 123!', 'password_reset_token' => $token = (string) Str::uuid()]);
                $job = match ($operation) {
                    'create' => new CreateCustomerBlog($blog->id),
                    'delete' => new DeleteCustomerBlog($blog->id),
                    'reset' => new ResetWordPressPassword($blog->id, $token),
                };
                Process::swap(new Factory);
                Process::fake(['*' => function () use ($scenario) {
                    if ($scenario === 'exception') {
                        throw new \RuntimeException('Transport interrupted');
                    }

                    return Process::result(output: 'invalid response', exitCode: match ($scenario) {
                        'disconnect' => 255, 'signal' => 143, 'complete_failure' => 1, default => 0,
                    });
                }]);
                (new SerializeWordPressOperations)->handle($job, fn ($job) => $job->handle(app(WordPressProvisioner::class)));
                $uncertain = in_array($scenario, ['disconnect', 'exception', 'signal']);
                $this->assertSame($uncertain, $job->remoteOperationUncertain, "$operation $scenario");
                $lock = Cache::store(config('blogshed.lock_store'))->lock(SerializeWordPressOperations::key('192.0.2.10'), 1);
                $this->assertSame(! $uncertain, $lock->get(), "$operation $scenario lock");
                if ($scenario === 'preflight') {
                    Process::assertNothingRan();
                }
                $lock->forceRelease();
                $blog->delete();
                $blog->server->delete();
            }
        }
    }

    public function test_job_timeouts_are_failed_without_automatic_remote_retries(): void
    {
        $creating = $this->blog('creating', status: 'provisioning');
        $deleting = $this->blog('deleting', status: 'deleting');
        foreach ([new CreateCustomerBlog($creating->id), new DeleteCustomerBlog($deleting->id)] as $job) {
            $this->assertTrue($job->failOnTimeout);
            $this->assertSame(1, $job->tries);
            $this->assertGreaterThan(300, $job->timeout);
            $job->failed(null);
        }
        $this->assertSame('failed', $creating->fresh()->status);
        $this->assertSame('deletion_failed', $deleting->fresh()->status);
        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $this->assertGreaterThan(330, config("queue.connections.$connection.retry_after"));
        }
    }
}
