<?php

namespace Tests\Feature;

use App\Jobs\DeleteCustomerBlog;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
