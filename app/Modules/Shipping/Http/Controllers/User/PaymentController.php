<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\PaymentRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingInvoice;
use App\Modules\Shipping\Models\ShippingPayment;
use App\Modules\Shipping\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends ShippingBaseController
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);
        $agents = ShippingAgent::query()->where('workspace_id', $workspaceId)->orderBy('name')->get();
        $openInvoices = ShippingInvoice::query()->with(['agent', 'carrierAccount'])->where('workspace_id', $workspaceId)->where('payment_status', '!=', 'paid')->latest('id')->limit(200)->get();
        $selectedInvoice = $openInvoices->firstWhere('id', $request->integer('invoice'));

        return view('shipping::user.management.payments.index', [
            'balances' => $agents->map(fn (ShippingAgent $agent): array => [
                'agent' => $agent,
                'balance' => $this->payments->balance($agent),
                'open_invoices' => $agent->invoices()->where('payment_status', '!=', 'paid')->count(),
            ]),
            'payments' => ShippingPayment::query()->with(['agent', 'carrierAccount', 'invoice', 'recorder'])->where('workspace_id', $workspaceId)->latest('paid_on')->latest('id')->paginate(25)->withQueryString(),
            'agents' => $agents,
            'carrierAccounts' => CarrierAccount::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'openInvoices' => $openInvoices,
            'canManage' => $request->user()->can('shipping.billing.manage'),
            'selectedInvoice' => $selectedInvoice?->id,
            'defaults' => [
                'provider' => match (true) {
                    $selectedInvoice?->shipping_agent_id !== null => 'agent:'.$selectedInvoice->shipping_agent_id,
                    $selectedInvoice?->shipping_carrier_account_id !== null => 'account:'.$selectedInvoice->shipping_carrier_account_id,
                    default => null,
                },
                'amount' => $selectedInvoice?->outstanding(),
                'currency' => $selectedInvoice?->currency ?? $this->storeCurrency($request),
            ],
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        $payment = $this->payments->record($this->workspaceId($request), $request->safe()->except('receipt'), $request->file('receipt'), $request->user());

        return back()->with('success', __('Payment of :amount :currency recorded for :provider.', ['amount' => $payment->amount, 'currency' => $payment->currency, 'provider' => $payment->providerLabel()]));
    }

    public function receipt(Request $request, ShippingPayment $payment): BinaryFileResponse
    {
        $this->assertWorkspace($request, $payment);
        abort_unless($payment->receipt_path && Storage::disk(PaymentService::DISK)->exists($payment->receipt_path), 404);

        return response()->download(Storage::disk(PaymentService::DISK)->path($payment->receipt_path), $payment->receipt_original_name ?: basename($payment->receipt_path));
    }

    public function destroy(Request $request, ShippingPayment $payment): RedirectResponse
    {
        $this->assertWorkspace($request, $payment);
        $this->payments->delete($payment, $request->user());

        return back()->with('success', __('Payment removed.'));
    }

    public function statement(Request $request, ShippingAgent $agent): View
    {
        $this->assertWorkspace($request, $agent);
        $from = ($request->filled('from') ? rescue(fn () => Carbon::parse((string) $request->query('from')), null, false) : null) ?? now()->subMonths(3)->startOfMonth();
        $to = ($request->filled('to') ? rescue(fn () => Carbon::parse((string) $request->query('to')), null, false) : null) ?? today();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return view('shipping::user.management.payments.statement', [
            'agent' => $agent,
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'statement' => $this->payments->statement($agent, $from->copy()->startOfDay(), $to->copy()->endOfDay()),
        ]);
    }
}
