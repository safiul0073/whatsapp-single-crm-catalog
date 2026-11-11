<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Services\InvoicePresenter;
use App\Modules\PlansSubscriptions\Services\PlanCheckoutService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Request $request, Payment $payment, WorkspaceResolver $workspaces, PlanCheckoutService $checkout, InvoicePresenter $invoices): View
    {
        abort_unless($checkout->belongsTo($payment, $workspaces->current($request->user())), 404);
        abort_unless($payment->status === 'completed' && data_get($payment->metadata, 'plan_id'), 404);

        return view('plans-subscriptions::invoice', ['invoice' => $invoices->present($payment)]);
    }
}
