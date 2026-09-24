<?php

namespace Tests\Feature;

use App\Jobs\CreateCustomerBlog;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_cannot_self_activate_or_request_a_blog(): void
    {
        Queue::fake();
        $this->post('/register', ['name' => 'Pending', 'email' => 'pending@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'activated_at' => now(), 'is_admin' => true])->assertRedirect('/account');
        $user = User::where('email', 'pending@example.com')->firstOrFail();
        $this->assertNull($user->activated_at);
        $this->get('/account')->assertOk()->assertSee('Your signup is currently being checked.')->assertDontSee('Create my blog')->assertDontSee('name="subdomain"', false);
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'pending-blog'])->assertRedirect('/account');
        $this->post(route('admin.users.activate', $user))->assertForbidden();
        $this->assertNull($user->fresh()->activated_at);
        $this->assertDatabaseCount('customer_blogs', 0);
        Queue::assertNothingPushed();
    }

    public function test_admin_activation_unlocks_setup_and_is_idempotent(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        Server::create(['name' => 'Test', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Test', 'monthly_cost' => 1, 'active' => true]);
        $this->actingAs($admin)->get('/admin/users')->assertSee('Activate User');
        $this->from('/admin/users')->post(route('admin.users.activate', $user))->assertRedirect('/admin/users');
        $activatedAt = $user->fresh()->activated_at;
        $this->assertNotNull($activatedAt);
        $this->travel(1)->hour();
        $this->post(route('admin.users.activate', $user))->assertRedirect();
        $this->assertTrue($activatedAt->equalTo($user->fresh()->activated_at));
        $this->actingAs($user->fresh())->get('/account')->assertSee('Create my blog')->assertDontSee('Your signup is currently being checked.');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'approved-blog'])->assertSessionHasNoErrors()->assertRedirect('/account');
        Queue::assertPushed(CreateCustomerBlog::class, 1);
    }

    public function test_guests_cannot_activate_users(): void
    {
        $user = User::factory()->create();
        $this->post(route('admin.users.activate', $user))->assertRedirect('/login');
        $this->assertNull($user->fresh()->activated_at);
    }

    public function test_worker_does_not_provision_for_an_unactivated_user(): void
    {
        $blog = CustomerBlog::create(['user_id' => User::factory()->create()->id, 'subdomain' => 'unapproved']);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldNotReceive('create');
        (new CreateCustomerBlog($blog->id))->handle($provisioner);
        $this->assertSame('failed', $blog->fresh()->status);
    }
}
