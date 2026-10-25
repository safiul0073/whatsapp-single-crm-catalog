<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OrderShipment extends Model
{
    protected $table = 'commerce_order_shipments';

    protected $fillable = ['order_id', 'carrier', 'tracking_number', 'tracking_url', 'proof_image', 'shipped_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function proofImageUrl(): ?string
    {
        return $this->proof_image ? Storage::disk('public')->url($this->proof_image) : null;
    }
}
