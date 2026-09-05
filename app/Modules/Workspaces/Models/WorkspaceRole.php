<?php

namespace App\Modules\Workspaces\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceRole extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(WorkspaceRolePermission::class, 'workspace_role_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_role_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class, 'workspace_role_id');
    }
}
