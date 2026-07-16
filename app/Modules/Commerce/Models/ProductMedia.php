<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    protected $table = 'commerce_product_media';

    protected $fillable = ['workspace_id', 'product_id', 'media_id', 'media_type', 'role', 'alt_text', 'position', 'is_primary'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_primary' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
