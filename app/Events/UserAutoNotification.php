<?php

namespace App\Events;

use App\Enums\NotificationTemplateSlug;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAutoNotification
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public NotificationTemplateSlug $templateSlug,
    ) {}
}
