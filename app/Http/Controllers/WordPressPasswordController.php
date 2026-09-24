<?php

namespace App\Http\Controllers;

use App\Jobs\ResetWordPressPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WordPressPasswordController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->activated_at !== null, 403);
        $data = $request->validateWithBag('wordpressPassword', [
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed', 'not_regex:/[\x00-\x1F\x7F]/'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            // Never accept a blog ID, server or username from the form.
            $blog = $request->user()->customerBlog()->lockForUpdate()->first();
            if (! $blog || ! in_array($blog->status, ['active', 'password_reset_failed'], true) || ! $blog->server_id || ! $blog->wp_admin_username) {
                throw ValidationException::withMessages(['password' => 'Password reset is unavailable while another blog operation is in progress.'])
                    ->errorBag('wordpressPassword');
            }
            $token = (string) Str::uuid();
            $blog->update([
                'status' => 'password_reset_pending',
                'pending_wp_admin_password' => $data['password'],
                'password_reset_token' => $token,
                'failure_reason' => null,
            ]);
            ResetWordPressPassword::dispatch($blog->id, $token)->afterCommit();
        });

        return redirect()->route('account')->with('status', 'Your password reset is queued. This page will update when it is complete.');
    }
}
