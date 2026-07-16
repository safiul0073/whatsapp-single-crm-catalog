<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Models\CampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedCampaignRecipientJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $recipientIds) {}

    public function handle(): void
    {
        CampaignRecipient::query()
            ->whereIn('id', $this->recipientIds)
            ->where('status', CampaignRecipientStatus::Failed->value)
            ->chunkById(500, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    $recipient->update([
                        'status' => CampaignRecipientStatus::Queued->value,
                        'error_code' => null,
                        'error_message' => null,
                        'failed_at' => null,
                        'queued_at' => now(),
                    ]);

                    SendCampaignRecipientJob::dispatch($recipient->id);
                }
            });
    }
}
