<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\CarrierAccountRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Services\ShippingAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CarrierAccountController extends ShippingBaseController
{
    public function __construct(private ShippingAuditService $audit) {}

    public function index(Request $request): View
    {
        return view('shipping::user.management.carriers.index', [
            'accounts' => CarrierAccount::query()->where('workspace_id', $this->workspaceId($request))->orderBy('carrier')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('shipping::user.management.carriers.form', ['account' => new CarrierAccount(['environment' => 'sandbox', 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'is_active' => true])]);
    }

    public function store(CarrierAccountRequest $request): RedirectResponse
    {
        $account = CarrierAccount::query()->create($request->accountData() + ['workspace_id' => $this->workspaceId($request)]);
        $this->audit->record($account->workspace_id, 'carrier.created', 'Courier account added: '.$account->name);

        return redirect()->route('user.shipping.carriers.index')->with('success', __('Courier account saved.'));
    }

    public function edit(Request $request, CarrierAccount $carrier): View
    {
        $this->assertWorkspace($request, $carrier);

        return view('shipping::user.management.carriers.form', ['account' => $carrier]);
    }

    public function update(CarrierAccountRequest $request, CarrierAccount $carrier): RedirectResponse
    {
        $this->assertWorkspace($request, $carrier);
        $old = $carrier->only(['dim_divisor', 'weight_rounding_step', 'environment', 'is_active']);
        $carrier->update($request->accountData($carrier));
        $this->audit->record($carrier->workspace_id, 'carrier.updated', 'Courier account updated: '.$carrier->name, null, $old, $carrier->only(array_keys($old)));

        return redirect()->route('user.shipping.carriers.index')->with('success', __('Courier account updated.'));
    }

    public function destroy(Request $request, CarrierAccount $carrier): RedirectResponse
    {
        $this->assertWorkspace($request, $carrier);
        $carrier->delete();
        $this->audit->record($this->workspaceId($request), 'carrier.deleted', 'Courier account removed: '.$carrier->name);

        return redirect()->route('user.shipping.carriers.index')->with('success', __('Courier account removed.'));
    }
}
