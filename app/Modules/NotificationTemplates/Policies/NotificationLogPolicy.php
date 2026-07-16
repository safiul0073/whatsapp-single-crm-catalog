<?php

namespace App\Modules\NotificationTemplates\Policies;

use App\Modules\NotificationTemplates\Models\NotificationLog;
use Illuminate\Contracts\Auth\Authenticatable;

class NotificationLogPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('notification-logs.view');
    }

    public function view(Authenticatable $user, NotificationLog $notificationLog): bool
    {
        return $user->can('notification-logs.view');
    }
}
