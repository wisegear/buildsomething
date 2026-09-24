<?php

namespace App\Http\Controllers;

use App\Jobs\CreateCustomerBlog;
use App\Models\CustomerBlog;
use App\Models\Server;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerBlogController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->activated_at === null) {
            return redirect()->route('account')->with('status', 'Your signup is currently being checked.');
        }

        if ($request->user()->customerBlog()->exists()) {
            return redirect()->route('account')->with('status', 'Your blog request has already been received.');
        }

        $data = $request->validate([
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'terms_accepted' => ['required', 'accepted'],
            'subdomain' => [
                'required', 'string', 'min:3', 'max:28',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::notIn(['www', 'mail', 'admin', 'api', 'app', 'blog', 'help', 'support', 'test', 'staging', 'dev', 'ftp', 'smtp', 'ns1', 'ns2']),
                Rule::unique('customer_blogs', 'subdomain'),
            ],
        ]);

        try {
            DB::transaction(function () use ($request, $data): void {
                $servers = Server::where('active', true)->orderBy('id')->lockForUpdate()->get();
                if ($servers->isEmpty()) {
                    throw ValidationException::withMessages(['location' => 'No locations are available right now. Please try again later.']);
                }
                $server = $servers->count() === 1 ? $servers->first() : $servers->firstWhere('location', $request->input('location'));
                if (! $server) {
                    throw ValidationException::withMessages(['location' => 'Please select an available location.']);
                }
                $blog = CustomerBlog::create([
                    'server_id' => $server->id,
                    'user_id' => $request->user()->id,
                    'subdomain' => $data['subdomain'],
                    'description' => $data['description'],
                    'terms_accepted' => true,
                    'status' => 'pending',
                ]);

                CreateCustomerBlog::dispatch($blog->id)->afterCommit();
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['subdomain' => 'That name is already taken, or you already have a blog request.']);
        }

        return redirect()->route('account')->with('status', 'Your blog request is in. Setup will begin shortly.');
    }
}
