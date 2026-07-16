<?php

namespace App\Modules\SupportTickets\Tests\Feature;

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\Admin;
use App\Models\User;
use App\Modules\SupportTickets\Mail\TicketCreatedMail;
use App\Modules\SupportTickets\Mail\TicketRepliedMail;
use App\Modules\SupportTickets\Models\SupportTicket;
use App\Modules\SupportTickets\Models\SupportTicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->withoutMiddleware([
            EnsureTwoFactorAuthenticated::class,
            EnsureOnboardingComplete::class,
        ]);
    }

    // ── User side ────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_the_tickets_index(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('user.support-tickets.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_view_the_create_ticket_form(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('user.support-tickets.create'))
            ->assertOk();
    }

    public function test_authenticated_user_can_submit_a_new_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.store'), [
                'subject' => 'My first ticket',
                'message' => 'This is the ticket message body.',
                'priority' => 'medium',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::where('user_id', $user->id)->first();

        $this->assertNotNull($ticket);
        $this->assertSame('My first ticket', $ticket->subject);
        $this->assertSame('medium', $ticket->priority);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, SupportTicketReply::where('ticket_id', $ticket->id)->count());

        Mail::assertQueued(TicketCreatedMail::class);
    }

    public function test_validates_required_fields_when_submitting_a_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.store'), [])
            ->assertSessionHasErrors(['subject', 'message', 'priority']);
    }

    public function test_authenticated_user_can_view_their_own_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Test ticket',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->get(route('user.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Test ticket');
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'subject' => 'Private ticket',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($other)
            ->get(route('user.support-tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_authenticated_user_can_reply_to_their_own_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Ticket to reply to',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'This is my follow-up reply.',
            ])
            ->assertRedirect();

        $this->assertSame(1, SupportTicketReply::where('ticket_id', $ticket->id)->count());

        Mail::assertQueued(TicketRepliedMail::class);
    }

    public function test_user_cannot_reply_to_another_users_ticket(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'subject' => 'Private ticket',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($other)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'Trying to reply to someone else\'s ticket.',
            ])
            ->assertForbidden();
    }

    public function test_replying_to_a_resolved_ticket_reopens_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Resolved ticket',
            'status' => 'resolved',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'Actually I still need help.',
            ]);

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_replying_to_a_closed_ticket_reopens_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Closed ticket',
            'status' => 'closed',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'I need this reopened.',
            ]);

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_user_can_filter_tickets_by_status(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        SupportTicket::create(['user_id' => $user->id, 'subject' => 'Open one', 'status' => 'open', 'priority' => 'low']);
        SupportTicket::create(['user_id' => $user->id, 'subject' => 'Closed one', 'status' => 'closed', 'priority' => 'low']);

        $this->actingAs($user)
            ->get(route('user.support-tickets.index', ['status' => 'open']))
            ->assertOk()
            ->assertSee('Open one')
            ->assertDontSee('Closed one');
    }

    // ── Admin side ───────────────────────────────────────────────────────────

    public function test_admin_can_view_all_tickets(): void
    {
        $admin = Admin::factory()->create();

        SupportTicket::create([
            'user_id' => User::factory()->create()->id,
            'subject' => 'Admin visible ticket',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.index'))
            ->assertOk()
            ->assertSee('Admin visible ticket');
    }

    public function test_admin_can_view_a_single_ticket(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Detailed ticket',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Detailed ticket');
    }

    public function test_admin_can_reply_to_a_ticket(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Needs admin reply',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.reply', $ticket), [
                'message' => 'Hello, we are looking into this.',
            ])
            ->assertRedirect();

        $reply = SupportTicketReply::where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($reply);
        $this->assertTrue($reply->is_staff);
        $this->assertSame('in_progress', $ticket->fresh()->status);

        Mail::assertQueued(TicketRepliedMail::class);
    }

    public function test_admin_can_update_ticket_status(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Status test ticket',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.update-status', $ticket), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_admin_can_delete_a_ticket(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'To be deleted',
            'status' => 'closed',
            'priority' => 'low',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.support-tickets.destroy', $ticket))
            ->assertRedirect(route('admin.support-tickets.index'));

        $this->assertNull(SupportTicket::find($ticket->id));
    }

    public function test_admin_reply_is_attributed_to_the_admin(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Needs admin reply',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.reply', $ticket), [
                'message' => 'Hello, we are looking into this.',
            ])
            ->assertRedirect();

        $reply = SupportTicketReply::where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($reply);
        $this->assertTrue($reply->is_staff);
        $this->assertNull($reply->user_id);
        $this->assertSame($admin->id, $reply->admin_id);
    }

    public function test_user_can_reply_via_ajax_endpoint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Ajax reply test',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('user.support-tickets.reply-ajax', $ticket), [
                'message' => 'This is an AJAX reply.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'open');

        $this->assertSame(1, SupportTicketReply::where('ticket_id', $ticket->id)->count());
        $this->assertStringContainsString('This is an AJAX reply.', $response->json('html'));
    }

    public function test_admin_can_reply_via_ajax_endpoint(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Ajax admin reply test',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.support-tickets.reply-ajax', $ticket), [
                'message' => 'Staff AJAX reply.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'in_progress');

        $this->assertStringContainsString('Staff AJAX reply.', $response->json('html'));
    }

    public function test_poll_endpoint_returns_new_replies(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Poll test',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $firstReply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'First message',
            'is_staff' => false,
        ]);

        $secondReply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Second message',
            'is_staff' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('user.support-tickets.poll', $ticket, ['after' => $firstReply->id]))
            ->assertOk()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Second message'));
    }

    public function test_user_can_create_ticket_with_attachments(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.store'), [
                'subject' => 'Ticket with attachment',
                'message' => 'Please see attached.',
                'priority' => 'medium',
                'attachments' => [
                    UploadedFile::fake()->image('screenshot.png'),
                ],
            ])
            ->assertRedirect();

        $ticket = SupportTicket::where('user_id', $user->id)->first();

        $this->assertNotNull($ticket);
        $this->assertSame(1, $ticket->attachments()->count());
        $this->assertSame('screenshot.png', $ticket->attachments()->first()->original_name);
    }

    public function test_user_can_reply_with_attachments(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Reply with attachment',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'See attached.',
                'attachments' => [
                    UploadedFile::fake()->create('document.pdf', 100),
                ],
            ])
            ->assertRedirect();

        $reply = SupportTicketReply::where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($reply);
        $this->assertSame(1, $reply->attachments()->count());
    }

    public function test_user_can_search_tickets_by_subject(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        SupportTicket::create(['user_id' => $user->id, 'subject' => 'Billing issue', 'status' => 'open', 'priority' => 'low']);
        SupportTicket::create(['user_id' => $user->id, 'subject' => 'Technical problem', 'status' => 'open', 'priority' => 'low']);

        $this->actingAs($user)
            ->get(route('user.support-tickets.index', ['search' => 'Billing']))
            ->assertOk()
            ->assertSee('Billing issue')
            ->assertDontSee('Technical problem');
    }

    public function test_user_can_reopen_closed_ticket_with_reply(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Closed ticket',
            'status' => 'closed',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->post(route('user.support-tickets.reply', $ticket), [
                'message' => 'Please reopen this.',
            ])
            ->assertRedirect();

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame(1, SupportTicketReply::where('ticket_id', $ticket->id)->count());
    }

    public function test_urgent_priority_is_displayed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Urgent issue',
            'status' => 'open',
            'priority' => 'urgent',
        ]);

        $this->actingAs($user)
            ->get(route('user.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Urgent');
    }
}
