<?php

namespace App\Modules\MetaAds\Jobs;

use App\Modules\MetaAds\Models\MetaAd;
use App\Modules\MetaAds\Services\MetaAdBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Creates the ad in Meta (campaign → ad set → creative → ad), paused.
 */
class PublishMetaAdJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $metaAdId) {}

    public function handle(MetaAdBuilderService $builder): void
    {
        $ad = MetaAd::query()->find($this->metaAdId);

        if ($ad && $ad->status === MetaAd::STATUS_CREATING) {
            $builder->publish($ad);
        }
    }
}
