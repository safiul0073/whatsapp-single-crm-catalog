<?php

namespace App\Modules\SchedulerQueue\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueMonitorService
{
    public function summary(): array
    {
        return [
            'pending' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed' => DB::table('failed_jobs')->count(),
        ];
    }

    public function pendingJobs(array $filters = []): LengthAwarePaginator
    {
        return DB::table('jobs')
            ->when($filters['queue'] ?? null, fn ($query, string $queue) => $query->where('queue', $queue))
            ->when(($filters['status'] ?? null) === 'pending', fn ($query) => $query->whereNull('reserved_at'))
            ->when(($filters['status'] ?? null) === 'reserved', fn ($query) => $query->whereNotNull('reserved_at'))
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'pending_page')
            ->through(fn ($job): array => $this->formatPendingJob($job))
            ->withQueryString();
    }

    public function failedJobs(array $filters = []): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->when($filters['queue'] ?? null, fn ($query, string $queue) => $query->where('queue', $queue))
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'failed_page')
            ->through(fn ($job): array => $this->formatFailedJob($job))
            ->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function queueNames(): array
    {
        return DB::table('jobs')->select('queue')
            ->union(DB::table('failed_jobs')->select('queue'))
            ->pluck('queue')
            ->filter()
            ->unique()
            ->values()
            ->all() ?: [config('queue.connections.database.queue', 'default')];
    }

    public function retry(string $id): void
    {
        Artisan::call('queue:retry', ['id' => [$id]]);
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
    }

    public function forget(string $id): void
    {
        Artisan::call('queue:forget', ['id' => $id]);
    }

    public function flush(): void
    {
        Artisan::call('queue:flush');
    }

    public function clear(string $queue): void
    {
        Artisan::call('queue:clear', [
            'connection' => 'database',
            '--queue' => $queue,
            '--force' => true,
        ]);
    }

    public function restart(): void
    {
        Artisan::call('queue:restart');
    }

    protected function formatPendingJob(object $job): array
    {
        $payload = $this->decodePayload($job->payload);

        return [
            'id' => $job->id,
            'queue' => $job->queue,
            'display_name' => $payload['displayName'] ?? $payload['job'] ?? 'Queued job',
            'job_class' => data_get($payload, 'data.commandName') ?? $payload['job'] ?? null,
            'attempts' => $job->attempts,
            'reserved_at' => $this->timestamp($job->reserved_at),
            'available_at' => $this->timestamp($job->available_at),
            'created_at' => $this->timestamp($job->created_at),
        ];
    }

    protected function formatFailedJob(object $job): array
    {
        $payload = $this->decodePayload($job->payload);

        return [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'display_name' => $payload['displayName'] ?? $payload['job'] ?? 'Failed job',
            'job_class' => data_get($payload, 'data.commandName') ?? $payload['job'] ?? null,
            'failed_at' => $job->failed_at,
            'exception_preview' => Str::limit((string) $job->exception, 220),
        ];
    }

    protected function decodePayload(?string $payload): array
    {
        if (! $payload) {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function timestamp(null|int|string $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp)->toDateTimeString();
        }

        return (string) $timestamp;
    }
}
