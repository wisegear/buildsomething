<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function index(): View
    {
        return view('index', ['posts' => Post::published()->latest('post_date')->latest('id')->take(3)->get()]);
    }

    public function blog(): View
    {
        return view('blog.index', ['posts' => Post::published()->latest('post_date')->latest('id')->paginate(9)]);
    }

    public function post(string $slug): View
    {
        return view('blog.show', ['post' => Post::published()->where('slug', $slug)->firstOrFail()]);
    }
}
