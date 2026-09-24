<?php

namespace Tests\Feature;

use App\Jobs\DeleteCustomerBlog;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class WordPressDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function blog(): CustomerBlog
    {
        config(['blogshed.ssh_user' => 'blogshed-deploy', 'blogshed.ssh_key' => '/tmp/test-key', 'blogshed.ssh_port' => 22, 'blogshed.ssh_host' => 'wrong-host']);
        $server = Server::create(['name' => 'Assigned server', 'ip_address' => '192.0.2.5', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 10, 'active' => false]);

        return CustomerBlog::create(['user_id' => User::factory()->create()->id, 'server_id' => $server->id, 'subdomain' => 'garden', 'status' => 'deleting']);
    }

    public function test_confirmed_deletion_runs_on_assigned_inactive_server_and_removes_record(): void
    {
        $blog = $this->blog();
        Process::fake(['*' => Process::result(output: "{\n\"success\":true,\"domain\":\"garden.blogshed.uk\",\"deleted\":true,\"deletion_seconds\":2\n}", errorOutput: 'Removing site files...')]);
        (new DeleteCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
        Process::assertRan(fn ($process) => $process->command === ['ssh', '-i', '/tmp/test-key', '-p', '22', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10', 'blogshed-deploy@192.0.2.5', 'sudo', '-n', '/usr/local/bin/delete-wordpress', 'garden']);
        $this->assertDatabaseMissing('customer_blogs', ['id' => $blog->id]);
        $this->assertDatabaseHas('users', ['id' => $blog->user_id]);
    }

    public function test_unconfirmed_responses_preserve_blog(): void
    {
        $blog = $this->blog();
        foreach ([
            ['{"success":false,"error":"Metadata not found"}', 1],
            ['{"success":true,"domain":"garden.blogshed.uk","deleted":true}', 1],
            ['{"success":true,"domain":"different.blogshed.uk","deleted":true}', 0],
            ['{"success":true,"domain":"garden.blogshed.uk"}', 0],
            ['{"success":true,"domain":"garden.blogshed.uk","deleted":false}', 0],
            ['not JSON', 0],
        ] as [$output, $exitCode]) {
            $blog->update(['status' => 'deleting']);
            Process::fake(['*' => Process::result(output: $output, exitCode: $exitCode)]);
            (new DeleteCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
            $this->assertSame('deletion_failed', $blog->fresh()->status);
        }
    }

    public function test_invalid_targets_never_run_remote_commands(): void
    {
        $blog = $this->blog();
        Process::fake();
        foreach ([str_repeat('a', 29), 'garden;whoami', '-garden'] as $name) {
            $blog->update(['subdomain' => $name, 'status' => 'deleting']);
            (new DeleteCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
            $this->assertSame('deletion_failed', $blog->fresh()->status);
        }
        $blog->update(['subdomain' => 'garden', 'server_id' => null, 'status' => 'deleting']);
        (new DeleteCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
        $this->assertSame('deletion_failed', $blog->fresh()->status);
        Process::assertNothingRan();
    }
}
