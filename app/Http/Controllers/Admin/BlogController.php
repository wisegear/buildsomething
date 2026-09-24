<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteCustomerBlog;
use App\Models\CustomerBlog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('admin.blogs', ['blogs' => CustomerBlog::with(['user', 'server'])->latest()->latest('id')->paginate(25)]);
    }

    public function destroy(Request $request, CustomerBlog $blog): RedirectResponse
    {
        $request->validate(['confirm_domain' => ['required', 'string', 'in:'.$blog->domain]]);

        DB::transaction(function () use ($blog): void {
            $blog = CustomerBlog::lockForUpdate()->findOrFail($blog->id);
            if (! in_array($blog->status, ['active', 'failed', 'deletion_failed', 'password_reset_failed'], true)) {
                throw ValidationException::withMessages(['blog' => 'Wait for the current blog operation to finish before deleting it.']);
            }
            if (! $blog->server_id) {
                throw ValidationException::withMessages(['blog' => 'This blog has no assigned server. Assign its actual hosting server before deleting it.']);
            }
            $blog->update(['status' => 'deleting', 'failure_reason' => null]);
            DeleteCustomerBlog::dispatch($blog->id)->afterCommit();
        });

        return back()->with('status', 'Blog deletion queued. The record will be removed after the server confirms deletion.');
    }
}
