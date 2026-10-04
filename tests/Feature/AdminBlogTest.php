<?php

namespace Tests\Feature;

use App\Jobs\DeleteCustomerBlog;
use App\Jobs\UpdateBlogResources;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminBlogTest extends TestCase
{
    use RefreshDatabase;

    private function blog(): CustomerBlog
    {
        $server = Server::create(['name' => 'London server', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 10, 'active' => false]);

        return CustomerBlog::create(['user_id' => User::factory()->create()->id, 'server_id' => $server->id, 'subdomain' => 'garden', 'status' => 'active', 'wp_admin_password' => 'private-password']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_resource_service_requires_exact_server_confirmation(): void
    {
        $blog = $this->blog();
        $blog->update(['pending_workers' => 8, 'pending_memory_mb' => 256]);
        config(['blogshed.ssh_key' => '/test/key']);
        Process::fake([
            '*' => Process::result(output: json_encode(['success' => true, 'domain' => $blog->domain, 'workers' => 8, 'memory_mb' => 256])),
        ]);
        (new WordPressProvisioner)->updateResources($blog);
        Process::assertRan(fn ($process) => in_array('/usr/local/bin/update-wordpress-resources', $process->command, true));
        Process::fake([
            '*' => Process::result(output: json_encode(['success' => true, 'domain' => 'other.blogshed.uk', 'workers' => 8, 'memory_mb' => 256])),
        ]);
        $this->expectException(RuntimeException::class);
        (new WordPressProvisioner)->updateResources($blog);
    }

    public function test_resource_changes_are_admin_only_validated_and_queued_once(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $url = '/admin/blogs/'.$blog->id;
        $this->patch($url, ['workers' => 8, 'memory_mb' => 256])->assertRedirect('/login');
        $this->actingAs($blog->user)->patch($url, ['workers' => 8, 'memory_mb' => 256])->assertForbidden();
        $this->actingAs($this->admin())->patch($url, ['workers' => 0, 'memory_mb' => 4096])->assertSessionHasErrors(['workers', 'memory_mb']);
        $this->patch($url, ['workers' => 8, 'memory_mb' => 256])->assertSessionHasNoErrors();
        $this->assertSame('resource_update_pending', $blog->fresh()->status);
        $this->assertNull($blog->fresh()->workers);
        $this->patch($url, ['workers' => 9, 'memory_mb' => 512])->assertSessionHasErrors('blog');
        Queue::assertPushed(UpdateBlogResources::class, 1);
    }

    public function test_resource_job_confirms_values_and_ignores_stale_jobs(): void
    {
        $blog = $this->blog();
        $blog->update(['status' => 'resource_update_pending', 'resource_update_token' => 'current', 'pending_workers' => 8, 'pending_memory_mb' => 256]);
        $service = Mockery::mock(WordPressProvisioner::class);
        $service->shouldReceive('updateResources')->once()->andReturnNull();
        (new UpdateBlogResources($blog->id, 'stale'))->handle($service);
        (new UpdateBlogResources($blog->id, 'current'))->handle($service);
        $this->assertSame('active', $blog->fresh()->status);
        $this->assertSame(8, $blog->fresh()->workers);
        $this->assertSame(256, $blog->fresh()->memory_mb);
    }

    public function test_unconfirmed_resource_changes_preserve_last_confirmed_values(): void
    {
        $blog = $this->blog();
        $blog->update(['status' => 'resource_update_pending', 'resource_update_token' => 'current', 'workers' => 5, 'memory_mb' => 512, 'pending_workers' => 8, 'pending_memory_mb' => 256]);
        $service = Mockery::mock(WordPressProvisioner::class);
        $service->shouldReceive('updateResources')->once()->andThrow(new RuntimeException('Disconnected'));
        (new UpdateBlogResources($blog->id, 'current'))->handle($service);
        $this->assertSame('resource_update_failed', $blog->fresh()->status);
        $this->assertSame(5, $blog->fresh()->workers);
        $this->assertSame(512, $blog->fresh()->memory_mb);
    }

    public function test_admin_can_list_and_queue_a_confirmed_deletion_only_once(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $this->actingAs($this->admin())->get('/admin/blogs')->assertOk()->assertSee('garden.blogshed.uk')->assertSee('London server')->assertDontSee('private-password');
        $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => 'wrong'])->assertSessionHasErrors('confirm_domain');
        Queue::assertNothingPushed();
        $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => $blog->domain])->assertSessionHasNoErrors();
        $this->assertSame('deleting', $blog->fresh()->status);
        $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => $blog->domain])->assertSessionHasErrors('blog');
        Queue::assertPushed(DeleteCustomerBlog::class, 1);
    }

    public function test_non_admins_cannot_list_or_delete_blogs(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $this->get('/admin/blogs')->assertRedirect('/login');
        $this->delete('/admin/blogs/'.$blog->id)->assertRedirect('/login');
        $this->actingAs($blog->user)->get('/admin/blogs')->assertForbidden();
        $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => $blog->domain])->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_unassigned_and_provisioning_blogs_cannot_be_deleted(): void
    {
        Queue::fake();
        $blog = $this->blog();
        $this->actingAs($this->admin());
        foreach (['pending', 'provisioning'] as $status) {
            $blog->update(['status' => $status]);
            $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => $blog->domain])->assertSessionHasErrors('blog');
        }
        $blog->update(['status' => 'active', 'server_id' => null]);
        $this->delete('/admin/blogs/'.$blog->id, ['confirm_domain' => $blog->domain])->assertSessionHasErrors('blog');
        Queue::assertNothingPushed();
    }

    public function test_job_removes_record_only_after_remote_success_and_keeps_owner(): void
    {
        $blog = $this->blog();
        $blog->update(['status' => 'deleting']);
        $service = Mockery::mock(WordPressProvisioner::class);
        $service->shouldReceive('delete')->once()->withArgs(fn ($target) => $target->id === $blog->id)->andReturnNull();
        (new DeleteCustomerBlog($blog->id))->handle($service);
        (new DeleteCustomerBlog($blog->id))->handle($service);
        $this->assertDatabaseMissing('customer_blogs', ['id' => $blog->id]);
        $this->assertDatabaseHas('users', ['id' => $blog->user_id]);
    }

    public function test_remote_failure_preserves_the_record_and_assignment(): void
    {
        $blog = $this->blog();
        $blog->update(['status' => 'deleting']);
        $service = Mockery::mock(WordPressProvisioner::class);
        $service->shouldReceive('delete')->once()->andThrow(new RuntimeException('Remote failure'));
        (new DeleteCustomerBlog($blog->id))->handle($service);
        $this->assertSame('deletion_failed', $blog->fresh()->status);
        $this->assertSame($blog->server_id, $blog->fresh()->server_id);
    }
}
