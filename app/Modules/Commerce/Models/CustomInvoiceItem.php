<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomInvoiceItem extends Model
{
    protected $table = 'commerce_custom_invoice_items';

    protected $fillable = ['custom_invoice_id', 'product_id', 'variant_id', 'name', 'description', 'quantity', 'unit_price', 'line_total', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:4', 'position' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomInvoice::class, 'custom_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
