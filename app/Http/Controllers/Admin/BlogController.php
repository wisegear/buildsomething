<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteCustomerBlog;
use App\Jobs\UpdateBlogResources;
use App\Models\CustomerBlog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('admin.blogs', ['blogs' => CustomerBlog::with(['user', 'server'])->latest()->latest('id')->paginate(25)]);
    }

    public function update(Request $request, CustomerBlog $blog): RedirectResponse
    {
        $values = $request->validate([
            'workers' => ['required', 'integer', 'between:1,50'],
            'memory_mb' => ['required', 'integer', 'between:32,2048'],
        ]);
        DB::transaction(function () use ($blog, $values): void {
            $blog = CustomerBlog::lockForUpdate()->findOrFail($blog->id);
            if (! $blog->server_id || ! in_array($blog->status, ['active', 'resource_update_failed'], true)) {
                throw ValidationException::withMessages(['blog' => 'Resources can only be changed for an active blog with an assigned server.']);
            }
            $token = (string) Str::uuid();
            $blog->update(['status' => 'resource_update_pending', 'pending_workers' => $values['workers'],
                'pending_memory_mb' => $values['memory_mb'], 'resource_update_token' => $token, 'failure_reason' => null]);
            UpdateBlogResources::dispatch($blog->id, $token)->afterCommit();
        });

        return back()->with('status', 'Resource update queued. Values will be confirmed after the server applies them.');
    }

    public function destroy(Request $request, CustomerBlog $blog): RedirectResponse
    {
        $request->validate(['confirm_domain' => ['required', 'string', 'in:'.$blog->domain]]);

        DB::transaction(function () use ($blog): void {
            $blog = CustomerBlog::lockForUpdate()->findOrFail($blog->id);
            if (! in_array($blog->status, ['active', 'failed', 'deletion_failed', 'password_reset_failed', 'resource_update_failed'], true)) {
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
