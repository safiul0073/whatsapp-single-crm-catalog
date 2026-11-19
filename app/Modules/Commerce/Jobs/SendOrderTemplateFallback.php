<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Services\OrderWhatsAppTemplateFallbackService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderTemplateFallback implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $uniqueFor = 600;

    public function __construct(public int $messageId)
    {
        $this->onQueue('commerce-orders');
    }

    public function uniqueId(): string
    {
        return 'order-template-fallback:'.$this->messageId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(OrderWhatsAppTemplateFallbackService $fallback): void
    {
        $fallback->resend($this->messageId);
    }
}
