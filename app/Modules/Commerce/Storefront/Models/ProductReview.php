<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Commerce\Storefront\Services\ProductRatingService;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'commerce_product_reviews';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    protected static function booted(): void
    {
        $refresh = static function (ProductReview $review): void {
            app(ProductRatingService::class)->refresh((int) $review->workspace_id, (int) $review->product_id);
        };

        static::saved($refresh);
        static::deleted($refresh);
    }
}
