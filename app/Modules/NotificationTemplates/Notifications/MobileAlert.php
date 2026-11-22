<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Modules\Commerce\Storefront\Services\StorePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Push alert sent through the store's own Firebase project. Stores without
 * push configured are skipped.
 */
class MobileAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public string $title, public string $body, public array $data = [], public ?int $workspaceId = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $workspaceId = $this->workspaceId ?? $notifiable->workspace_id ?? null;

        return $workspaceId && app(StorePushService::class)->messaging((int) $workspaceId) ? [FcmChannel::class] : [];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $workspaceId = (int) ($this->workspaceId ?? $notifiable->workspace_id);

        return FcmMessage::create()
            ->notification(FcmNotification::create()->title($this->title)->body($this->body))
            ->data(array_map(fn ($value): string => (string) $value, [...$this->data, 'workspace_id' => $workspaceId]))
            ->usingClient(app(StorePushService::class)->messaging($workspaceId));
    }
}
