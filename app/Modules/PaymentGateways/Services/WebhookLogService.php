<?php

namespace App\Modules\PaymentGateways\Services;

use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\Shared\Traits\HasCrudOperations;

class WebhookLogService
{
    use HasCrudOperations;

    protected string $model = WebhookLog::class;

    /** @var array<string> */
    protected array $searchable = ['gateway', 'event_type', 'gateway_event_id'];

    /** @var array<string> */
    protected array $filterable = ['gateway'];

    protected string $defaultSortBy = 'created_at';

    protected string $defaultSortOrder = 'desc';
}
