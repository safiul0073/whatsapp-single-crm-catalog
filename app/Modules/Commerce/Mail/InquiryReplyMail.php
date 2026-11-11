<?php

namespace App\Modules\Commerce\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $replySubject,
        public string $replyMessage,
        public string $ownerEmail,
        public string $senderName,
        public string $customerName,
        public ?string $originalMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            replyTo: [new Address($this->ownerEmail, $this->senderName)],
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'commerce::emails.inquiry-reply',
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
