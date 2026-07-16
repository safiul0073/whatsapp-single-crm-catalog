<?php

namespace App\Modules\AuthApi\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $otp,
        protected string $purpose = 'login verification',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification code')
            ->line("Use this code to complete your {$this->purpose}.")
            ->line($this->otp)
            ->line('This code will expire in 5 minutes.');
    }
}
