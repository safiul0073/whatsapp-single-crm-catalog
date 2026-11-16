<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Shipping\Http\Requests\OtherCostsRequest;
use App\Modules\Shipping\Http\Requests\ProductCostsRequest;
use App\Modules\Shipping\Http\Requests\ReportRequest;
use App\Modules\Shipping\Models\ShippingAuditEntry;
use App\Modules\Shipping\Services\OrderCostService;
use App\Modules\Shipping\Services\ShippingAuditService;
use App\Modules\Shipping\Services\ShippingMoney;
use App\Modules\Shipping\Services\ShippingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends ShippingBaseController
{
    public function index(ReportRequest $request, ShippingReportService $reports): View
    {
        $from = Carbon::parse($request->validated('from') ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($request->validated('to') ?? now()->toDateString())->endOfDay();
        $period = $request->validated('period') ?? 'daily';
        $currency = $this->storeCurrency($request);
        $rows = $reports->orderRows($this->workspaceId($request), $from, $to);

        return view('shipping::user.management.reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periods' => ShippingReportService::PERIODS,
            'rows' => $rows,
            'buckets' => $reports->byPeriod($rows, $period),
            'totals' => $reports->totals($rows),
            'currency' => $currency,
            'precision' => ShippingMoney::precision($currency),
            'canManageCosts' => $request->user()->can('shipping.product_costs.manage'),
        ]);
    }

    public function productCosts(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->where('workspace_id', $this->workspaceId($request))
            ->when($request->boolean('missing'), fn ($query) => $query->whereNull('cost_price'))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('sku', 'like', $like)->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like))))
            ->orderBy('product_id')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('shipping::user.management.reports.product-costs', [
            'variants' => $variants->through(fn (ProductVariant $variant): ProductVariant => $variant->makeVisible('cost_price')),
            'search' => $search,
            'missing' => $request->boolean('missing'),
            'currency' => $this->storeCurrency($request),
        ]);
    }

    public function updateProductCosts(ProductCostsRequest $request, ShippingAuditService $audit): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $costs = $request->validated('costs');
        $variants = ProductVariant::query()->where('workspace_id', $workspaceId)->whereIn('id', array_keys($costs))->get();
        $changed = 0;

        foreach ($variants as $variant) {
            $new = $costs[$variant->id] === null || $costs[$variant->id] === '' ? null : ShippingMoney::round($costs[$variant->id], 4);
            $old = $variant->cost_price === null ? null : ShippingMoney::round($variant->cost_price, 4);
            if ($new !== $old) {
                $variant->cost_price = $new;
                $variant->saveQuietly();
                $audit->record($workspaceId, 'product_cost.updated', 'Cost price changed for SKU '.($variant->sku ?: '#'.$variant->id), null, ['cost_price' => $old], ['cost_price' => $new]);
                $changed++;
            }
        }

        return back()->with('success', __(':count cost price(s) updated.', ['count' => $changed]));
    }

    public function updateOtherCosts(OtherCostsRequest $request, Order $order, OrderCostService $costs, ShippingAuditService $audit): RedirectResponse
    {
        $this->assertWorkspace($request, $order);
        $cost = $costs->ensure($order);
        $old = $cost->other_costs;
        $cost->update(['other_costs' => ShippingMoney::round($request->validated('other_costs'), ShippingMoney::precision($order->currency ?: 'USD'))]);
        $audit->record($order->workspace_id, 'order_cost.updated', 'Other costs changed for order '.$order->number, null, ['other_costs' => $old], ['other_costs' => $cost->other_costs]);

        return back()->with('success', __('Other costs saved.'));
    }

    public function audit(Request $request): View
    {
        return view('shipping::user.management.audit', [
            'entries' => ShippingAuditEntry::query()->with(['user:id,name', 'shipment:id,reference'])->where('workspace_id', $this->workspaceId($request))->latest('id')->paginate(50),
        ]);
    }
}
