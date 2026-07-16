<?php

namespace App\Modules\NotificationTemplates\Policies;

use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Contracts\Auth\Authenticatable;

class NotificationTemplatePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('notification-templates.view');
    }

    public function view(Authenticatable $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('notification-templates.view');
    }

    public function update(Authenticatable $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('notification-templates.edit');
    }
}
