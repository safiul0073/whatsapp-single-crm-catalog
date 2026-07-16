<?php

namespace App\Modules\SupportTickets\Mail;

use App\Modules\SupportTickets\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketRepliedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Authenticatable $user,
        public SupportTicket $ticket,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Reply on Ticket '.$this->ticket->formatted_id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'support-tickets::emails.ticket-replied',
            with: [
                'ticketUrl' => route('user.support-tickets.show', $this->ticket),
            ],
        );
    }
}
