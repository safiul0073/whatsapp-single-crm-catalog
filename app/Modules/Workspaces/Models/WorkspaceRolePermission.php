<?php

namespace App\Modules\Workspaces\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceRolePermission extends Model
{
    protected $fillable = [
        'workspace_id',
        'workspace_role_id',
        'permission_name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workspaceRole(): BelongsTo
    {
        return $this->belongsTo(WorkspaceRole::class, 'workspace_role_id');
    }
}
