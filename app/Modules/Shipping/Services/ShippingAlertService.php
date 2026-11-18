<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Collection;

/**
 * Sends shipping alerts to the store owner and to active team members whose role holds a given permission.
 */
class ShippingAlertService
{
    public function __construct(private SystemNotificationService $notifications) {}

    public function notify(Shipment $shipment, string $title, string $body, ?string $permission = null): int
    {
        $recipients = $this->recipients($shipment->workspace_id, $permission);
        foreach ($recipients as $user) {
            rescue(fn () => $this->notifications->send($user, [
                'title' => $title,
                'body' => mb_substr($body, 0, 500),
                'url' => route('user.shipping.shipments.show', $shipment),
            ], 'shipping'), report: true);
        }

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(int $workspaceId, ?string $permission = null): Collection
    {
        $owner = Workspace::query()->find($workspaceId)?->owner;
        $members = collect();
        if ($permission !== null) {
            $userIds = WorkspaceMember::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', WorkspaceMemberStatus::Active->value)
                ->whereHas('workspaceRole.rolePermissions', fn ($permissions) => $permissions->where('permission_name', $permission))
                ->pluck('user_id');
            $members = User::query()->whereIn('id', $userIds)->get();
        }

        return collect([$owner])->filter()->merge($members)->unique('id')->values();
    }
}
