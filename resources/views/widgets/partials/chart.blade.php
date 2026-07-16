<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ $title }}</h2>
    <div id="chart-{{ $widgetId }}" class="apex-chart-widget" style="min-height: {{ $chartHeight }}px;"></div>
</div>

<script>
(function () {
    function initChart() {
        if (typeof ApexCharts === 'undefined') {
            setTimeout(initChart, 100);
            return;
        }

        const el = document.querySelector('#chart-{{ $widgetId }}');
        if (!el || el.dataset.rendered) return;
        el.dataset.rendered = 'true';

        const isDark = document.documentElement.classList.contains('dark');

        const theme = {
            text: isDark ? '#8e99a4' : '#717f8e',
            grid: isDark ? '#2d3339' : '#e3e6e8',
            tooltipBg: isDark ? '#17191c' : '#ffffff',
            tooltipBorder: isDark ? '#2d3339' : '#e3e6e8',
            tooltipText: isDark ? '#c9cfd5' : '#3d4851',
        };

        const chartType = @js($chartType);
        const isAxisChart = ['area', 'line', 'bar'].includes(chartType);
        const isPieChart = ['donut', 'pie', 'radialBar'].includes(chartType);

        const options = {
            chart: {
                type: chartType,
                height: {{ $chartHeight }},
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                background: 'transparent',
                selection: { enabled: false },
            },
            states: {
                hover: { filter: { type: 'none' } },
                active: { filter: { type: 'none' } },
            },
            series: @js($series),
            colors: @js($chartColors),
            dataLabels: { enabled: false },
            @if(!empty($categories))
            xaxis: {
                categories: @js($categories),
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: theme.text, fontSize: '12px' } },
            },
            @endif
            @if(!empty($labels))
            labels: @js($labels),
            @endif
            yaxis: {
                labels: {
                    style: { colors: theme.text, fontSize: '12px' },
                },
            },
            grid: {
                borderColor: theme.grid,
                strokeDashArray: 4,
                padding: { left: 8, right: 8 },
            },
            legend: {
                labels: { colors: theme.text },
                fontSize: '12px',
                position: isPieChart ? 'bottom' : 'top',
                horizontalAlign: 'right',
            },
            tooltip: {
                theme: false,
                style: { fontSize: '12px' },
                custom: isAxisChart ? function ({ series, seriesIndex, dataPointIndex, w }) {
                    let rows = '';
                    for (let i = 0; i < series.length; i++) {
                        const name = w.globals.seriesNames[i] || '';
                        const color = w.globals.colors[i] || '#5096f2';
                        const val = series[i][dataPointIndex];
                        if (val === undefined) continue;
                        rows += '<div style="display:flex;align-items:center;gap:6px;' + (i > 0 ? 'margin-top:4px;' : '') + '">'
                            + '<span style="width:8px;height:8px;border-radius:50%;background:' + color + ';display:inline-block;flex-shrink:0;"></span>'
                            + '<span style="color:' + theme.tooltipText + ';font-size:12px;">' + name + ': <b>' + val.toLocaleString() + '</b></span>'
                            + '</div>';
                    }
                    const cat = w.globals.categoryLabels?.[dataPointIndex] || w.globals.labels?.[dataPointIndex] || '';
                    return '<div style="background:' + theme.tooltipBg + ';border:1px solid ' + theme.tooltipBorder + ';border-radius:8px;padding:10px 14px;box-shadow:0 4px 16px rgba(0,0,0,0.12);direction:ltr;text-align:left;">'
                        + (cat ? '<div style="font-weight:600;color:' + theme.text + ';margin-bottom:6px;font-size:12px;">' + cat + '</div>' : '')
                        + rows
                        + '</div>';
                } : undefined,
            },
            stroke: isAxisChart
                ? { width: 2.5, curve: 'smooth' }
                : (isPieChart ? { width: 3, colors: [isDark ? '#101214' : '#ffffff'] } : { width: 0 }),
            @if($chartType === 'area')
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] },
            },
            @endif
            @if(in_array($chartType, ['donut', 'pie']))
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: { show: true, color: theme.text, fontSize: '13px', offsetY: -4 },
                            value: { show: true, color: theme.text, fontSize: '20px', fontWeight: 700, offsetY: 4 },
                            total: { show: true, label: '{{ __("Total") }}', color: theme.text, fontSize: '12px' },
                        },
                    },
                },
            },
            @endif
        };

        const extraOptions = @js($extraOptions);
        if (extraOptions && Object.keys(extraOptions).length) {
            Object.assign(options, extraOptions);
        }

        new ApexCharts(el, options).render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChart);
    } else {
        initChart();
    }
})();
</script>
