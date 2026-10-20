<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'commerce_product_reviews';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
}
