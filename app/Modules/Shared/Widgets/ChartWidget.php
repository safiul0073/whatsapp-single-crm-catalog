<?php

namespace App\Modules\Shared\Widgets;

/**
 * Base class for chart-based dashboard widgets (powered by ApexCharts).
 *
 * Extend this class and implement getData() to define chart series/options.
 * The chart type, height, colors, and other options are customizable.
 *
 * Example:
 *   class RevenueChart extends ChartWidget
 *   {
 *       public function id(): string { return 'revenue-chart'; }
 *       public function title(): string { return 'Revenue'; }
 *       public function chartType(): string { return 'area'; }
 *       public function position(): int { return 20; }
 *
 *       protected function getData(): array
 *       {
 *           return [
 *               'series' => [['name' => 'Revenue', 'data' => [100, 200, 300]]],
 *               'categories' => ['Jan', 'Feb', 'Mar'],
 *           ];
 *       }
 *   }
 */
abstract class ChartWidget extends BaseWidget
{
    /**
     * ApexCharts chart type: area, line, bar, donut, pie, radialBar, etc.
     */
    public function chartType(): string
    {
        return 'area';
    }

    /**
     * Chart height in pixels.
     */
    public function chartHeight(): int
    {
        return 300;
    }

    /**
     * Chart color palette.
     *
     * @return string[]
     */
    public function chartColors(): array
    {
        return ['#5096f2', '#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4'];
    }

    /**
     * Return chart data: series, categories, labels, etc.
     *
     * For line/area/bar charts:
     *   ['series' => [['name' => 'Sales', 'data' => [10, 20, 30]]], 'categories' => ['Jan', 'Feb', 'Mar']]
     *
     * For donut/pie charts:
     *   ['series' => [42, 28, 18, 12], 'labels' => ['A', 'B', 'C', 'D']]
     *
     * @return array{series: array, categories?: string[], labels?: string[]}
     */
    abstract protected function getData(): array;

    /**
     * Additional ApexCharts options to merge (optional).
     * Return a JSON-encodable array matching ApexCharts option structure.
     *
     * @return array<string, mixed>
     */
    protected function getChartOptions(): array
    {
        return [];
    }

    public function render(): string
    {
        $data = $this->getData();

        return $this->view('widgets.partials.chart', [
            'widgetId' => $this->id(),
            'title' => $this->title(),
            'chartType' => $this->chartType(),
            'chartHeight' => $this->chartHeight(),
            'chartColors' => $this->chartColors(),
            'series' => $data['series'] ?? [],
            'categories' => $data['categories'] ?? [],
            'labels' => $data['labels'] ?? [],
            'extraOptions' => $this->getChartOptions(),
        ]);
    }
}
