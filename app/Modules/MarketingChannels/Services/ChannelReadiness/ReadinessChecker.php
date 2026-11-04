<?php

namespace App\Modules\MarketingChannels\Services\ChannelReadiness;

use App\Modules\MarketingChannels\Models\ChannelAccount;

/**
 * Checks, with live data from the provider, what a connected channel still needs before it can
 * receive, send and run campaigns. Each channel module registers its own checker.
 */
interface ReadinessChecker
{
    public function provider(): string;

    /**
     * @return array{items: array<int, array<string, mixed>>, facts?: array<int, array{label: string, value: string}>, notes?: array<int, string>}
     */
    public function check(ChannelAccount $account): array;
}
