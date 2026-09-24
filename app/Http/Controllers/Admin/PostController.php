<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Services\BlogImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class PostController extends Controller
{
    public function index(): View
    {
        return view('admin.posts', ['posts' => Post::latest('post_date')->latest('id')->paginate(15), 'total' => Post::count(), 'published' => Post::published()->count()]);
    }

    public function create(): View
    {
        return view('admin.form', ['post' => new Post]);
    }

    public function edit(Post $post): View
    {
        return view('admin.form', compact('post'));
    }

    public function store(PostRequest $request, BlogImages $images): RedirectResponse
    {
        $post = new Post;
        $post->user_id = $request->user()->id;
        $post->slug = Post::uniqueSlug($request->validated('title'));

        return $this->save($request, $post, $images);
    }

    public function update(PostRequest $request, Post $post, BlogImages $images): RedirectResponse
    {
        return $this->save($request, $post, $images);
    }

    private function save(PostRequest $request, Post $post, BlogImages $images): RedirectResponse
    {
        $data = $request->safe()->only(['title', 'seo_summary', 'post_date', 'image_alt']);
        $sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig)->allowSafeElements()->allowRelativeLinks()->withMaxInputLength(200000));
        $data['body'] = $sanitizer->sanitize($request->validated('body'));
        $data['is_published'] = $request->validated('action') === 'publish';
        $old = $post->image;
        $new = null;
        if ($request->hasFile('image')) {
            $new = $images->store($request->file('image'));
            $data['image'] = $new;
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }
        try {
            $post->fill($data)->save();
        } catch (\Throwable $e) {
            if ($new) {
                $images->delete($new);
            } throw $e;
        }
        if ($old && ($new || $request->boolean('remove_image'))) {
            $images->delete($old);
        }

        return redirect()->route('admin.posts.edit', $post)->with('status', $post->is_published ? 'Post saved for publication.' : 'Draft saved.');
    }

    public function destroy(Post $post, BlogImages $images): RedirectResponse
    {
        $image = $post->image;
        $post->delete();
        if ($image) {
            $images->delete($image);
        }

        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
    }
}
