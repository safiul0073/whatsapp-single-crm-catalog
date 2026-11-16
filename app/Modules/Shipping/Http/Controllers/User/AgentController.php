<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\AgentRequest;
use App\Modules\Shipping\Http\Requests\ChartStatusRequest;
use App\Modules\Shipping\Http\Requests\RateChartRequest;
use App\Modules\Shipping\Http\Requests\RateRowRequest;
use App\Modules\Shipping\Models\AgentRateChart;
use App\Modules\Shipping\Models\AgentRateRow;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Services\RateChartImportService;
use App\Modules\Shipping\Services\ShippingAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentController extends ShippingBaseController
{
    public function __construct(private ShippingAuditService $audit) {}

    public function index(Request $request): View
    {
        return view('shipping::user.management.agents.index', [
            'agents' => ShippingAgent::query()
                ->where('workspace_id', $this->workspaceId($request))
                ->withCount(['charts as active_charts_count' => fn ($charts) => $charts->usable()])
                ->orderBy('name')
                ->get(),
            'canManage' => $request->user()->can('shipping.agents.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('shipping::user.management.agents.form', [
            'agent' => new ShippingAgent(['currency' => $this->storeCurrency($request), 'exchange_rate' => '1', 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'payment_status' => 'clear', 'is_active' => true]),
            'storeCurrency' => $this->storeCurrency($request),
        ]);
    }

    public function store(AgentRequest $request): RedirectResponse
    {
        $agent = ShippingAgent::query()->create($request->validated() + ['workspace_id' => $this->workspaceId($request)]);
        $this->audit->record($agent->workspace_id, 'agent.created', 'Shipping agent added: '.$agent->name);

        return redirect()->route('user.shipping.agents.show', $agent)->with('success', __('Shipping agent saved. Add a rate chart next.'));
    }

    public function show(Request $request, ShippingAgent $agent): View
    {
        $this->assertWorkspace($request, $agent);

        return view('shipping::user.management.agents.show', [
            'agent' => $agent,
            'charts' => $agent->charts()->withCount('rows')->with(['rows' => fn ($rows) => $rows->orderBy('country_code')->orderBy('min_weight_kg')->limit(300)])->latest('id')->get(),
            'canManage' => $request->user()->can('shipping.agents.manage'),
            'storeCurrency' => $this->storeCurrency($request),
            'countries' => $this->countries(),
        ]);
    }

    public function edit(Request $request, ShippingAgent $agent): View
    {
        $this->assertWorkspace($request, $agent);

        return view('shipping::user.management.agents.form', ['agent' => $agent, 'storeCurrency' => $this->storeCurrency($request)]);
    }

    public function update(AgentRequest $request, ShippingAgent $agent): RedirectResponse
    {
        $this->assertWorkspace($request, $agent);
        $tracked = ['fuel_surcharge_percent', 'other_fee', 'exchange_rate', 'dim_divisor', 'weight_rounding_step', 'payment_status'];
        $old = $agent->only($tracked);
        $agent->update($request->validated());
        $this->audit->record($agent->workspace_id, 'agent.updated', 'Shipping agent updated: '.$agent->name, null, $old, $agent->only($tracked));

        return redirect()->route('user.shipping.agents.show', $agent)->with('success', __('Shipping agent updated.'));
    }

    public function destroy(Request $request, ShippingAgent $agent): RedirectResponse
    {
        $this->assertWorkspace($request, $agent);
        foreach ($agent->charts as $chart) {
            $this->deleteChartFile($chart);
        }
        $agent->delete();
        $this->audit->record($this->workspaceId($request), 'agent.deleted', 'Shipping agent removed: '.$agent->name);

        return redirect()->route('user.shipping.agents.index')->with('success', __('Shipping agent removed.'));
    }

    public function storeChart(RateChartRequest $request, ShippingAgent $agent, RateChartImportService $importer): RedirectResponse
    {
        $this->assertWorkspace($request, $agent);
        $file = $request->file('file');
        $extension = $file ? strtolower($file->getClientOriginalExtension()) : null;

        $chart = AgentRateChart::query()->create([
            'workspace_id' => $agent->workspace_id,
            'shipping_agent_id' => $agent->id,
            'name' => $request->validated('name'),
            'source_type' => $extension === null ? 'manual' : ($extension === 'xls' ? 'xlsx' : $extension),
            'file_path' => $file?->storeAs('shipping-rate-charts/'.$agent->workspace_id, uniqid('chart_', true).'.'.$extension, 'local'),
            'original_name' => $file?->getClientOriginalName(),
            'currency' => strtoupper($request->validated('currency')),
            'effective_from' => $request->validated('effective_from'),
            'expires_on' => $request->validated('expires_on'),
            'status' => 'pending_review',
        ]);

        $message = __('Rate chart saved. Add its rates, then activate it.');
        if ($file && $extension === 'pdf') {
            $message = __('PDF stored. Enter its rates by hand and review them before activating the chart.');
        } elseif ($file) {
            try {
                $result = $importer->import($chart, Storage::disk('local')->path($chart->file_path));
                $message = __(':imported rate row(s) imported, :skipped skipped. Review them, then activate the chart.', $result);
            } catch (ValidationException $exception) {
                $this->deleteChartFile($chart);
                $chart->delete();

                throw $exception;
            }
        }

        $this->audit->record($agent->workspace_id, 'chart.uploaded', 'Rate chart added for '.$agent->name.': '.$chart->name);

        return back()->with('success', $message);
    }

    public function chartStatus(ChartStatusRequest $request, AgentRateChart $chart): RedirectResponse
    {
        $this->assertWorkspace($request, $chart);
        $status = $request->validated('status');
        if ($status === 'active' && ! $chart->rows()->exists()) {
            throw ValidationException::withMessages(['status' => __('Add at least one rate row before activating this chart.')]);
        }

        $old = $chart->status;
        $chart->update([
            'status' => $status,
            'reviewed_by' => $status === 'active' ? $request->user()->id : $chart->reviewed_by,
            'reviewed_at' => $status === 'active' ? now() : $chart->reviewed_at,
        ]);
        $this->audit->record($chart->workspace_id, 'chart.'.$status, 'Rate chart "'.$chart->name.'" set to '.$status, null, ['status' => $old], ['status' => $status]);

        return back()->with('success', __('Rate chart updated.'));
    }

    public function destroyChart(Request $request, AgentRateChart $chart): RedirectResponse
    {
        $this->assertWorkspace($request, $chart);
        $this->deleteChartFile($chart);
        $chart->delete();
        $this->audit->record($this->workspaceId($request), 'chart.deleted', 'Rate chart removed: '.$chart->name);

        return back()->with('success', __('Rate chart removed.'));
    }

    public function storeRow(RateRowRequest $request, AgentRateChart $chart): RedirectResponse
    {
        $this->assertWorkspace($request, $chart);
        $row = $chart->rows()->create($request->validated() + ['workspace_id' => $chart->workspace_id]);
        $this->audit->record($chart->workspace_id, 'rate.added', 'Rate row added to "'.$chart->name.'"', null, null, $row->only(['country_code', 'zone', 'min_weight_kg', 'max_weight_kg', 'price', 'price_per_kg']));

        return back()->with('success', __('Rate row added.'));
    }

    public function destroyRow(Request $request, AgentRateRow $row): RedirectResponse
    {
        $this->assertWorkspace($request, $row);
        $old = $row->only(['country_code', 'zone', 'min_weight_kg', 'max_weight_kg', 'price', 'price_per_kg']);
        $row->delete();
        $this->audit->record($this->workspaceId($request), 'rate.deleted', 'Rate row removed', null, $old);

        return back()->with('success', __('Rate row removed.'));
    }

    public function chartFile(Request $request, AgentRateChart $chart): BinaryFileResponse
    {
        $this->assertWorkspace($request, $chart);
        abort_unless($chart->file_path && Storage::disk('local')->exists($chart->file_path), 404);

        return response()->download(Storage::disk('local')->path($chart->file_path), $chart->original_name ?: basename($chart->file_path));
    }

    private function deleteChartFile(AgentRateChart $chart): void
    {
        if ($chart->file_path) {
            Storage::disk('local')->delete($chart->file_path);
        }
    }
}
