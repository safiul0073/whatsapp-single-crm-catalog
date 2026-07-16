<?php

namespace App\Modules\AiSettings\Services;

use App\Modules\AiSettings\Models\AiUsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AiUsageReportService
{
    public function overview(array $filters = []): array
    {
        $query = $this->filteredQuery($filters);

        return [
            'total_calls' => (clone $query)->count(),
            'successful_calls' => (clone $query)->where('status', 'success')->count(),
            'failed_calls' => (clone $query)->where('status', 'failed')->count(),
            'total_tokens' => (int) (clone $query)->sum('total_tokens'),
            'estimated_cost' => (float) (clone $query)->sum('estimated_cost'),
        ];
    }

    public function chart(int $days, array $filters = []): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $success = $this->dailyCounts($startDate, $days, $filters, 'success');
        $failed = $this->dailyCounts($startDate, $days, $filters, 'failed');
        $categories = [];
        $successData = [];
        $failedData = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);
            $key = $date->toDateString();
            $categories[] = $date->format('M j');
            $successData[] = (int) ($success[$key] ?? 0);
            $failedData[] = (int) ($failed[$key] ?? 0);
        }

        return [
            'series' => [
                ['name' => __('Successful'), 'data' => $successData],
                ['name' => __('Failed'), 'data' => $failedData],
            ],
            'categories' => $categories,
        ];
    }

    public function logs(array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['workspace', 'user'])
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function filterOptions(): array
    {
        return [
            'providers' => AiUsageLog::query()->whereNotNull('provider')->distinct()->orderBy('provider')->pluck('provider')->values(),
            'features' => AiUsageLog::query()->distinct()->orderBy('feature')->pluck('feature')->values(),
        ];
    }

    protected function filteredQuery(array $filters = []): Builder
    {
        return AiUsageLog::query()
            ->when($filters['range'] ?? null, function (Builder $query, mixed $range): void {
                $query->where('created_at', '>=', now()->subDays(((int) $range) - 1)->startOfDay());
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider))
            ->when($filters['feature'] ?? null, fn (Builder $query, string $feature) => $query->where('feature', $feature))
            ->when($filters['workspace'] ?? null, function (Builder $query, string $workspace): void {
                $query->whereHas('workspace', fn (Builder $workspaceQuery) => $workspaceQuery->where('name', 'like', '%'.$workspace.'%'));
            });
    }

    protected function dailyCounts(Carbon $startDate, int $days, array $filters, string $status): Collection
    {
        return $this->filteredQuery(array_merge($filters, ['status' => $status]))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $startDate->copy()->addDays($days - 1)->endOfDay()])
            ->groupBy('date')
            ->pluck('count', 'date');
    }
}
