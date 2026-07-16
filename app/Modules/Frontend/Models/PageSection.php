<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    protected $fillable = [
        'page_id',
        'frontend_section_id',
        'sort_order',
        'visibility_rules',
    ];

    protected function casts(): array
    {
        return [
            'visibility_rules' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FrontendSection::class, 'frontend_section_id');
    }
}
