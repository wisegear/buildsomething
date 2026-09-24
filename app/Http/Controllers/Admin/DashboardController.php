<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerBlog;
use App\Models\Post;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.index', [
            'userCount' => User::count(),
            'postCount' => Post::count(),
            'blogCount' => CustomerBlog::count(),
            'recentBlogs' => CustomerBlog::with('user')->latest()->latest('id')->limit(6)->get(),
        ]);
    }
}
