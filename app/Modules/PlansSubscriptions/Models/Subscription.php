<?php

namespace App\Modules\PlansSubscriptions\Models;

use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'status',
        'starts_at',
        'renews_at',
        'ends_at',
        'cancelled_at',
        'cancel_at_period_end',
        'pending_plan_id',
        'last_payment_id',
        'usage',
        'usage_period_start',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'usage' => 'array',
            'usage_period_start' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function lastPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'last_payment_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest('id');
    }

    public function hasUsableStatus(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true);
    }
}
