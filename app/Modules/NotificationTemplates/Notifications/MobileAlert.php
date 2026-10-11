<?php

namespace App\Modules\NotificationTemplates\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class MobileAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body, public array $data = []) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return FcmMessage::create()
            ->notification(FcmNotification::create()->title($this->title)->body($this->body))
            ->data(array_map(fn ($value): string => (string) $value, $this->data));
    }
}
