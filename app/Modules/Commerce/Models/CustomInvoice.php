<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Storefront\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A standalone invoice an owner builds by hand, optionally answering a customer's request. */
class CustomInvoice extends Model
{
    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::DRAFT, self::SENT, self::PAID, self::CANCELLED];

    protected $table = 'commerce_custom_invoices';

    protected $fillable = [
        'workspace_id', 'customer_id', 'invoice_request_id', 'sequence', 'number', 'token', 'status',
        'bill_to_name', 'bill_to_phone', 'bill_to_email', 'bill_to_address', 'currency',
        'subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total',
        'notes', 'due_date', 'issued_at', 'sent_at', 'paid_at', 'cancelled_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4', 'discount_amount' => 'decimal:4', 'tax_amount' => 'decimal:4',
            'shipping_amount' => 'decimal:4', 'total' => 'decimal:4', 'due_date' => 'date',
            'issued_at' => 'datetime', 'sent_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomInvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InvoiceRequest::class, 'invoice_request_id');
    }

    /**
     * Invoices a customer may see: anything the owner has issued.
     */
    public function scopeVisibleToCustomer(Builder $query): Builder
    {
        return $query->where('status', '!=', self::DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function moneyPrecision(): int
    {
        return (new StoreOrderSetting(['currency' => $this->currency]))->precision();
    }

    public function publicUrl(bool $download = false): string
    {
        $url = rtrim((string) config('app.url'), '/').'/custom-invoices/'.$this->token;

        return $download ? $url.'?download=1' : $url;
    }
}
