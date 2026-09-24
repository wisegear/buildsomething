@extends('layouts.site')
@section('title', 'Admin overview — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">BLOGSHED ADMIN</p><h1>Overview</h1><p>A little overview of your growing community.</p></div></div>
    <div class="admin-stats">
        <a class="panel stat-panel" href="{{ route('admin.users.index') }}"><span>Users</span><strong>{{ number_format($userCount) }}</strong><span class="text-link">View users ↗</span></a>
        <a class="panel stat-panel" href="{{ route('admin.posts.index') }}"><span>Blog posts</span><strong>{{ number_format($postCount) }}</strong><span class="text-link">Manage posts ↗</span></a>
        <div class="panel stat-panel"><span>Blogs</span><strong>{{ number_format($blogCount) }}</strong><p>Customer blogs across all statuses</p></div>
    </div>
    <div class="panel"><h2 class="admin-panel-title">Latest blogs</h2><p>The last 6 customer blogs created.</p>
        @forelse($recentBlogs as $blog)
            <div class="admin-row"><div><h3><a href="https://{{ $blog->domain }}" target="_blank" rel="noopener noreferrer">{{ $blog->domain }} ↗</a></h3><p>{{ $blog->user?->name ?? 'Deleted user' }} · {{ $blog->created_at->format('j M Y, H:i') }}</p></div><span class="badge">{{ ucfirst($blog->status) }}</span></div>
        @empty
            <div class="empty-state"><h3>No blogs yet</h3><p>New customer blogs will appear here.</p></div>
        @endforelse
    </div>
</section>
@endsection
