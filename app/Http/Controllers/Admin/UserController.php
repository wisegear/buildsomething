<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteBannedUserBlog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function activate(User $user): RedirectResponse
    {
        User::whereKey($user->id)->whereNull('activated_at')->whereNull('banned_at')->update(['activated_at' => now()]);

        return back()->with('status', 'User activated. They can now set up their blog.');
    }

    public function ban(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            // Creation uses the same user lock, so it cannot slip past a ban.
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_if($user->is_admin, 403, 'Administrators cannot be banned.');
            if ($user->banned_at === null) {
                $user->forceFill(['banned_at' => now()])->save();
            }
            $blog = $user->customerBlog()->first();
            if ($blog && $blog->status !== 'deleting') {
                DeleteBannedUserBlog::dispatch($blog->id)->afterCommit();
            }
        });

        return back()->with('status', 'User banned. Their account is retained and any blog deletion has been queued.');
    }

    public function unban(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_if($user->is_admin, 403, 'Administrators cannot be unbanned.');
            // Serialize with the ban deletion job's decision to start deletion.
            $user->customerBlog()->lockForUpdate()->first();
            $user->forceFill(['banned_at' => null])->save();
        });

        return back()->with('status', 'User unbanned. Their previous activation status is retained. Blog deletion already started cannot be undone.');
    }

    public function index(): View
    {
        return view('admin.users', ['users' => User::with(['customerBlog', 'signupAssessment'])->orderBy('name')->orderBy('id')->paginate(25)]);
    }
}
