<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Shipping\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ShippingReportService
{
    public const PERIODS = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    public function __construct(private OrderCostService $costs) {}

    /**
     * Per-order shipping and profit figures for orders that have shipping records.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function orderRows(int $workspaceId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $shipments = Shipment::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('order_id');

        $orders = Order::query()->withTrashed()->with('items')->where('workspace_id', $workspaceId)->whereIn('id', $shipments->keys())->get()->keyBy('id');

        return $shipments->map(function (Collection $orderShipments, int $orderId) use ($orders): ?array {
            $order = $orders->get($orderId);
            if (! $order) {
                return null;
            }

            $precision = ShippingMoney::precision($order->currency ?: 'USD');
            $minor = fn ($amount): int => OrderMoney::minor((string) ($amount ?? 0), $precision);
            $cost = $this->costs->ensure($order);

            $revenue = max(0, $minor($order->total) - $minor($order->refunded_amount));
            $estimated = $orderShipments->sum(fn (Shipment $shipment): int => $minor($shipment->estimated_cost));
            $approved = $orderShipments->sum(fn (Shipment $shipment): int => $minor($shipment->approved_cost));
            $hasActual = $orderShipments->contains(fn (Shipment $shipment): bool => $shipment->final_cost !== null);
            $actual = $orderShipments->sum(fn (Shipment $shipment): int => $minor($shipment->final_cost));
            $sets = (int) $orderShipments->sum('total_sets');
            $otherCosts = $minor($cost->other_costs);
            $productCost = $minor($cost->product_cost);

            return [
                'order' => $order,
                'date' => $orderShipments->min('created_at'),
                'currency' => $order->currency ?: 'USD',
                'precision' => $precision,
                'shipments' => $orderShipments->count(),
                'sets' => $sets,
                'revenue' => $revenue,
                'estimated' => $estimated,
                'approved' => $approved,
                'actual' => $hasActual ? $actual : null,
                'difference' => $hasActual ? $actual - $estimated : null,
                'cost_per_set' => $hasActual && $sets > 0 ? intdiv($actual, $sets) : null,
                'percent_of_revenue' => $hasActual && $revenue > 0 ? round(($actual / $revenue) * 100, 1) : null,
                'product_cost' => $cost->is_complete ? $productCost : null,
                'other_costs' => $otherCosts,
                'profit' => $hasActual && $cost->is_complete ? $revenue - $productCost - $actual - $otherCosts : null,
                'needs_review' => $orderShipments->contains('needs_review', true),
            ];
        })->filter()->sortByDesc('date')->values();
    }

    /**
     * Totals bucketed by day, week or month.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function byPeriod(Collection $rows, string $period): Collection
    {
        return $rows->groupBy(fn (array $row): string => match ($period) {
            'weekly' => $row['date']->copy()->startOfWeek()->format('Y-m-d'),
            'monthly' => $row['date']->format('Y-m'),
            default => $row['date']->format('Y-m-d'),
        })->map(fn (Collection $group, string $label): array => ['label' => $label] + $this->totals($group))->sortKeys()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function totals(Collection $rows): array
    {
        $billed = $rows->filter(fn (array $row): bool => $row['actual'] !== null);
        $profitable = $rows->filter(fn (array $row): bool => $row['profit'] !== null);
        $actual = (int) $billed->sum('actual');
        $revenueBilled = (int) $billed->sum('revenue');
        $sets = (int) $billed->sum('sets');

        return [
            'orders' => $rows->count(),
            'shipments' => (int) $rows->sum('shipments'),
            'revenue' => (int) $rows->sum('revenue'),
            'estimated' => (int) $rows->sum('estimated'),
            'actual' => $actual,
            'difference' => (int) $billed->sum('difference'),
            'cost_per_set' => $sets > 0 ? intdiv($actual, $sets) : null,
            'percent_of_revenue' => $revenueBilled > 0 ? round(($actual / $revenueBilled) * 100, 1) : null,
            'profit' => $profitable->isEmpty() ? null : (int) $profitable->sum('profit'),
            'profit_incomplete' => $rows->count() - $profitable->count(),
        ];
    }

    public static function format(?int $minor, int $precision = 2): string
    {
        if ($minor === null) {
            return '—';
        }

        return ($minor < 0 ? '-' : '').number_format(abs($minor) / (10 ** $precision), $precision);
    }
}
