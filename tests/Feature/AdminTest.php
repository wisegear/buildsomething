<?php

namespace Tests\Feature;

use App\Models\CustomerBlog;
use App\Models\Post;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_dashboard_counts_and_limits_recent_blogs_without_showing_posts(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 7; $i++) {
            CustomerBlog::create(['user_id' => User::factory()->create()->id, 'subdomain' => 'garden'.$i]);
        }
        Post::create(['title' => 'Separate post title', 'slug' => 'separate', 'seo_summary' => 'Summary', 'body' => 'Body', 'post_date' => today()]);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertViewHas('userCount', 8)->assertViewHas('blogCount', 7)->assertViewHas('postCount', 1)
            ->assertViewHas('recentBlogs', fn ($blogs) => $blogs->pluck('subdomain')->all() === ['garden6', 'garden5', 'garden4', 'garden3', 'garden2', 'garden1'])
            ->assertDontSee('garden0.blogshed.uk')->assertDontSee('Separate post title');
        $this->get('/admin/posts')->assertOk()->assertSee('Separate post title');
        $this->get('/admin/users')->assertOk()->assertSee('https://garden0.blogshed.uk', false)->assertSee('No blog yet');
    }

    public function test_servers_can_be_created_edited_and_removed(): void
    {
        $this->actingAs($this->admin());
        $data = ['name' => 'Primary server', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Example host', 'monthly_cost' => '12.50', 'active' => '1'];
        $this->get('/admin/servers')->assertOk()->assertSee('No servers yet');
        $this->get('/admin/servers/create')->assertOk();
        $this->post('/admin/servers', $data)->assertRedirect('/admin/servers')->assertSessionHasNoErrors();
        $server = Server::firstOrFail();
        $this->assertSame('12.50', $server->monthly_cost);
        $this->assertTrue($server->active);
        $this->get('/admin/servers')->assertOk()->assertSee('Primary server')->assertSee('£12.50');
        $this->get('/admin/servers/'.$server->id.'/edit')->assertOk()->assertSee('192.0.2.1');
        $this->put('/admin/servers/'.$server->id, array_merge($data, ['ip_address' => '2001:db8::1', 'monthly_cost' => '0', 'active' => '0']))->assertSessionHasNoErrors();
        $this->assertSame('2001:db8::1', $server->fresh()->ip_address);
        $this->assertFalse($server->fresh()->active);
        $this->get('/admin/servers')->assertSee('<td>No</td>', false);
        $this->post('/admin/servers', array_merge($data, ['name' => 'Second server']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('servers', 2);
        $this->delete('/admin/servers/'.$server->id)->assertRedirect('/admin/servers');
        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_assigned_server_address_cannot_redirect_existing_blog_operations(): void
    {
        $data = ['name' => 'Primary', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => '10', 'active' => '1'];
        $server = Server::create($data);
        CustomerBlog::create(['user_id' => User::factory()->create()->id, 'server_id' => $server->id, 'subdomain' => 'existing-shed']);
        $this->actingAs($this->admin());

        $this->put('/admin/servers/'.$server->id, array_replace($data, ['ip_address' => '192.0.2.2']))
            ->assertSessionHasErrors('ip_address');
        $this->assertSame('192.0.2.1', $server->fresh()->ip_address);
        $this->put('/admin/servers/'.$server->id, array_replace($data, ['active' => '0', 'name' => 'Paused']))
            ->assertSessionHasNoErrors();
        $this->assertFalse($server->fresh()->active);
        $this->assertSame('Paused', $server->fresh()->name);
    }

    public function test_server_details_are_validated(): void
    {
        $this->actingAs($this->admin())->post('/admin/servers', ['name' => '', 'ip_address' => 'invalid', 'location' => '', 'provider' => '', 'monthly_cost' => '-1', 'active' => 'invalid'])
            ->assertSessionHasErrors(['name', 'ip_address', 'location', 'provider', 'monthly_cost', 'active']);
        $this->post('/admin/servers', ['name' => 'Test', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => '1.234'])->assertSessionHasErrors('monthly_cost');
        $this->assertDatabaseCount('servers', 0);
    }

    public function test_new_admin_sections_are_private(): void
    {
        $server = Server::create(['name' => 'Private server', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 1]);
        foreach (['/admin', '/admin/users', '/admin/servers', '/admin/servers/create', '/admin/servers/'.$server->id.'/edit'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->create());
        foreach (['/admin', '/admin/users', '/admin/servers', '/admin/servers/create', '/admin/servers/'.$server->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/servers', [])->assertForbidden();
        $this->put('/admin/servers/'.$server->id, [])->assertForbidden();
        $this->delete('/admin/servers/'.$server->id)->assertForbidden();
        $this->assertDatabaseCount('servers', 1);
    }

    public function test_users_are_available_across_pages(): void
    {
        $admin = $this->admin();
        User::factory()->count(26)->create();
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 27 && $users->count() === 25);
        $this->get('/admin/users?page=2')->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 2);
    }
}
