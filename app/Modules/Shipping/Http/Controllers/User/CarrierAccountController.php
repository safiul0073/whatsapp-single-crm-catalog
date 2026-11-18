<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\CarrierAccountRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\Carriers\CarrierClientManager;
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

    public function create(Request $request): View
    {
        return view('shipping::user.management.carriers.form', [
            'account' => new CarrierAccount(['environment' => 'sandbox', 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'exchange_rate' => '1', 'is_active' => true]),
            'countries' => $this->countries(),
            'storeCurrency' => $this->storeCurrency($request),
        ]);
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

        return view('shipping::user.management.carriers.form', ['account' => $carrier, 'countries' => $this->countries(), 'storeCurrency' => $this->storeCurrency($request)]);
    }

    public function update(CarrierAccountRequest $request, CarrierAccount $carrier): RedirectResponse
    {
        $this->assertWorkspace($request, $carrier);
        $old = $carrier->only(['dim_divisor', 'weight_rounding_step', 'environment', 'exchange_rate', 'is_active', 'booking_enabled']);
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

    public function test(Request $request, CarrierAccount $carrier, CarrierClientManager $clients): RedirectResponse
    {
        $this->assertWorkspace($request, $carrier);
        $gaps = array_diff($carrier->liveRateGaps(), ['ship-from address']);
        if ($gaps !== []) {
            return back()->with('error', __('Add these before testing: :fields.', ['fields' => implode(', ', $gaps)]));
        }

        try {
            $message = $clients->for($carrier)->testConnection($carrier);
            $carrier->forceFill(['last_error' => null, 'last_error_at' => null])->saveQuietly();

            return back()->with('success', $message);
        } catch (CarrierApiException $exception) {
            $carrier->forceFill(['last_error' => mb_substr($exception->summary(), 0, 500), 'last_error_at' => now()])->saveQuietly();

            return back()->with('error', __('Connection failed — :message', ['message' => $exception->summary()]));
        }
    }
}
