<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Jobs\NotifyCustomerAlert;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Sends refund and withdrawal updates once the surrounding transaction has committed.
 */
class ReferralNotifier
{
    public function __construct(private SystemNotificationService $notifications) {}

    /**
     * @param  array<string, string>  $data
     */
    public function customer(int $workspace, ?int $customer, string $title, string $body, array $data = []): void
    {
        if (! $customer) {
            return;
        }
        DB::afterCommit(fn () => rescue(fn () => NotifyCustomerAlert::dispatch($workspace, $customer, $title, $body, $data), report: true));
    }

    public function owner(int $workspace, string $title, string $body, string $url): void
    {
        DB::afterCommit(fn () => rescue(function () use ($workspace, $title, $body, $url): void {
            $owner = Workspace::query()->find($workspace)?->owner;
            if ($owner) {
                $this->notifications->send($owner, ['title' => $title, 'body' => $body, 'url' => $url], 'commerce');
            }
        }, report: true));
    }
}
