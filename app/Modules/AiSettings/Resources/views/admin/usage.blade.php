<x-layouts.admin :title="__('AI Usage')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('AI Usage') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Platform-wide AI calls, failures, token estimates, and usage traces.') }}</p>
            </div>
            <a href="{{ route('admin.ai-settings.index') }}" class="btn-sm btn-outline">
                <i class="ph ph-gear-six"></i>
                {{ __('AI Settings') }}
            </a>
        </div>

        <form method="GET" action="{{ route('admin.ai-usage.index') }}" class="section-card">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <x-forms.select :label="__('Range')" name="range" :value="$range">
                    @foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days', 90 => '90 days'] as $value => $label)
                        <option value="{{ $value }}" @selected((int) $range === $value)>{{ __($label) }}</option>
                    @endforeach
                </x-forms.select>

                <x-forms.select :label="__('Status')" name="status" :value="$filters['status'] ?? ''">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>{{ __('Success') }}</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>{{ __('Failed') }}</option>
                </x-forms.select>

                <x-forms.select :label="__('Provider')" name="provider" :value="$filters['provider'] ?? ''">
                    <option value="">{{ __('All providers') }}</option>
                    @foreach ($filterOptions['providers'] as $provider)
                        <option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ Str::headline($provider) }}</option>
                    @endforeach
                </x-forms.select>

                <x-forms.select :label="__('Feature')" name="feature" :value="$filters['feature'] ?? ''">
                    <option value="">{{ __('All features') }}</option>
                    @foreach ($filterOptions['features'] as $feature)
                        <option value="{{ $feature }}" @selected(($filters['feature'] ?? '') === $feature)>{{ Str::headline($feature) }}</option>
                    @endforeach
                </x-forms.select>

                <div class="flex gap-2 md:col-span-2 xl:col-span-1 xl:items-end">
                    <div class="min-w-0 flex-1">
                        <x-forms.input :label="__('Workspace')" name="workspace" :value="$filters['workspace'] ?? ''" :placeholder="__('Search workspace')" />
                    </div>
                    <button type="submit" class="btn btn-primary h-11 shrink-0 px-4">
                        <i class="ph ph-funnel"></i>
                    </button>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-ui.kpi-card :title="__('Total Calls')" :value="number_format($overview['total_calls'])" icon="ph-sparkle" color="primary" />
            <x-ui.kpi-card :title="__('Successful')" :value="number_format($overview['successful_calls'])" icon="ph-check-circle" color="success" />
            <x-ui.kpi-card :title="__('Failed')" :value="number_format($overview['failed_calls'])" icon="ph-warning-circle" color="error" />
            <x-ui.kpi-card :title="__('Tokens')" :value="number_format($overview['total_tokens'])" icon="ph-brackets-curly" color="warning" />
            <x-ui.kpi-card :title="__('Estimated Cost')" :value="'$'.number_format($overview['estimated_cost'], 4)" icon="ph-currency-dollar" color="info" />
        </div>

        <div class="section-card">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 class="heading-5 text-neutral-950">{{ __('AI calls by day') }}</h2>
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ trans_choice(':count day|:count days', $range, ['count' => $range]) }}</p>
            </div>
            <div id="ai-usage-chart" class="min-h-[320px]"></div>
        </div>

        <div class="section-card overflow-hidden p-0">
            <div class="border-b border-neutral-100 px-5 py-4">
                <h2 class="heading-5 text-neutral-950">{{ __('Usage Logs') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-100">
                    <thead class="bg-section">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Time') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Workspace') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('User') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Feature') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Provider') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Tokens') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Duration') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-neutral-0">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-neutral-500">{{ $log->created_at?->format('M d, H:i') }}</td>
                                <td class="px-5 py-4 text-sm font-semibold text-neutral-950">{{ $log->workspace?->name ?? __('Platform') }}</td>
                                <td class="px-5 py-4 text-sm text-neutral-600">
                                    {{ $log->user?->name ?? __('System') }}
                                    @if ($log->user?->email)
                                        <span class="block text-xs text-neutral-400">{{ $log->user->email }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-neutral-600">{{ Str::headline($log->feature) }}</td>
                                <td class="px-5 py-4 text-sm text-neutral-600">
                                    {{ $log->provider ? Str::headline($log->provider) : '-' }}
                                    @if ($log->model)
                                        <span class="block text-xs text-neutral-400">{{ $log->model }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-error' }}">{{ __(Str::headline($log->status)) }}</span>
                                </td>
                                <td class="px-5 py-4 text-right text-sm text-neutral-600">{{ $log->total_tokens !== null ? number_format($log->total_tokens) : '-' }}</td>
                                <td class="px-5 py-4 text-right text-sm text-neutral-600">{{ $log->duration_ms !== null ? number_format($log->duration_ms).'ms' : '-' }}</td>
                                <td class="max-w-xs px-5 py-4 text-sm text-neutral-500">{{ $log->error_message ? Str::limit($log->error_message, 90) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center text-sm text-neutral-500">{{ __('No AI usage logs match these filters yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-100 px-5 py-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                'use strict';

                function renderAiUsageChart() {
                    'use strict';

                    if (typeof ApexCharts === 'undefined') {
                        setTimeout(renderAiUsageChart, 100);
                        return;
                    }

                    const chartEl = document.querySelector('#ai-usage-chart');
                    if (!chartEl || chartEl.dataset.rendered) {
                        return;
                    }

                    chartEl.dataset.rendered = 'true';
                    const isDark = document.documentElement.classList.contains('dark');
                    const chart = new ApexCharts(chartEl, {
                        chart: { type: 'area', height: 320, toolbar: { show: false }, background: 'transparent' },
                        series: @js($chart['series']),
                        colors: ['#22c55e', '#ef4444'],
                        dataLabels: { enabled: false },
                        stroke: { width: 2.5, curve: 'smooth' },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] } },
                        xaxis: {
                            categories: @js($chart['categories']),
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                            labels: { style: { colors: isDark ? '#8e99a4' : '#717f8e' } },
                        },
                        yaxis: { labels: { style: { colors: isDark ? '#8e99a4' : '#717f8e' } } },
                        grid: { borderColor: isDark ? '#2d3339' : '#e3e6e8', strokeDashArray: 4 },
                        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: isDark ? '#8e99a4' : '#717f8e' } },
                    });

                    chart.render();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', renderAiUsageChart);
                } else {
                    renderAiUsageChart();
                }
            })();
        </script>
    @endpush
</x-layouts.admin>
