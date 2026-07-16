<?php

use App\Mail\GenericNotificationMail;
use App\Models\User;
use App\Modules\SupportTickets\Mail\TicketCreatedMail;
use App\Modules\SupportTickets\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('registers ticket ownership policies for viewing and replying', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $ticket = SupportTicket::query()->create([
        'user_id' => $owner->id,
        'subject' => 'Billing issue',
        'status' => 'open',
        'priority' => 'medium',
    ]);

    expect(Gate::forUser($owner)->allows('view', $ticket))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('reply', $ticket))->toBeTrue()
        ->and(Gate::forUser($otherUser)->allows('view', $ticket))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('reply', $ticket))->toBeFalse();
});

it('formats ticket identifiers through the shared accessor', function () {
    $ticket = SupportTicket::query()->create([
        'user_id' => User::factory()->create()->id,
        'subject' => 'Need help',
        'status' => 'open',
        'priority' => 'low',
    ]);

    $expectedFormattedId = '#'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);

    expect($ticket->formatted_id)->toBe($expectedFormattedId)
        ->and((new TicketCreatedMail($ticket->user, $ticket))->envelope()->subject)
        ->toBe('Support Ticket '.$expectedFormattedId.' Opened');
});

it('queues generic notification mailables', function () {
    expect(new GenericNotificationMail(
        title: 'Subject',
        body: 'Body copy',
    ))->toBeInstanceOf(ShouldQueue::class);
});
