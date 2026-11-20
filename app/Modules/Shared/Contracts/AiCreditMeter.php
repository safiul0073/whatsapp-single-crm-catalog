<?php

namespace App\Modules\Shared\Contracts;

/**
 * Charges platform AI usage to a workspace. Bound by the plans module; when it
 * is not bound, platform AI calls are not metered.
 */
interface AiCreditMeter
{
    /**
     * Throw when the workspace cannot spend this many credits.
     */
    public function ensure(int $workspaceId, int $amount = 1): void;

    public function consume(int $workspaceId, int $amount = 1): void;
}
