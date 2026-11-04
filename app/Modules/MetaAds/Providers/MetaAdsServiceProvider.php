<?php

namespace App\Modules\MetaAds\Providers;

use App\Modules\Inbox\Models\Message;
use App\Modules\MetaAds\Listeners\RecordAdReferral;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class MetaAdsServiceProvider extends BasePanelModuleProvider
{
    protected function bootModule(array $module): void
    {
        Message::created(fn (Message $message) => app(RecordAdReferral::class)->handle($message));
    }
}
