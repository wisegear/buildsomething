@extends('layouts.site')
@section('title', 'Customer blogs — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">YOUR COMMUNITY</p><h1>Blogs</h1><p>{{ number_format($blogs->total()) }} customer blogs</p></div></div>
    @if($errors->any())<div class="error-box" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">Blog</th><th scope="col">Owner</th><th scope="col">Server</th><th scope="col">Status</th><th scope="col">Resources</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($blogs as $blog)
        <tr><td><a class="text-link" href="https://{{ $blog->domain }}" target="_blank" rel="noopener noreferrer">{{ $blog->domain }} ↗</a></td><td>{{ $blog->user?->name ?? 'Deleted user' }}<p>{{ $blog->user?->email }}</p></td><td>{{ $blog->server?->name ?? 'Unassigned' }}<p>{{ $blog->server?->location }}</p></td><td>{{ ucfirst(str_replace('_', ' ', $blog->status)) }}@if($blog->status === 'deletion_failed')<p>Check the server before retrying.</p>@endif</td><td>
            <strong>{{ $blog->workers ?? 5 }} workers</strong><p>{{ $blog->memory_mb ?? 512 }} MB per worker</p>
            @if($blog->workers === null)<p>Provisioning defaults; not yet confirmed</p>@endif
            @if($blog->status === 'resource_update_failed')<p role="alert">Update unconfirmed — retry below.</p>@endif
            @if(in_array($blog->status, ['resource_update_pending', 'resource_updating']))<p>Applying {{ $blog->pending_workers }} workers / {{ $blog->pending_memory_mb }} MB…</p>@endif
            @if($blog->server_id && in_array($blog->status, ['active', 'resource_update_failed']))
            <details><summary class="text-link">Edit resources</summary>
                <form class="resource-form" method="POST" action="{{ route('admin.blogs.update', $blog) }}">
                    @csrf @method('PATCH')
                    <label for="workers-{{ $blog->id }}">Maximum workers</label>
                    <input id="workers-{{ $blog->id }}" name="workers" type="number" min="1" max="50" required value="{{ $blog->pending_workers ?? $blog->workers ?? 5 }}">
                    <label for="memory-{{ $blog->id }}">Memory per worker (MB)</label>
                    <input id="memory-{{ $blog->id }}" name="memory_mb" type="number" min="32" max="2048" required value="{{ $blog->pending_memory_mb ?? $blog->memory_mb ?? 512 }}">
                    <p>PHP memory limit per worker, not reserved RAM. Changes reload PHP on the hosting server.</p>
                    <button class="button" type="submit">Save resources</button>
                </form>
            </details>
            @endif
        </td><td>{{ $blog->created_at->format('j M Y') }}</td><td>
            @if($blog->server_id && in_array($blog->status, ['active', 'failed', 'deletion_failed', 'password_reset_failed', 'resource_update_failed']))
                <details><summary class="delete-link">Delete blog</summary>
                    <form method="POST" action="{{ route('admin.blogs.destroy', $blog) }}">
                        @csrf @method('DELETE')
                        <p>This permanently deletes the WordPress site and its content. The user account remains.</p>
                        <label for="confirm-{{ $blog->id }}">Type {{ $blog->domain }} to confirm</label>
                        <input id="confirm-{{ $blog->id }}" name="confirm_domain" type="text" required autocomplete="off">
                        <button class="delete-link" type="submit">Permanently delete blog</button>
                    </form>
                </details>
            @elseif(! $blog->server_id)<span>Assign hosting server first</span>
            @else<span>Operation in progress</span>@endif
        </td></tr>
    @empty<tr><td colspan="7">No customer blogs yet.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $blogs->links() }}</div>
</section>
@endsection
