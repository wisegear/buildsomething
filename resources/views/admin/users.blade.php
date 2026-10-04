@extends('layouts.site')
@section('title', 'Users — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">YOUR COMMUNITY</p><h1>Users</h1><p>{{ number_format($users->total()) }} registered users</p></div></div>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">User</th><th scope="col">Blog</th><th scope="col">Status</th><th scope="col">Activation</th><th scope="col">Joined</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr class="admin-user-details">
            <td><strong>{{ $user->name }}</strong><p>{{ $user->email }}</p>@if($user->is_admin)<span class="badge">Admin</span>@endif @if($user->banned_at)<span class="badge badge-banned">Banned</span>@endif</td>
            <td>@if($user->customerBlog)<a class="text-link" href="https://{{ $user->customerBlog->domain }}" target="_blank" rel="noopener noreferrer">{{ $user->customerBlog->domain }} ↗</a>@else<span>No blog yet</span>@endif</td>
            <td>{{ $user->customerBlog ? ucfirst(str_replace('_', ' ', $user->customerBlog->status)) : '—' }}@if($user->banned_at && $user->customerBlog && $user->customerBlog->status !== 'deleting')<p>Deletion queued or needs attention</p>@endif</td>
            <td>@if($user->banned_at)<span>—</span>@elseif($user->activated_at)<span class="badge">Activated</span>@else<form method="POST" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="button small" type="submit">Activate User</button></form>@endif</td>
            <td>{{ $user->created_at->format('j M Y') }}</td>
            <td>@if(! $user->is_admin)
                @if(! $user->banned_at || ($user->customerBlog && $user->customerBlog->status !== 'deleting'))
                    <form method="POST" action="{{ route('admin.users.ban', $user) }}" x-on:submit="if (!confirm('Ban this user and permanently delete their blog? Their account will be kept.')) $event.preventDefault()">
                        @csrf
                        <button class="button small secondary" type="submit" aria-label="{{ $user->banned_at ? 'Retry blog deletion for ' : 'Ban ' }}{{ $user->name }}">{{ $user->banned_at ? 'Retry deletion' : 'Ban' }}</button>
                    </form>
                @endif
            @endif</td>
        </tr>
        <tr class="admin-user-signup"><td colspan="6">
            <div class="signup-summary">
                @foreach(['registration_ip' => 'Registration IP', 'previous_ip_registrations' => 'Previous signup count', 'referrer' => 'Referrer', 'email_domain' => 'Email domain'] as $field => $label)
                    <span><strong>{{ $label }}:</strong> {{ $user->signupAssessment?->$field ?? 'Not available' }}</span>
                @endforeach
            </div>
        </td></tr>
    @empty<tr><td colspan="6">No users yet.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $users->links() }}</div>
</section>
@endsection
