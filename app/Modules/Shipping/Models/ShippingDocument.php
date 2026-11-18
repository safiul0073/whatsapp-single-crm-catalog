<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingDocument extends Model
{
    public const TYPES = [
        'label' => 'Shipping label',
        'commercial_invoice' => 'Commercial invoice',
        'customs' => 'Customs document',
        'other' => 'Other',
    ];

    protected $table = 'shipping_documents';

    protected $fillable = [
        'workspace_id',
        'shipping_shipment_id',
        'type',
        'file_path',
        'original_name',
        'mime',
        'size',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
