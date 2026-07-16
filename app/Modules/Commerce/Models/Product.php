<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'commerce_products';

    protected $fillable = ['workspace_id', 'category_id', 'brand_id', 'audience_id', 'primary_media_id', 'name', 'slug', 'brand', 'description', 'care_information', 'condition', 'audience', 'country_of_origin', 'status', 'wizard_step', 'published_at'];

    protected function casts(): array
    {
        return ['wizard_step' => 'integer', 'published_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brandRecord(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function audienceRecord(): BelongsTo
    {
        return $this->belongsTo(Audience::class, 'audience_id');
    }

    public function primaryMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'primary_media_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->with('media')->orderBy('position');
    }
}
