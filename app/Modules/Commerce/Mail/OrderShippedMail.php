<?php

namespace App\Modules\Commerce\Mail;

use App\Modules\Commerce\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your order #{$this->order->number} has shipped!",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'commerce::emails.order-shipped',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
