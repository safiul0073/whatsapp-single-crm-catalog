<?php

namespace App\Modules\Workspaces\Models;

use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceRolePermission extends Model
{
    protected $fillable = [
        'workspace_id',
        'role',
        'permission_name',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceMemberRole::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
