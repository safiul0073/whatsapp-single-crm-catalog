<?php

namespace App\Modules\PlansSubscriptions\Models;

use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SubscriptionEvent extends Model
{
    protected $fillable = ['subscription_id', 'type', 'from_plan_id', 'to_plan_id', 'payment_id', 'actor_type', 'actor_id', 'meta'];

    protected function casts(): array
    {
        return ['type' => SubscriptionEventType::class, 'meta' => 'array'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
