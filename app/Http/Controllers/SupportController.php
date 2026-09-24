<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportMessageRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = $request->user()->supportTickets()->with('latestReply.user')->withCount('replies')
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(10);

        return view('support.index', ['tickets' => $tickets, 'adminInbox' => false]);
    }

    public function adminIndex(Request $request): View
    {
        $tickets = SupportTicket::with(['user', 'latestReply.user'])->withCount('replies')
            ->where('status', $request->boolean('closed') ? '=' : '!=', 'Closed')
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('support.index', ['tickets' => $tickets, 'adminInbox' => true]);
    }

    public function create(): View
    {
        return view('support.create');
    }

    public function store(SupportMessageRequest $request): RedirectResponse
    {
        $ticket = $request->user()->supportTickets()->create([
            'title' => $request->validated('title'), 'body' => $request->sanitizedBody(),
        ]);

        return redirect()->route('support.show', $ticket)->with('status', 'Your support ticket has been created.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorizeTicket($request, $ticket);
        $ticket->load('user');

        return view('support.show', ['ticket' => $ticket, 'replies' => $ticket->replies()->with('user')->orderByDesc('id')->paginate(20)]);
    }

    public function reply(SupportMessageRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $body = $request->sanitizedBody();
        DB::transaction(function () use ($request, $ticket, $body): void {
            $locked = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $locked->replies()->create(['body' => $body, 'user_id' => $request->user()->id]);
            $status = $request->user()->can('manage-blog') && $locked->user_id !== $request->user()->id
                ? 'Awaiting Reply' : 'Open';
            $locked->forceFill(['status' => $status, 'updated_at' => now()])->save();
        });

        return redirect()->route('support.show', $ticket)->with('status', 'Reply added.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request, $ticket);
        $allowed = $request->user()->can('manage-blog') ? SupportTicket::STATUSES : ['Open', 'Closed'];
        $data = $request->validate(['status' => ['required', Rule::in($allowed)]]);
        $ticket->forceFill(['status' => $data['status'], 'updated_at' => now()])->save();

        return redirect()->route('support.show', $ticket)->with('status', 'Ticket status updated.');
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id || $request->user()->can('manage-blog'), 403);
    }
}
