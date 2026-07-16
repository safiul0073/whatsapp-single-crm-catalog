<?php

namespace App\Modules\Workspaces\Models;

use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceMember extends Pivot
{
    use HasFactory;

    protected $table = 'workspace_members';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceMemberRole::class,
            'status' => WorkspaceMemberStatus::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
