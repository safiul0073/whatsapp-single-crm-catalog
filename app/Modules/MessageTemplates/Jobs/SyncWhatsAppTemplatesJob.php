<?php

namespace App\Modules\MessageTemplates\Jobs;

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps template statuses correct when Meta's status webhook is not subscribed or a delivery was missed.
 */
class SyncWhatsAppTemplatesJob implements ShouldQueue
{
    use Queueable;

    public function handle(ChannelManager $channels): void
    {
        ChannelAccount::query()
            ->where('provider', 'whatsapp')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->whereNotNull('provider_account_id')
            ->orderBy('id')
            ->get()
            ->unique(fn (ChannelAccount $account): string => $account->workspace_id.':'.$account->provider_account_id)
            ->each(function (ChannelAccount $account) use ($channels): void {
                try {
                    $channels->syncTemplates($account);
                } catch (Throwable $exception) {
                    Log::warning('Scheduled WhatsApp template sync failed.', ['account' => $account->id, 'error' => $exception->getMessage()]);
                }
            });
    }
}
