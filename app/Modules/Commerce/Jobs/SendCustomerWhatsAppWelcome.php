<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerWhatsAppWelcome implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public string $registrationId)
    {
        $this->onQueue('commerce-orders');
    }

    public function uniqueId(): string
    {
        return $this->registrationId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WhatsAppCustomerAuthService $auth): void
    {
        $auth->sendWelcome($this->registrationId);
    }
}
