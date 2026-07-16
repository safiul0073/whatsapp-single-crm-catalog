<?php

namespace App\Modules\WhatsAppCloud\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $account, public array $payload, public array $headers = []) {}

    public function handle(): void
    {
        Log::info('WhatsApp webhook received', ['account' => $this->account, 'object' => $this->payload['object'] ?? null]);
    }
}
