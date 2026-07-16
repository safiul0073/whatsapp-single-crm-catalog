@php
    $defaultPeriod = array_key_first($periods);
@endphp

<div class="section-card">
    <div class="mb-4 flex items-center justify-between gap-4">
        <h2 class="heading-5 text-neutral-950">{{ $title }}</h2>
        <div class="flex shrink-0 items-center gap-1 rounded-lg bg-neutral-100 p-1">
            @foreach($periods as $periodKey => $period)
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors {{ $periodKey === $defaultPeriod ? 'bg-neutral-0 text-neutral-950 shadow-sm' : 'text-neutral-500 hover:text-neutral-950' }}"
                    data-chart-period="{{ $periodKey }}"
                    data-chart-target="{{ $widgetId }}"
                >
                    {{ $period['label'] }}
                </button>
            @endforeach
        </div>
    </div>
    <div id="chart-{{ $widgetId }}" class="apex-chart-widget min-h-[300px]"></div>
</div>

<script>
(function () {
    'use strict';

    function initFilterableChart() {
        'use strict';

        if (typeof ApexCharts === 'undefined') {
            setTimeout(initFilterableChart, 100);
            return;
        }

        const chartEl = document.querySelector('#chart-{{ $widgetId }}');
        if (!chartEl || chartEl.dataset.rendered) {
            return;
        }

        chartEl.dataset.rendered = 'true';

        const periodData = @js($periods);
        const chartType = @js($chartType);
        const chartColors = @js($chartColors);
        const chartHeight = @js($chartHeight);
        const defaultPeriod = @js($defaultPeriod);
        const isDark = document.documentElement.classList.contains('dark');

        const theme = {
            text: isDark ? '#8e99a4' : '#717f8e',
            grid: isDark ? '#2d3339' : '#e3e6e8',
        };

        const options = {
            chart: {
                type: chartType,
                height: chartHeight,
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                background: 'transparent',
                selection: { enabled: false },
            },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } },
            },
            series: periodData[defaultPeriod].data.series || [],
            colors: chartColors,
            dataLabels: { enabled: false },
            xaxis: {
                categories: periodData[defaultPeriod].data.categories || [],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: theme.text, fontSize: '12px' } },
            },
            yaxis: {
                labels: { style: { colors: theme.text, fontSize: '12px' } },
            },
            grid: {
                borderColor: theme.grid,
                strokeDashArray: 4,
                padding: { left: 8, right: 8 },
            },
            legend: {
                labels: { colors: theme.text },
                fontSize: '12px',
                position: 'top',
                horizontalAlign: 'right',
            },
            stroke: chartType === 'area' || chartType === 'line'
                ? { width: 2.5, curve: 'smooth' }
                : { width: 0 },
            tooltip: {
                y: {
                    formatter: function (value) {
                        'use strict';

                        return Number(value).toLocaleString();
                    },
                },
            },
        };

        if (chartType === 'area') {
            options.fill = {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] },
            };
        }

        const chart = new ApexCharts(chartEl, options);
        chart.render();

        document.querySelectorAll('[data-chart-target="{{ $widgetId }}"]').forEach(function (button) {
            'use strict';

            button.addEventListener('click', function () {
                'use strict';

                const period = button.dataset.chartPeriod;
                const nextData = periodData[period].data;

                document.querySelectorAll('[data-chart-target="{{ $widgetId }}"]').forEach(function (control) {
                    'use strict';

                    control.classList.toggle('bg-neutral-0', control === button);
                    control.classList.toggle('text-neutral-950', control === button);
                    control.classList.toggle('shadow-sm', control === button);
                    control.classList.toggle('text-neutral-500', control !== button);
                });

                chart.updateOptions({
                    xaxis: {
                        categories: nextData.categories || [],
                    },
                });
                chart.updateSeries(nextData.series || []);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilterableChart);
    } else {
        initFilterableChart();
    }
})();
</script>
