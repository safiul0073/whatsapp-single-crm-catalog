<?php

namespace App\Modules\Commerce\Mail;

use App\Modules\Commerce\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $productNames
     */
    public function __construct(public Order $order, public string $reviewUrl, public array $productNames) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "How was your order #{$this->order->number}?");
    }

    public function content(): Content
    {
        return new Content(view: 'commerce::emails.order-review-request');
    }
}
