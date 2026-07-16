<?php

namespace App\Modules\Automations\Jobs;

use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Automations\Services\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomationStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $automationId,
        public array $context = [],
        public ?int $runId = null,
        public ?string $nodeId = null,
    ) {}

    public function handle(AutomationDispatcher $dispatcher, AutomationRunner $runner): void
    {
        if ($this->runId && $this->nodeId) {
            $runner->execute($this->automationId, $this->runId, $this->nodeId, $this->context);

            return;
        }

        $automation = Automation::query()->find($this->automationId);

        if (! $automation) {
            return;
        }

        $dispatcher->startAutomation($automation, $this->context);
    }
}
