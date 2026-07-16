<?php

namespace App\Modules\SchedulerQueue\Services;

use App\Modules\SchedulerQueue\Models\SchedulerEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ManagedSchedulerService
{
    public function __construct(protected SchedulerRegistry $registry) {}

    public function syncRegisteredEntries(): void
    {
        foreach ($this->registry->entries() as $key => $definition) {
            $entry = SchedulerEntry::query()->firstOrNew(['key' => $key]);

            if (! $entry->exists) {
                $entry->fill([
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'target' => $definition['target'],
                    'frequency' => $definition['frequency'],
                    'queue' => $definition['queue'],
                    'enabled' => $definition['enabled'],
                    'options' => $definition['options'] ?? [],
                ])->save();

                continue;
            }

            $entry->forceFill([
                'label' => $definition['label'],
                'type' => $definition['type'],
                'target' => $definition['target'],
                'options' => $definition['options'] ?? [],
            ])->save();
        }
    }

    /**
     * @return array<int, SchedulerEntry>
     */
    public function runDue(): array
    {
        $this->syncRegisteredEntries();
        $ran = [];

        SchedulerEntry::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$ran): void {
                foreach ($entries as $entry) {
                    if (! $this->isDue($entry)) {
                        continue;
                    }

                    $this->run($entry);
                    $ran[] = $entry;
                }
            });

        return $ran;
    }

    public function run(SchedulerEntry $entry): void
    {
        $definition = $this->registry->registered($entry->key);

        if (! $definition) {
            throw new InvalidArgumentException("Scheduler entry [{$entry->key}] is not registered.");
        }

        $entry->markStarted();

        try {
            match ($definition['type']) {
                SchedulerRegistry::TYPE_JOB => $this->dispatchJob($entry, $definition['target']),
                SchedulerRegistry::TYPE_COMMAND => $this->runCommand($definition['target']),
                default => throw new InvalidArgumentException("Unsupported scheduler type [{$definition['type']}]."),
            };

            $entry->markFinished('success', 'Scheduler entry dispatched successfully.');
        } catch (Throwable $exception) {
            $entry->markFinished('failed', Str::limit($exception->getMessage(), 1000, ''));

            throw $exception;
        }
    }

    public function isDue(SchedulerEntry $entry): bool
    {
        if (! $this->registry->isRegistered($entry->key)) {
            return false;
        }

        if (! $this->registry->frequencyIsSupported($entry->frequency)) {
            return false;
        }

        $nextRunAt = $entry->nextRunAt();

        return $nextRunAt === null || $nextRunAt->lte(now());
    }

    protected function dispatchJob(SchedulerEntry $entry, string $jobClass): void
    {
        $job = app($jobClass);

        if ($job instanceof ShouldQueue && method_exists($job, 'onQueue')) {
            $job->onQueue($entry->queue ?: 'default');
        }

        Bus::dispatch($job);
    }

    protected function runCommand(string $command): void
    {
        Artisan::call($command);
    }
}
