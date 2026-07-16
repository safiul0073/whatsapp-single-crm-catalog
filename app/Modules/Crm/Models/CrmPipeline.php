<?php

namespace App\Modules\Crm\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipeline extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'name', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class, 'pipeline_id')->orderBy('position');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'pipeline_id');
    }
}
