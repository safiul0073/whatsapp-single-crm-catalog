<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogSyncRun extends Model
{
    protected $table = 'commerce_catalog_sync_runs';

    protected $fillable = ['workspace_id', 'catalog_id', 'mode', 'status', 'total_items', 'successful_items', 'failed_items', 'summary', 'last_error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['total_items' => 'integer', 'successful_items' => 'integer', 'failed_items' => 'integer', 'summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}
