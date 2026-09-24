<?php

namespace Tests\Feature;

use App\Jobs\CreateCustomerBlog;
use App\Models\CustomerBlog;
use App\Models\Server;
use App\Models\User;
use App\Services\WordPressProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CustomerBlogTest extends TestCase
{
    use RefreshDatabase;

    private function server(array $attributes = []): Server
    {
        return Server::create(array_merge(['name' => 'Primary', 'ip_address' => '192.0.2.1', 'location' => 'London', 'provider' => 'Host', 'monthly_cost' => 10, 'active' => true], $attributes));
    }

    public function test_blog_creation_requires_a_name_description_and_terms_agreement(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        $this->server();
        $this->actingAs(User::factory()->activated()->create());
        $valid = ['subdomain' => 'my-garden', 'description' => 'Stories from my garden.', 'terms_accepted' => '1'];

        foreach (['subdomain', 'description', 'terms_accepted'] as $field) {
            $data = $valid;
            unset($data[$field]);
            $this->from('/account')->post('/account/blog', $data)->assertSessionHasErrors($field);
        }
        foreach (['', '   ', str_repeat('a', 5001), ['invalid']] as $description) {
            $this->post('/account/blog', array_replace($valid, ['description' => $description]))->assertSessionHasErrors('description');
        }
        foreach (['0', false, 'no'] as $agreement) {
            $this->post('/account/blog', array_replace($valid, ['terms_accepted' => $agreement]))->assertSessionHasErrors('terms_accepted');
        }
        $this->assertDatabaseCount('customer_blogs', 0);
        Queue::assertNothingPushed();

        $this->post('/account/blog', $valid)->assertSessionHasNoErrors()->assertRedirect('/account');
        $this->assertDatabaseHas('customer_blogs', [
            'subdomain' => 'my-garden',
            'description' => 'Stories from my garden.',
            'terms_accepted' => true,
        ]);
        $this->assertTrue(CustomerBlog::firstOrFail()->terms_accepted);
        Queue::assertPushed(CreateCustomerBlog::class, 1);
    }

    public function test_locations_route_requests_to_the_selected_active_server(): void
    {
        Queue::fake();
        $london = $this->server();
        $paris = $this->server(['location' => 'Paris', 'ip_address' => '192.0.2.2']);
        $this->server(['location' => 'Berlin', 'active' => false]);
        $this->actingAs(User::factory()->activated()->create())->get('/account')->assertSee('Choose your blog location')->assertSee('Paris')->assertDontSee('Berlin');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'new-blog'])->assertSessionHasErrors('location');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'new-blog', 'location' => 'Berlin'])->assertSessionHasErrors('location');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'new-blog', 'location' => 'Paris'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_blogs', ['subdomain' => 'new-blog', 'server_id' => $paris->id]);
        $admin = User::factory()->activated()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->delete('/admin/servers/'.$paris->id);
        $this->assertDatabaseHas('servers', ['id' => $paris->id]);
    }

    public function test_no_active_servers_prevents_creation(): void
    {
        Queue::fake();
        $this->server(['active' => false]);
        $this->actingAs(User::factory()->activated()->create())->get('/account')->assertSee('No locations are available')->assertDontSee('Create my blog');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'new-blog'])->assertSessionHasErrors('location');
        $this->assertDatabaseCount('customer_blogs', 0);
        Queue::assertNothingPushed();
    }

    public function test_unassigned_or_inactive_servers_never_run_scripts(): void
    {
        Process::fake();
        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'unassigned']);
        (new CreateCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
        $this->assertSame('failed', $blog->fresh()->status);
        $blog->update(['status' => 'pending', 'server_id' => $this->server(['active' => false])->id]);
        (new CreateCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
        $this->assertSame('failed', $blog->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_user_can_request_one_blog_and_only_see_their_own_details(): void
    {
        Queue::fake();
        $server = $this->server();
        $owner = User::factory()->activated()->create();
        $other = User::factory()->activated()->create();

        $this->actingAs($owner)->get('/account')->assertDontSee('Choose your blog location')->assertSee('Create my blog');
        $this->actingAs($owner)->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'my-garden'])->assertRedirect('/account');
        $blog = $owner->customerBlog()->firstOrFail();
        $this->assertSame('pending', $blog->status);
        $this->assertSame($server->id, $blog->server_id);
        Queue::assertPushed(CreateCustomerBlog::class, 1);

        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'another-garden'])->assertRedirect('/account');
        $this->assertDatabaseCount('customer_blogs', 1);

        $blog->update(['status' => 'active', 'wp_admin_username' => 'admin_example', 'wp_admin_password' => 'secret-password']);
        $this->assertDatabaseMissing('customer_blogs', ['wp_admin_password' => 'secret-password']);
        $this->get('/account')->assertOk()->assertSee('my-garden.blogshed.uk')->assertSee('admin_example')->assertSee('secret-password')->assertDontSee('Create my blog');
        $this->actingAs($other)->get('/account')->assertOk()->assertDontSee('my-garden.blogshed.uk')->assertDontSee('secret-password');
        $this->get('/admin/posts')->assertForbidden();
    }

    public function test_invalid_or_taken_names_are_rejected(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->activated()->create())->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'www'])->assertSessionHasErrors('subdomain');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'Bad_Name'])->assertSessionHasErrors('subdomain');
        CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'taken', 'status' => 'pending']);
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => 'taken'])->assertSessionHasErrors('subdomain');
        Queue::assertNothingPushed();
    }

    public function test_user_with_blog_cannot_delete_account_and_orphan_the_remote_site(): void
    {
        $user = User::factory()->activated()->create();
        CustomerBlog::create(['user_id' => $user->id, 'subdomain' => 'my-shed', 'status' => 'active']);

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password', null, 'userDeletion');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('customer_blogs', ['subdomain' => 'my-shed']);
    }

    public function test_job_publishes_credentials_or_marks_the_request_failed(): void
    {
        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'little-shed', 'status' => 'pending']);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('create')->once()->andReturn(['username' => 'wp_person', 'password' => 'wp_secret']);

        (new CreateCustomerBlog($blog->id))->handle($provisioner);
        $this->assertSame('active', $blog->fresh()->status);
        $this->assertSame('wp_secret', $blog->fresh()->wp_admin_password);

        $failed = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'quiet-shed', 'status' => 'pending']);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('create')->once()->andThrow(new RuntimeException('SSH failed'));
        (new CreateCustomerBlog($failed->id))->handle($provisioner);
        $this->assertSame('failed', $failed->fresh()->status);
    }

    public function test_provisioner_reads_the_final_json_without_logging_credentials(): void
    {
        config()->set('blogshed.ssh_host', 'saturn.example');
        config()->set('blogshed.ssh_key', '/tmp/test-key');
        Process::fake([
            '*' => Process::result("Creating little-shed.blogshed.uk\n{\n  \"success\": true,\n  \"domain\": \"little-shed.blogshed.uk\",\n  \"admin_user\": \"wp_person\",\n  \"admin_password\": \"wp_secret\"\n}\n"),
        ]);

        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'little-shed', 'status' => 'pending']);
        $blog->update(['server_id' => $this->server()->id]);
        $blog->user->update(['email' => 'Owner+garden@example.com']);
        $credentials = app(WordPressProvisioner::class)->create($blog);

        $this->assertSame(['username' => 'wp_person', 'password' => 'wp_secret'], $credentials);
        Process::assertRan(fn ($process) => array_slice($process->command, -5) === [
            'sudo', '-n', '/usr/local/bin/create-wordpress', 'little-shed', "'Owner+garden@example.com'",
        ] && in_array('blogshed-deploy@192.0.2.1', $process->command, true));
    }

    public function test_invalid_owner_emails_never_reach_ssh(): void
    {
        Process::fake();
        $owner = User::factory()->activated()->create();
        $blog = CustomerBlog::create(['user_id' => $owner->id, 'server_id' => $this->server()->id, 'subdomain' => 'email-shed']);

        foreach (['', 'not-an-email', 'owner@example.com;whoami', "owner@example.com\n", '$(whoami)@example.com', "o'brien@example.com", str_repeat('a', 255).'@example.com'] as $email) {
            $owner->update(['email' => $email]);
            $blog->update(['status' => 'pending']);
            (new CreateCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
            $this->assertSame('failed', $blog->fresh()->status);
        }

        Process::assertNothingRan();
    }

    public function test_missing_owner_never_reaches_ssh(): void
    {
        Process::fake();
        $blog = new CustomerBlog(['subdomain' => 'missing-owner']);

        try {
            app(WordPressProvisioner::class)->create($blog);
            $this->fail('Provisioning should reject a missing owner.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The blog owner must have an email address supported by the provisioning script.', $exception->getMessage());
        }

        Process::assertNothingRan();
    }

    public function test_subdomain_length_boundary_matches_provisioning_limit(): void
    {
        Queue::fake();
        $this->server();
        $this->actingAs(User::factory()->activated()->create());
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => str_repeat('a', 29)])->assertSessionHasErrors('subdomain');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => "gar\nden"])->assertSessionHasErrors('subdomain');
        $this->post('/account/blog', ['description' => 'Stories from my garden.', 'terms_accepted' => '1', 'subdomain' => str_repeat('a', 28)])->assertSessionHasNoErrors();
        Queue::assertPushed(CreateCustomerBlog::class, 1);
    }

    public function test_invalid_stored_names_never_reach_ssh(): void
    {
        Process::fake();
        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'server_id' => $this->server()->id, 'subdomain' => 'garden']);
        foreach ([str_repeat('a', 29), 'ab', 'garden;whoami', '-garden', 'garden--shed', "garden\n"] as $name) {
            $blog->update(['subdomain' => $name, 'status' => 'pending']);
            (new CreateCustomerBlog($blog->id))->handle(app(WordPressProvisioner::class));
            $this->assertSame('failed', $blog->fresh()->status);
        }
        Process::assertNothingRan();
    }

    public function test_failure_logs_never_include_exception_credentials(): void
    {
        Log::spy();
        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'private-shed']);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('create')->once()->andThrow(new RuntimeException('Command output: admin_password=secret-value'));

        (new CreateCustomerBlog($blog->id))->handle($provisioner);

        Log::shouldHaveReceived('error')->once()->with('Blog provisioning failed', [
            'blog_id' => $blog->id, 'exception_type' => RuntimeException::class,
        ]);
        $this->assertSame('failed', $blog->fresh()->status);
        $this->assertStringNotContainsString('secret-value', $blog->fresh()->failure_reason);
    }

    public function test_overlapping_and_repeated_jobs_only_provision_once(): void
    {
        $blog = CustomerBlog::create(['user_id' => User::factory()->activated()->create()->id, 'subdomain' => 'only-once']);
        $provisioner = Mockery::mock(WordPressProvisioner::class);
        $provisioner->shouldReceive('create')->once()->andReturnUsing(function () use ($blog, $provisioner) {
            (new CreateCustomerBlog($blog->id))->handle($provisioner);

            return ['username' => 'owner', 'password' => 'secret'];
        });
        (new CreateCustomerBlog($blog->id))->handle($provisioner);
        (new CreateCustomerBlog($blog->id))->handle($provisioner);
        $this->assertSame('active', $blog->fresh()->status);
    }
}
