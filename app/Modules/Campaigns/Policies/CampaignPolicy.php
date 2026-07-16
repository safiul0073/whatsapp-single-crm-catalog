<?php

namespace App\Modules\Campaigns\Policies;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Workspaces\Models\Workspace;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function report(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function manage(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    protected function owns(User $user, Campaign $campaign): bool
    {
        return Workspace::query()
            ->whereKey($campaign->workspace_id)
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                        ->where('users.id', $user->id)
                        ->where('workspace_members.status', 'active'));
            })
            ->exists();
    }
}
