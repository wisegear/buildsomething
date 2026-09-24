<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServerRequest;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        return view('admin.servers.index', ['servers' => Server::orderBy('name')->orderBy('id')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.servers.form', ['server' => new Server]);
    }

    public function store(ServerRequest $request): RedirectResponse
    {
        Server::create($request->validated());

        return redirect()->route('admin.servers.index')->with('status', 'Server added.');
    }

    public function edit(Server $server): View
    {
        return view('admin.servers.form', compact('server'));
    }

    public function update(ServerRequest $request, Server $server): RedirectResponse
    {
        DB::transaction(function () use ($request, $server): void {
            $server = Server::lockForUpdate()->findOrFail($server->id);
            $data = $request->validated();
            if (inet_pton($data['ip_address']) !== inet_pton($server->ip_address)
                && $server->customerBlogs()->exists()) {
                throw ValidationException::withMessages([
                    'ip_address' => 'This server has assigned blogs. Move and reconcile those sites before changing its address.',
                ]);
            }
            $server->update($data);
        });

        return redirect()->route('admin.servers.index')->with('status', 'Server updated.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        if ($server->customerBlogs()->exists()) {
            return back()->with('status', 'This server has assigned blogs and cannot be removed. Set Active to No to stop new signups.');
        }
        $server->delete();

        return redirect()->route('admin.servers.index')->with('status', 'Server record removed.');
    }
}
