<?php

namespace App\Modules\WhatsAppCloud\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWhatsAppTemplatesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $workspaceId) {}

    public function handle(): void {}
}
