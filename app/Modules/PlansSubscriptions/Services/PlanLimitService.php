<?php

namespace App\Modules\PlansSubscriptions\Services;

class PlanLimitService
{
    public const PLATFORM_AI_CREDITS_LIMIT = 'max_ai_credits';

    public function allows(int $workspaceId, string $limit, int $amount = 1): bool
    {
        return true;
    }

    public function consume(int $workspaceId, string $limit, int $amount = 1): void {}

    public function ensurePlatformAiCredits(int $workspaceId, int $amount = 1): void {}

    public function consumePlatformAiCredits(int $workspaceId, int $amount = 1): void
    {
        $this->consume($workspaceId, self::PLATFORM_AI_CREDITS_LIMIT, $amount);
    }

    public function featureEnabled(int $workspaceId, string $feature): bool
    {
        return true;
    }
}
