@extends('layouts.site')
@section('title', 'Support tickets — blogshed.uk')
@section('content')
<section class="wrap admin-area support-area">
    @if($adminInbox) @include('admin.navigation') @endif
    <div class="section-heading"><div><p class="eyebrow">HERE TO HELP</p><h1>Support tickets</h1><p>{{ $adminInbox ? 'Review and manage customer requests.' : 'Have a question? Open a ticket and keep the conversation here.' }}</p><p>No email notifications are sent. Check Support for replies.</p></div>
    @if(!$adminInbox)<a class="button small" href="{{ route('support.create') }}">Create new ticket</a>@endif</div>
    @if($adminInbox)<div class="support-actions"><a class="button small secondary" href="{{ route('admin.support.index') }}" @if(!request()->boolean('closed')) aria-current="page" @endif>Show open tickets</a><a class="button small secondary" href="{{ route('admin.support.index', ['closed' => 1]) }}" @if(request()->boolean('closed')) aria-current="page" @endif>Show closed tickets</a></div>@endif
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Ticket</th><th>{{ $adminInbox ? 'Opened by' : 'Opened' }}</th><th>Last response</th><th>Last replied by</th><th>Replies</th><th>Status</th></tr></thead><tbody>
        @forelse($tickets as $ticket)
        <tr><td><a class="text-link" href="{{ route('support.show', $ticket) }}">{{ $ticket->title }}</a></td><td>{{ $adminInbox ? $ticket->user->name : $ticket->created_at->diffForHumans() }}</td><td>{{ $ticket->latestReply?->created_at->diffForHumans() ?? '—' }}</td><td>{{ $ticket->latestReply ? ($ticket->latestReply->user?->name ?? 'Deleted user') : '—' }}</td><td>{{ $ticket->replies_count }}</td><td><span class="badge">{{ $ticket->status }}</span></td></tr>
        @empty<tr><td colspan="6">There are no support tickets to display.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $tickets->links() }}</div>
</section>
@endsection
