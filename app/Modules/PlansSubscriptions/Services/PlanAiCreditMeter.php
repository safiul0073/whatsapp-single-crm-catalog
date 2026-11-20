<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\Shared\Contracts\AiCreditMeter;

class PlanAiCreditMeter implements AiCreditMeter
{
    public function __construct(protected PlanLimitService $limits) {}

    public function ensure(int $workspaceId, int $amount = 1): void
    {
        $this->limits->ensurePlatformAiCredits($workspaceId, $amount);
    }

    public function consume(int $workspaceId, int $amount = 1): void
    {
        $this->limits->consumePlatformAiCredits($workspaceId, $amount);
    }
}
