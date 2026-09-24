<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(User $user, string $title = 'Help with my blog'): SupportTicket
    {
        return $user->supportTickets()->create(['title' => $title, 'body' => '<p>My question</p>']);
    }

    public function test_unactivated_customer_can_create_view_and_reply_to_a_ticket(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/support/create')->assertOk()->assertSee('data-support-editor', false);
        $this->post('/support', ['title' => 'Activation question', 'body' => '<p>Please <strong>help</strong></p>', 'user_id' => 999, 'status' => 'Closed'])->assertSessionHasNoErrors();
        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('Open', $ticket->status);
        $this->get('/support')->assertOk()->assertSee('Activation question');
        $this->get('/support/'.$ticket->id)->assertOk()->assertSee('<strong>help</strong>', false);
        $this->post('/support/'.$ticket->id.'/replies', ['body' => '<p>More details</p>', 'user_id' => 999])->assertSessionHasNoErrors();
        $this->assertSame($user->id, $ticket->replies()->firstOrFail()->user_id);
        $this->get('/support')->assertOk()->assertSee($user->name);
        $this->get('/support/'.$ticket->id)->assertSee('More details');
    }

    public function test_tickets_are_private_and_guests_are_redirected(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticket($owner, 'Private ticket title');
        $this->get('/support')->assertRedirect('/login');
        $this->get('/support/'.$ticket->id)->assertRedirect('/login');
        $this->post('/support', [])->assertRedirect('/login');
        $this->post('/support/'.$ticket->id.'/replies', [])->assertRedirect('/login');
        $this->patch('/support/'.$ticket->id.'/status', ['status' => 'Closed'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/support')->assertDontSee('Private ticket title');
        $this->get('/support/'.$ticket->id)->assertForbidden();
        $this->post('/support/'.$ticket->id.'/replies', ['body' => 'Intrusion'])->assertForbidden();
        $this->patch('/support/'.$ticket->id.'/status', ['status' => 'Closed'])->assertForbidden();
        $this->get('/admin/support')->assertForbidden();
        $this->assertSame('Open', $ticket->fresh()->status);
        $this->assertSame(0, $ticket->replies()->count());
    }

    public function test_owner_can_close_and_reopen_but_cannot_set_admin_statuses(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $this->actingAs($user)->patch('/support/'.$ticket->id.'/status', ['status' => 'In Progress'])->assertSessionHasErrors('status');
        $this->patch('/support/'.$ticket->id.'/status', ['status' => 'Awaiting Reply'])->assertSessionHasErrors('status');
        foreach (['Closed', 'Open', 'Closed'] as $status) {
            $this->patch('/support/'.$ticket->id.'/status', ['status' => $status])->assertRedirect();
            $this->assertSame($status, $ticket->fresh()->status);
        }
        $this->post('/support/'.$ticket->id.'/replies', ['body' => 'Another question'])->assertRedirect();
        $this->assertSame('Open', $ticket->fresh()->status);
    }

    public function test_admin_can_reply_manage_status_and_filter_the_inbox(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticket($owner, 'Current question');
        $closed = $this->ticket($owner, 'Resolved question');
        $closed->forceFill(['status' => 'Closed'])->save();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/support')->assertOk()->assertSee('Current question')->assertDontSee('Resolved question');
        $this->get('/admin/support?closed=1')->assertSee('Resolved question')->assertDontSee('Current question');
        $this->get('/support/'.$ticket->id)->assertOk()->assertSee('In Progress');
        $this->post('/support/'.$ticket->id.'/replies', ['body' => '<p>We can help</p>'])->assertRedirect();
        $this->assertSame($admin->id, $ticket->replies()->firstOrFail()->user_id);
        $this->assertSame('Awaiting Reply', $ticket->fresh()->status);
        $this->actingAs($owner)->get('/account')->assertSee('1 tickets awaiting your reply');
        $this->actingAs($admin);
        foreach (['In Progress', 'Awaiting Reply'] as $status) {
            $this->patch('/support/'.$ticket->id.'/status', ['status' => $status])->assertRedirect();
            $this->assertSame($status, $ticket->fresh()->status);
        }
        $this->actingAs($owner)->get('/account')->assertSee('1 tickets awaiting your reply');
        $this->post('/support/'.$ticket->id.'/replies', ['body' => 'Thank you'])->assertRedirect();
        $this->get('/account')->assertDontSee('1 tickets awaiting your reply');
    }

    public function test_messages_are_validated_and_dangerous_html_is_removed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/support', ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
        $this->post('/support', ['title' => str_repeat('a', 101), 'body' => str_repeat('a', 20001)])->assertSessionHasErrors(['title', 'body']);
        $this->post('/support', ['title' => 'Empty markup', 'body' => '<p>&nbsp;</p>'])->assertSessionHasErrors('body');
        $this->post('/support', ['title' => '<script>title</script>', 'body' => '<p onclick="evil()">Hello <strong>world</strong><script>alert(1)</script><a href="javascript:evil()">link</a><img src=x onerror="evil()"></p>'])->assertSessionHasNoErrors();
        $ticket = SupportTicket::firstOrFail();
        $this->assertStringNotContainsString('<script', $ticket->body);
        $this->assertStringNotContainsString('onclick', $ticket->body);
        $this->assertStringNotContainsString('javascript:', $ticket->body);
        $this->assertStringNotContainsString('<img', $ticket->body);
        $this->get('/support/'.$ticket->id)->assertDontSee('<script>title</script>', false)->assertSee('<strong>world</strong>', false);
        $this->post('/support/'.$ticket->id.'/replies', ['body' => '<p></p>'])->assertSessionHasErrors('body');
        $this->assertSame(0, $ticket->replies()->count());
    }

    public function test_closed_inbox_pagination_preserves_filter(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        for ($i = 0; $i < 16; $i++) {
            $this->ticket($admin, 'Closed '.$i)->forceFill(['status' => 'Closed'])->save();
        }
        $this->actingAs($admin)->get('/admin/support?closed=1')->assertOk()->assertSee('closed=1', false)
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 16 && $tickets->count() === 15);
        $this->get('/admin/support?closed=1&page=2')->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 1);
    }
}
