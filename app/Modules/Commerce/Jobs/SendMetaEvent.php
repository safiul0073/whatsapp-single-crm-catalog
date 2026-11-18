<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Delivers one browser-paired Conversions API event (PageView, ViewContent, AddToCart, InitiateCheckout).
 */
class SendMetaEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public int $workspaceId, public array $event) {}

    public function uniqueId(): string
    {
        return $this->workspaceId.':'.($this->event['event_id'] ?? '');
    }

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(MetaConversionsService $conversions): void
    {
        match ($conversions->send($this->workspaceId, [$this->event])) {
            MetaConversionsService::RATE_LIMITED => $this->release(120),
            MetaConversionsService::RETRY => throw new RuntimeException('Meta Conversions API is unavailable; retrying.'),
            default => null,
        };
    }
}
