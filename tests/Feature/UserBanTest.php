<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerBlogController;
use App\Jobs\CreateCustomerBlog;
use App\Jobs\DeleteBannedUserBlog;
use App\Jobs\DeleteCustomerBlog;
use App\Jobs\Middleware\SerializeWordPressOperations;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\SignupAssessment;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserBanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->activated()->create(['is_admin' => true]);
    }

    private function blog(User $user, string $status = 'active'): CustomerBlog
    {
        $server = Server::create(['name' => 'Test', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Test', 'monthly_cost' => 1, 'active' => true]);

        return CustomerBlog::create(['user_id' => $user->id, 'server_id' => $server->id, 'subdomain' => 'test-shed', 'status' => $status]);
    }

    public function test_only_admins_can_ban_and_admin_targets_are_protected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $admin = $this->admin();
        $this->post(route('admin.users.ban', $user))->assertRedirect('/login');
        $this->actingAs($user)->post(route('admin.users.ban', $user))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.ban', $admin))->assertForbidden();
        $this->assertNull($user->fresh()->banned_at);
        $this->assertNull($admin->fresh()->banned_at);
        Queue::assertNothingPushed();
    }

    public function test_ban_without_blog_keeps_account_and_original_timestamp(): void
    {
        Queue::fake();
        $user = User::factory()->activated()->create();
        $this->actingAs($this->admin())->post(route('admin.users.ban', $user))->assertRedirect();
        $bannedAt = $user->fresh()->banned_at;
        $this->assertNotNull($bannedAt);
        $this->travel(1)->hour();
        $this->post(route('admin.users.ban', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->banned_at->equalTo($bannedAt));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        Queue::assertNothingPushed();
    }

    public function test_banned_user_cannot_create_blog_or_be_reactivated(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->forceFill(['banned_at' => now()])->save();
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Your account is banned.')->assertDontSee('Create my blog');
        $this->post('/account/blog', ['subdomain' => 'another-blog', 'description' => 'Test', 'terms_accepted' => 1])->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.users.activate', $user))->assertRedirect();
        $this->assertNull($user->fresh()->activated_at);
        $user->forceFill(['activated_at' => now()])->save();
        $this->actingAs($user)->post('/account/blog', ['subdomain' => 'another-blog', 'description' => 'Test', 'terms_accepted' => 1])->assertForbidden();
        $this->assertDatabaseCount('customer_blogs', 0);
        Queue::assertNothingPushed();
    }

    public function test_ban_reuses_existing_deletion_job_and_is_idempotent_after_deletion(): void
    {
        Queue::fake();
        $user = User::factory()->activated()->create();
        $blog = $this->blog($user);
        $this->actingAs($this->admin())->post(route('admin.users.ban', $user))->assertRedirect();
        Queue::assertPushed(DeleteBannedUserBlog::class, fn ($job) => $job->blogId === $blog->id);
        (new DeleteBannedUserBlog($blog->id))->handle();
        (new DeleteBannedUserBlog($blog->id))->handle();
        Queue::assertPushed(DeleteCustomerBlog::class, 1);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('delete')->once()->withArgs(fn ($target, $callback) => $target->id === $blog->id);
        (new DeleteCustomerBlog($blog->id))->handle($provisioner);
        (new DeleteCustomerBlog($blog->id))->handle($provisioner);
        (new DeleteBannedUserBlog($blog->id))->handle();
        $this->post(route('admin.users.ban', $user))->assertRedirect();
        $this->assertDatabaseMissing('customer_blogs', ['id' => $blog->id]);
        $this->assertNotNull($user->fresh()->banned_at);
        $this->actingAs($user)->post('/account/blog', ['subdomain' => 'test-shed', 'description' => 'Test', 'terms_accepted' => 1])->assertForbidden();
    }

    public function test_pending_setup_never_provisions_after_ban(): void
    {
        Queue::fake();
        $user = User::factory()->activated()->create();
        $blog = $this->blog($user, 'pending');
        $this->actingAs($this->admin())->post(route('admin.users.ban', $user));
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldNotReceive('create');
        (new CreateCustomerBlog($blog->id))->handle($provisioner);
        $this->assertSame('failed', $blog->fresh()->status);
        (new DeleteBannedUserBlog($blog->id))->handle();
        $this->assertSame('deleting', $blog->fresh()->status);
    }

    public function test_deletion_waits_for_existing_server_operation_then_clears_pending_reset(): void
    {
        Queue::fake();
        Bus::fake();
        config(['queue.default' => 'database']);
        $user = User::factory()->activated()->create();
        $user->forceFill(['banned_at' => now()])->save();
        $blog = $this->blog($user, 'password_resetting');
        $blog->update(['pending_wp_admin_password' => 'private-password', 'password_reset_token' => 'test-token']);
        $lock = Cache::store(config('blogshed.lock_store'))->lock(SerializeWordPressOperations::key('192.0.2.1'), 660);
        $this->assertTrue($lock->get());
        $middleware = new SerializeWordPressOperations;
        $middleware->handle(new DeleteBannedUserBlog($blog->id), function () {
            $this->fail('Must wait for existing operation.');
        });
        Bus::assertDispatched(DeleteBannedUserBlog::class);
        $this->assertSame('password_resetting', $blog->fresh()->status);
        $lock->release();
        $middleware->handle(new DeleteBannedUserBlog($blog->id), fn ($job) => $job->handle());
        $this->assertSame('deleting', $blog->fresh()->status);
        $this->assertNull($blog->fresh()->pending_wp_admin_password);
        $this->assertNull($blog->fresh()->password_reset_token);
        Bus::assertDispatched(DeleteCustomerBlog::class, 1);
    }

    public function test_failed_deletion_keeps_ban_and_can_be_retried(): void
    {
        Queue::fake();
        $user = User::factory()->activated()->create();
        $blog = $this->blog($user);
        $this->actingAs($this->admin())->post(route('admin.users.ban', $user));
        (new DeleteBannedUserBlog($blog->id))->handle();
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('delete')->once()->andThrow(new \RuntimeException('private remote output'));
        (new DeleteCustomerBlog($blog->id))->handle($provisioner);
        $this->assertSame('deletion_failed', $blog->fresh()->status);
        $this->assertNotNull($user->fresh()->banned_at);
        $this->get('/admin/users')->assertSee('Banned')->assertSee('Retry deletion')->assertDontSee('private remote output');
        $this->post(route('admin.users.ban', $user));
        (new DeleteBannedUserBlog($blog->id))->handle();
        $this->assertSame('deleting', $blog->fresh()->status);
        Queue::assertPushed(DeleteCustomerBlog::class, 2);
    }

    public function test_creation_rechecks_persisted_ban_even_with_stale_authenticated_user(): void
    {
        Queue::fake();
        $user = User::factory()->activated()->create();
        $this->blog($user)->delete();
        User::whereKey($user->id)->update(['banned_at' => now()]);
        $request = Request::create('/account/blog', 'POST', ['subdomain' => 'new-shed', 'description' => 'Test', 'terms_accepted' => 1]);
        $request->setUserResolver(fn () => $user);
        try {
            app(CustomerBlogController::class)->store($request);
            $this->fail('A stale authenticated user must not bypass a persisted ban.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('customer_blogs', 0);
        Queue::assertNothingPushed();
    }

    public function test_signup_summary_is_inline_and_ban_state_hides_credentials(): void
    {
        $user = User::factory()->activated()->create();
        $user->forceFill(['banned_at' => now()])->save();
        $blog = $this->blog($user);
        $blog->update(['wp_admin_username' => 'private-user', 'wp_admin_password' => 'private-secret']);
        SignupAssessment::create(['user_id' => $user->id, 'registration_ip' => '192.0.2.99', 'previous_ip_registrations' => 0, 'email_domain' => 'example.com', 'referrer' => '<script>alert(1)</script>', 'registered_at' => now()]);
        $this->actingAs($this->admin())->get('/admin/users')->assertOk()->assertSee('Registration IP')->assertSee('Previous signup count')->assertSee('Referrer')->assertSee('Email domain')->assertSee('192.0.2.99')->assertSee('Banned')->assertDontSee('Signup / Security')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('private-secret');
        $this->actingAs($user)->get('/account')->assertSee('Your account is banned.')->assertDontSee('private-secret')->assertDontSee('Reset Admin Password');
        $this->post(route('account.blog.password'), ['password' => 'new-long-password', 'password_confirmation' => 'new-long-password'])->assertForbidden();
    }
}
