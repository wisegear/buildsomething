@extends('layouts.site')
@section('title', $ticket->title.' — Support — blogshed.uk')
@section('content')
<section class="wrap admin-area support-area">
    @can('manage-blog') @include('admin.navigation') @endcan
    <a class="text-link" href="{{ auth()->user()->can('manage-blog') ? route('admin.support.index') : route('support.index') }}">← Support tickets</a>
    <h1>{{ $ticket->title }}</h1>
    <p>Opened by {{ $ticket->user->name }} · {{ $ticket->created_at->format('j M Y, H:i') }} · <span class="badge">{{ $ticket->status }}</span></p>
    <article class="panel"><div class="prose">{!! $ticket->body !!}</div></article>
    <form class="support-actions" method="POST" action="{{ route('support.status', $ticket) }}">
        @csrf @method('PATCH')
        @if($ticket->status === 'Closed')<button class="button small secondary" name="status" value="Open">Reopen Ticket</button>@else<button class="button small secondary" name="status" value="Closed">Close Ticket</button>@endif
        @can('manage-blog')<button class="button small secondary" name="status" value="In Progress">In Progress</button><button class="button small secondary" name="status" value="Awaiting Reply">Awaiting Reply</button>@endcan
    </form>
    @error('status')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <h2>Ticket replies</h2>
    @forelse($replies as $reply)
        <article class="panel"><p>{{ $reply->user?->name ?? 'Deleted user' }} @if($reply->user?->is_admin)<span class="badge">Support</span>@endif · {{ $reply->created_at->format('j M Y, H:i') }}</p><div class="prose">{!! $reply->body !!}</div></article>
    @empty<p>No replies yet.</p>@endforelse
    <div class="pagination">{{ $replies->links() }}</div>
    <form class="panel support-form" method="POST" action="{{ route('support.reply', $ticket) }}">
        @csrf
        @if($ticket->status === 'Closed')<p>Adding a reply will reopen this ticket.</p>@endif
        @include('support.message-field')
        <button class="button" type="submit">Add Reply</button>
    </form>
</section>
@endsection
