<?php

namespace App\Modules\PlansSubscriptions\Models;

use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['workspace_id', 'plan_id', 'status', 'starts_at', 'renews_at', 'ends_at', 'usage'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'starts_at' => 'datetime', 'renews_at' => 'datetime', 'ends_at' => 'datetime', 'usage' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
