<?php

namespace App\Modules\Crm\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'pipeline_id', 'name', 'position', 'color'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'stage_id');
    }
}
