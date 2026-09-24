@extends('layouts.site')
@section('title', 'Servers — blogshed.uk')
@section('content')
<section class="wrap admin-area">
    @include('admin.navigation')
    <div class="section-heading"><div><p class="eyebrow">YOUR HOSTING</p><h1>Servers</h1><p>{{ number_format($servers->total()) }} server records</p></div><a class="button" href="{{ route('admin.servers.create') }}">Add server +</a></div>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">Server</th><th scope="col">IP address</th><th scope="col">Location</th><th scope="col">Provider</th><th scope="col">Monthly cost (GBP)</th><th scope="col">Active</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($servers as $server)
        <tr><td><strong>{{ $server->name }}</strong></td><td>{{ $server->ip_address }}</td><td>{{ $server->location }}</td><td>{{ $server->provider }}</td><td>£{{ number_format((float) $server->monthly_cost, 2) }}</td><td>{{ $server->active ? 'Yes' : 'No' }}</td><td><div class="row-actions"><a class="text-link" href="{{ route('admin.servers.edit', $server) }}">Edit</a><form method="POST" action="{{ route('admin.servers.destroy', $server) }}" onsubmit="return confirm('Remove this server record? The server itself will not be affected.')">@csrf @method('DELETE')<button class="delete-link">Remove</button></form></div></td></tr>
    @empty<tr><td colspan="7"><div class="empty-state"><h3>No servers yet</h3><p>Add your first server to keep its details and monthly cost together.</p></div></td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $servers->links() }}</div>
</section>
@endsection
