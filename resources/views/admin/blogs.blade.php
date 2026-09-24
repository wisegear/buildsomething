@extends('layouts.site')
@section('title', 'Customer blogs — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">YOUR COMMUNITY</p><h1>Blogs</h1><p>{{ number_format($blogs->total()) }} customer blogs</p></div></div>
    @if($errors->any())<div class="error-box" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">Blog</th><th scope="col">Owner</th><th scope="col">Server</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($blogs as $blog)
        <tr><td><a class="text-link" href="https://{{ $blog->domain }}" target="_blank" rel="noopener noreferrer">{{ $blog->domain }} ↗</a></td><td>{{ $blog->user?->name ?? 'Deleted user' }}<p>{{ $blog->user?->email }}</p></td><td>{{ $blog->server?->name ?? 'Unassigned' }}<p>{{ $blog->server?->location }}</p></td><td>{{ ucfirst(str_replace('_', ' ', $blog->status)) }}@if($blog->status === 'deletion_failed')<p>Check the server before retrying.</p>@endif</td><td>{{ $blog->created_at->format('j M Y') }}</td><td>
            @if($blog->server_id && in_array($blog->status, ['active', 'failed', 'deletion_failed', 'password_reset_failed']))
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
    @empty<tr><td colspan="6">No customer blogs yet.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $blogs->links() }}</div>
</section>
@endsection
