<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function activate(User $user): RedirectResponse
    {
        User::whereKey($user->id)->whereNull('activated_at')->update(['activated_at' => now()]);

        return back()->with('status', 'User activated. They can now set up their blog.');
    }

    public function index(): View
    {
        return view('admin.users', ['users' => User::with(['customerBlog', 'signupAssessment'])->orderBy('name')->orderBy('id')->paginate(25)]);
    }
}
