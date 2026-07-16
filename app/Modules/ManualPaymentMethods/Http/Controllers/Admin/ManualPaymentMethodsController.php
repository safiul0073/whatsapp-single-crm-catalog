<?php

namespace App\Modules\ManualPaymentMethods\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ManualPaymentMethods\Models\ManualPaymentMethod;
use App\Modules\PaymentGatewaySettings\Models\PaymentGatewaySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

class ManualPaymentMethodsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:payment-gateway-settings.edit', only: ['store', 'destroy']),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $slug = Str::slug($request->name);

        if (ManualPaymentMethod::withTrashed()->where('slug', $slug)->exists()) {
            return redirect()
                ->route('admin.payment-gateway-settings.index')
                ->withErrors(['name' => __('A payment method with this name already exists.')])
                ->withInput()
                ->with('open_modal', 'addManualGateway');
        }

        ManualPaymentMethod::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('admin.payment-gateway-settings.index', ['#'.$slug])
            ->with('success', __('Manual payment gateway ":name" created. Configure it below.', ['name' => $request->name]));
    }

    public function destroy(ManualPaymentMethod $manualPaymentMethod): RedirectResponse
    {
        $slug = $manualPaymentMethod->slug;

        PaymentGatewaySetting::where('key', 'like', "{$slug}_%")->delete();
        $manualPaymentMethod->forceDelete();

        return redirect()
            ->route('admin.payment-gateway-settings.index')
            ->with('success', __('Manual payment gateway removed.'));
    }
}
