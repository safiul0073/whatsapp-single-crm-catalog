<?php

namespace App\Modules\PaymentGateways\Policies;

use App\Modules\PaymentGateways\Models\WebhookLog;
use Illuminate\Contracts\Auth\Authenticatable;

class WebhookLogPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('webhook-logs.view');
    }

    public function view(Authenticatable $user, WebhookLog $webhookLog): bool
    {
        return $user->can('webhook-logs.view');
    }
}
