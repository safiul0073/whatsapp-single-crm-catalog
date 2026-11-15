<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only history of a refund request. Rows are never changed or removed.
 */
class RefundRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'commerce_refund_request_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund history is immutable.'));
        static::deleting(fn () => throw new LogicException('Refund history is immutable.'));
    }
}
