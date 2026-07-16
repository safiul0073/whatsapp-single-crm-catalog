<?php

namespace App\Modules\NotificationTemplates\Listeners;

use App\Events\UserAutoNotification;
use App\Modules\NotificationTemplates\Notifications\SendAutoNotification;
use Illuminate\Support\Facades\Log;

class UserAutoNotificationListener
{
    public function handle(UserAutoNotification $event): void
    {
        $user = $event->user;
        $templateSlug = $event->templateSlug;
        $template = $templateSlug->template();

        if ($template) {
            $user->notify(new SendAutoNotification($templateSlug));
        }

        Log::info('User auto notification', [
            'user_id' => $user->id,
            'template_slug' => $templateSlug->value,
            'template_id' => $template?->id,
            'status' => $template ? 'sent' : 'not_found',
        ]);
    }
}
