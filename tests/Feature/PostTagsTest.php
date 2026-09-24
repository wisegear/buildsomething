<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTagsTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return array_merge(['title' => 'Tagged story', 'seo_summary' => 'A guide', 'body' => '<p>Hello</p>', 'post_date' => today()->format('Y-m-d'), 'action' => 'publish', 'tags' => ' WordPress, writing, WORDPRESS, , '], $extra);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
    }

    public function test_tags_are_normalized_reused_editable_and_removable(): void
    {
        $this->admin();
        $this->post('/admin/posts', $this->data())->assertSessionHasNoErrors();
        $post = Post::firstOrFail();
        $this->assertSame(['wordpress', 'writing'], $post->tags->pluck('name')->all());
        $this->get('/admin/posts/'.$post->id.'/edit')->assertOk()->assertSee('wordpress, writing');
        $this->post('/admin/posts', $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tags', 2);
        $this->put('/admin/posts/'.$post->id, $this->data(['tags' => 'writing, Gardening']))->assertSessionHasNoErrors();
        $this->assertSame(['gardening', 'writing'], $post->fresh()->tags->pluck('name')->all());
        $this->put('/admin/posts/'.$post->id, array_diff_key($this->data(), ['tags' => true]))->assertSessionHasNoErrors();
        $this->assertCount(2, $post->fresh()->tags);
        $this->put('/admin/posts/'.$post->id, $this->data(['tags' => '']))->assertSessionHasNoErrors();
        $this->assertCount(0, $post->fresh()->tags);
        $other = Post::latest('id')->first();
        $this->delete('/admin/posts/'.$other->id)->assertRedirect();
        $this->assertDatabaseMissing('post_tag', ['post_id' => $other->id]);
    }

    public function test_public_tag_filters_and_pagination_only_show_published_posts(): void
    {
        $tag = Tag::create(['name' => 'writing']);
        $privateTag = Tag::create(['name' => 'private topic']);
        foreach (range(1, 12) as $number) {
            $post = Post::create(['title' => 'Story '.$number, 'slug' => 'story-'.$number, 'seo_summary' => 'Summary', 'body' => 'Content', 'post_date' => $number === 12 ? today()->addDay() : today(), 'is_published' => $number !== 11]);
            $post->tags()->attach($tag);
            if ($number > 10) {
                $post->tags()->attach($privateTag);
            }
        }
        $this->get('/blog?tag='.$tag->id)->assertOk()->assertSee('Notes about')->assertSee('tag='.$tag->id.'&amp;page=2', false)
            ->assertDontSee('Story 11')->assertDontSee('Story 12')->assertDontSee('private topic');
        $this->get('/blog?tag='.$tag->id.'&page=2')->assertOk()->assertSee('Story 1')->assertDontSee('Story 11');
        $this->get('/blog/story-1')->assertOk()->assertSee(route('blog.index', ['tag' => $tag->id]), false);
        $this->get('/')->assertOk()->assertSee('writing');
        $this->get('/blog?tag='.$privateTag->id)->assertNotFound();
        $this->get('/blog?tag=99999')->assertNotFound();
        $this->get('/blog/story-11')->assertNotFound();
        $this->get('/blog/story-12')->assertNotFound();
    }

    public function test_invalid_tags_do_not_create_posts_or_tags(): void
    {
        $this->admin();
        foreach ([['unexpected'], str_repeat('a', 51), implode(',', range(1, 11)), '<script>alert(1)</script>'] as $tags) {
            $this->post('/admin/posts', $this->data(['tags' => $tags]))->assertSessionHasErrors('tags');
        }
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_tag_migration_and_rollback_preserve_existing_posts(): void
    {
        $post = Post::create(['title' => 'Existing content', 'slug' => 'existing', 'seo_summary' => 'Summary', 'body' => 'Keep this content', 'post_date' => today(), 'is_published' => true]);
        $migration = require database_path('migrations/2026_09_24_200000_create_post_tags_tables.php');
        $migration->down();
        $migration->up();
        $this->assertSame('Keep this content', $post->fresh()->body);
        $this->assertCount(0, $post->fresh()->tags);
    }

    public function test_existing_untagged_posts_and_empty_blog_still_render(): void
    {
        $this->get('/blog')->assertOk()->assertSee('A fresh notebook.');
        $post = Post::create(['title' => 'Legacy post', 'slug' => 'legacy', 'seo_summary' => 'Summary', 'body' => 'Content', 'post_date' => today(), 'is_published' => true]);
        $this->get('/blog')->assertOk()->assertSee($post->title);
        $this->get('/blog/legacy')->assertOk();
    }
}
