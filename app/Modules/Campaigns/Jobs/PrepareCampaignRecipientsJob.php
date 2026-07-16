<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareCampaignRecipientsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(CampaignService $service): void
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if (! $campaign) {
            return;
        }

        $service->prepareRecipients($campaign);
    }
}
