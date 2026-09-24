<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function index(): View
    {
        return view('index', ['posts' => Post::published()->with('tags')->latest('post_date')->latest('id')->take(3)->get()]);
    }

    public function blog(Request $request): View
    {
        $request->validate(['tag' => ['nullable', 'integer', 'min:1']]);
        $tag = $request->filled('tag')
            ? Tag::whereHas('posts', fn ($query) => $query->published())->findOrFail($request->integer('tag'))
            : null;
        $posts = Post::published()->with('tags')
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($tag->id)))
            ->latest('post_date')->latest('id')->paginate(9)->withQueryString();
        $tags = Tag::whereHas('posts', fn ($query) => $query->published())->orderBy('name')->get();

        return view('blog.index', compact('posts', 'tags', 'tag'));
    }

    public function post(string $slug): View
    {
        return view('blog.show', ['post' => Post::published()->with('tags')->where('slug', $slug)->firstOrFail()]);
    }
}
