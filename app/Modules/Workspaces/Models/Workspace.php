<?php

namespace App\Modules\Workspaces\Models;

use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Workspace extends Model
{
    protected $fillable = ['owner_id', 'name', 'slug', 'status', 'timezone', 'settings'];

    protected function casts(): array
    {
        return [
            'status' => WorkspaceStatus::class,
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps()
            ->using(WorkspaceMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', WorkspaceMemberStatus::Active->value);
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasServices(): bool
    {
        $serviceTables = [
            'subscriptions',
            'channel_accounts',
            'contacts',
            'campaigns',
            'conversations',
            'automations',
            'chatbots',
            'message_templates',
            'knowledge_bases',
            'segments',
            'leads',
            'crm_leads',
            'auto_reply_rules',
        ];

        foreach ($serviceTables as $table) {
            if (DB::table($table)->where('workspace_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function canDelete(): bool
    {
        return ! $this->hasServices();
    }
}
