@extends('layouts.site')
@section('title', 'Users — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">YOUR COMMUNITY</p><h1>Users</h1><p>{{ number_format($users->total()) }} registered users</p></div></div>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">User</th><th scope="col">Blog</th><th scope="col">Status</th><th scope="col">Activation</th><th scope="col">Joined</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr><td><strong>{{ $user->name }}</strong><p>{{ $user->email }}</p>@if($user->is_admin)<span class="badge">Admin</span>@endif</td><td>@if($user->customerBlog)<a class="text-link" href="https://{{ $user->customerBlog->domain }}" target="_blank" rel="noopener noreferrer">{{ $user->customerBlog->domain }} ↗</a>@else<span>No blog yet</span>@endif</td><td>{{ $user->customerBlog ? ucfirst($user->customerBlog->status) : '—' }}</td><td>@if($user->activated_at)<span class="badge">Activated</span>@else<form method="POST" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="button small" type="submit">Activate User</button></form>@endif</td><td>{{ $user->created_at->format('j M Y') }}</td></tr>
        <tr><td colspan="5">@include('admin.partials.signup-security', ['assessment' => $user->signupAssessment])</td></tr>
    @empty<tr><td colspan="5">No users yet.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $users->links() }}</div>
</section>
@endsection
