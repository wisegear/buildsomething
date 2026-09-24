<?php

namespace Tests\Feature;

use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Jobs\ResetWordPressPassword;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function blog(): CustomerBlog
    {
        config(['blogshed.ssh_key' => '/tmp/test-key']);
        $server = Server::create(['name' => 'Saturn', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 10, 'active' => false]);

        return CustomerBlog::create([
            'user_id' => User::factory()->activated()->create()->id, 'server_id' => $server->id,
            'subdomain' => 'garden', 'status' => 'active', 'wp_admin_username' => 'admin_01234567', 'wp_admin_password' => 'original-password',
        ]);
    }

    private function pending(CustomerBlog $blog): ResetWordPressPassword
    {
        $token = (string) Str::uuid();
        $blog->refresh()->update(['status' => 'password_reset_pending', 'pending_wp_admin_password' => 'New password!$ 123', 'password_reset_token' => $token]);

        return new ResetWordPressPassword($blog->id, $token);
    }

    public function test_owner_queues_reset_with_encrypted_password_and_no_secret_in_job(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $this->actingAs($blog->user)->get('/account')->assertSee('Reset Admin Password');
        $this->post(route('account.blog.password'), ['password' => 'New password!$ 123', 'password_confirmation' => 'New password!$ 123', 'blog_id' => 999])
            ->assertRedirect('/account')->assertSessionHasNoErrors();
        $this->assertSame('password_reset_pending', $blog->fresh()->status);
        $this->assertSame('New password!$ 123', $blog->fresh()->pending_wp_admin_password);
        $stored = DB::table('customer_blogs')->where('id', $blog->id)->value('pending_wp_admin_password');
        $this->assertStringNotContainsString('New password!$ 123', $stored);
        Queue::assertPushed(ResetWordPressPassword::class, function ($job) use ($blog) {
            $this->assertStringNotContainsString('New password!$ 123', serialize($job));

            return $job->blogId === $blog->id && $job->resetToken === $blog->fresh()->password_reset_token;
        });
        $this->get('/account')->assertSee('Your password reset is queued')->assertDontSee('original-password');
    }

    public function test_guests_other_users_and_inactive_users_cannot_reset_a_blog(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $data = ['password' => 'New password!$ 123', 'password_confirmation' => 'New password!$ 123', 'blog_id' => $blog->id];
        $this->post(route('account.blog.password'), $data)->assertRedirect('/login');
        $this->actingAs(User::factory()->activated()->create())->post(route('account.blog.password'), $data)->assertSessionHasErrors(['password'], null, 'wordpressPassword');
        $blog->user->forceFill(['activated_at' => null])->save();
        $this->actingAs($blog->user->fresh())->post(route('account.blog.password'), $data)->assertForbidden();
        Queue::assertNothingPushed();
        $this->assertSame('active', $blog->fresh()->status);
    }

    public function test_confirmation_strength_and_busy_state_are_validated_without_flashing_secrets(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $this->actingAs($blog->user);
        foreach ([['short', 'short'], ['Valid password 123', 'different'], ["long password\n123", "long password\n123"], [str_repeat('a', 129), str_repeat('a', 129)]] as [$password, $confirmation]) {
            $this->post(route('account.blog.password'), ['password' => $password, 'password_confirmation' => $confirmation])
                ->assertSessionHasErrors(['password'], null, 'wordpressPassword')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        }
        $blog->update(['status' => 'deleting']);
        $this->post(route('account.blog.password'), ['password' => 'Valid password 123', 'password_confirmation' => 'Valid password 123'])
            ->assertSessionHasErrors(['password'], null, 'wordpressPassword');
        Queue::assertNothingPushed();
    }

    public function test_success_updates_saved_credentials_only_after_exact_remote_confirmation(): void
    {
        $blog = $this->blog();
        $job = $this->pending($blog);
        Process::fake(['*' => Process::result('{"success":true,"password_reset":true,"domain":"garden.blogshed.uk","admin_username":"admin_01234567"}')]);
        $job->handle(app(WordPressProvisioner::class));
        $job->handle(app(WordPressProvisioner::class));
        Process::assertRanTimes(function ($process) {
            $this->assertSame("New password!$ 123\n", $process->input);
            $this->assertStringNotContainsString('New password', implode(' ', $process->command));

            return array_slice($process->command, -5) === ['sudo', '-n', '/usr/local/bin/reset-wordpress-password', 'garden', 'admin_01234567'];
        }, 1);
        $this->assertSame('active', $blog->fresh()->status);
        $this->assertSame('New password!$ 123', $blog->fresh()->wp_admin_password);
        $this->assertNull($blog->fresh()->pending_wp_admin_password);
        $this->assertNull($blog->fresh()->password_reset_token);
    }

    public function test_unconfirmed_results_clear_secrets_and_keep_site_for_recovery(): void
    {
        $blog = $this->blog();
        foreach ([
            ['{"success":true,"password_reset":true,"domain":"other.blogshed.uk","admin_username":"admin_01234567"}', 0],
            ['{"success":true,"password_reset":true,"domain":"garden.blogshed.uk","admin_username":"admin_deadbeef"}', 0],
            ['{"success":true,"password_reset":true,"domain":"garden.blogshed.uk","admin_username":"admin_01234567"}', 1],
            ['invalid output', 0],
        ] as [$output, $code]) {
            $job = $this->pending($blog);
            Process::fake(['*' => Process::result(output: $output, exitCode: $code)]);
            $job->handle(app(WordPressProvisioner::class));
            $this->assertSame('password_reset_failed', $blog->fresh()->status);
            $this->assertNull($blog->fresh()->pending_wp_admin_password);
            $this->assertNull($blog->fresh()->wp_admin_password);
            $this->assertFalse($job->remoteOperationUncertain);
        }
    }

    public function test_stale_jobs_cannot_claim_or_fail_a_new_reset(): void
    {
        $blog = $this->blog();
        $old = $this->pending($blog);
        $new = $this->pending($blog);
        Process::fake();
        $old->handle(app(WordPressProvisioner::class));
        $old->failed(null);
        Process::assertNothingRan();
        $this->assertSame($new->resetToken, $blog->fresh()->password_reset_token);
        $this->assertSame('password_reset_pending', $blog->fresh()->status);
    }

    public function test_reset_deferral_preserves_request_token_and_contains_no_password(): void
    {
        Bus::fake();
        config(['queue.default' => 'database']);
        $blog = $this->blog();
        $job = $this->pending($blog);
        $lock = Cache::store(config('blogshed.lock_store'))->lock(SerializeWordPressOperations::key('192.0.2.1'), 660);
        $lock->get();
        try {
            (new SerializeWordPressOperations)->handle($job, fn () => $this->fail('Reset ran during another host operation.'));
            Bus::assertDispatched(ResetWordPressPassword::class, fn ($deferred) => $deferred->resetToken === $job->resetToken && $deferred->blogId === $job->blogId && $deferred->delay === 15);
        } finally {
            $lock->release();
        }
    }

    public function test_reset_timeout_clears_pending_password_and_logs_do_not_include_secrets(): void
    {
        $blog = $this->blog();
        $job = $this->pending($blog);
        $job->failed(null);
        $this->assertSame('password_reset_failed', $blog->fresh()->status);
        $this->assertNull($blog->fresh()->pending_wp_admin_password);
        Log::spy();
        $job = $this->pending($blog);
        $service = \Mockery::mock(WordPressProvisioner::class);
        $service->shouldReceive('resetPassword')->once()->andThrow(new \RuntimeException('New password!$ 123'));
        $job->handle($service);
        Log::shouldHaveReceived('error')->once()->with('WordPress password reset failed', ['blog_id' => $blog->id, 'exception_type' => \RuntimeException::class]);
    }
}
