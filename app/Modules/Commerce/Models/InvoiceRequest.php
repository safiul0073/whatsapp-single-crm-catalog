<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Storefront\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A customer's ask for a custom invoice, answered by the owner with a CustomInvoice or a rejection. */
class InvoiceRequest extends Model
{
    public const PENDING = 'pending';

    public const INVOICED = 'invoiced';

    public const REJECTED = 'rejected';

    protected $table = 'commerce_invoice_requests';

    protected $fillable = ['workspace_id', 'customer_id', 'message', 'items', 'status', 'owner_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['items' => 'array', 'resolved_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(CustomInvoice::class, 'invoice_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
