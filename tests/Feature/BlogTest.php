<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\BlogImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_merge(['title' => 'Your first website', 'seo_summary' => 'A place to start.', 'body' => '<h2>Hello</h2><p>Build something.</p>', 'post_date' => today()->format('Y-m-d'), 'action' => 'publish'], $extra);
    }

    public function test_admin_routes_reject_guests_and_non_admins(): void
    {
        $this->get('/admin/posts')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['/admin/posts', '/admin/posts/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/posts', $this->data())->assertForbidden();
        $post = Post::create(['title' => 'Test', 'slug' => 'test', 'seo_summary' => 'Test', 'body' => 'Test', 'post_date' => today()]);
        $this->get('/admin/posts/'.$post->id.'/edit')->assertForbidden();
        $this->put('/admin/posts/'.$post->id, $this->data())->assertForbidden();
        $this->delete('/admin/posts/'.$post->id)->assertForbidden();
    }

    public function test_admin_can_create_edit_publish_and_delete_a_post(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/posts/create')->assertOk();
        $this->post('/admin/posts', $this->data(['action' => 'draft']))->assertSessionHasNoErrors();
        $post = Post::firstOrFail();
        $this->assertFalse($post->is_published);
        $this->get('/admin/posts')->assertOk()->assertSee('Your first website');
        $this->get('/admin/posts/'.$post->id.'/edit')->assertOk();
        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->put('/admin/posts/'.$post->id, $this->data(['title' => 'An updated story', 'body' => '<p>Safe story</p><script>alert(1)</script><a href="javascript:alert(2)">Link</a>']))->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertTrue($post->is_published);
        $this->assertStringNotContainsString('<script', $post->body);
        $this->assertStringNotContainsString('javascript:', $post->body);
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('An updated story');
        $this->get('/')->assertOk()->assertSee('An updated story');
        $this->delete('/admin/posts/'.$post->id)->assertRedirect('/admin/posts');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_future_posts_stay_private_and_slug_collisions_are_handled(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/posts', $this->data(['post_date' => today()->addDays(2)->format('Y-m-d')]));
        $this->post('/admin/posts', $this->data(['action' => 'draft']));
        $this->assertSame(['your-first-website', 'your-first-website-2'], Post::orderBy('id')->pluck('slug')->all());
        $this->get('/blog/your-first-website')->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee('Your first website');
        $this->get('/')->assertOk()->assertDontSee('Your first website');
    }

    public function test_cover_images_have_compressed_variants_and_are_cleaned_up(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->post('/admin/posts', $this->data(['image' => UploadedFile::fake()->image('cover.jpg', 2000, 1200)]))->assertSessionHasNoErrors();
        $post = Post::firstOrFail();
        $old = $post->image;
        $images = app(BlogImages::class);
        foreach (['thumbnail' => 400, 'small' => 800, 'large' => 1600] as $size => $width) {
            $path = $images->variantPath($old, $size);
            Storage::disk('public')->assertExists($path);
            $info = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame($width, $info[0]);
            $this->assertSame('image/webp', $info['mime']);
        }
        $this->put('/admin/posts/'.$post->id, $this->data(['image' => UploadedFile::fake()->image('replacement.png', 200, 100)]))->assertSessionHasNoErrors();
        $new = $post->fresh()->image;
        Storage::disk('public')->assertMissing($old);
        foreach (['thumbnail', 'small', 'large'] as $size) {
            Storage::disk('public')->assertMissing($images->variantPath($old, $size));
            $info = getimagesize(Storage::disk('public')->path($images->variantPath($new, $size)));
            $this->assertSame(200, $info[0]);
        }
        $this->put('/admin/posts/'.$post->id, $this->data(['remove_image' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($post->fresh()->image);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->put('/admin/posts/'.$post->id, $this->data(['image' => UploadedFile::fake()->image('last.jpg')]))->assertSessionHasNoErrors();
        $this->delete('/admin/posts/'.$post->id);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_post_and_image_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/posts', $this->data(['title' => '', 'post_date' => 'bad', 'action' => 'invalid', 'image' => UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml')]))->assertSessionHasErrors(['title', 'post_date', 'action', 'image']);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_signup_cannot_grant_admin(): void
    {
        $this->post('/register', ['name' => 'Visitor', 'email' => 'visitor@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'is_admin' => true])->assertRedirect('/account');
        $this->assertFalse(User::where('email', 'visitor@example.com')->firstOrFail()->is_admin);
        $this->get('/account')->assertOk()->assertSee('Your signup is currently being checked.');

    }
}
