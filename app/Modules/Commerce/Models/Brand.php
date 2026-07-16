<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $table = 'commerce_brands';

    protected $fillable = ['workspace_id', 'name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
