<?php

namespace App\Modules\PaymentGateways\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PaymentGateways\Events\PaymentFailed;
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class PaymentsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:payments.view', only: ['index', 'show']),
            new Middleware('permission:payments.approve', only: ['approve', 'reject']),
        ];
    }

    public function __construct(
        protected PaymentService $service
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'gateway' => $request->get('gateway'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->integer('per_page') ?: null;
        $payments = $this->service->listPaginated($filters, $perPage);

        if ($request->ajax()) {
            $html = view('payment-gateways::admin.payments._table-rows', compact('payments'))->render();
            $pagination = view('components.tables.pagination', ['paginator' => $payments])->render();

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
                'total' => $payments->total(),
            ]);
        }

        return view('payment-gateways::admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        return view('payment-gateways::admin.payments.show', compact('payment'));
    }

    public function approve(Payment $payment): RedirectResponse
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', __('Only pending payments can be approved.'));
        }

        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'approved_by' => auth('admin')->id(),
                'approved_at' => now()->toIso8601String(),
            ]),
        ]);

        event(new PaymentSucceeded($payment));

        return back()->with('success', __('Payment approved successfully.'));
    }

    public function reject(Payment $payment, Request $request): RedirectResponse
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', __('Only pending payments can be rejected.'));
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], [
                'rejected_by' => auth('admin')->id(),
                'rejected_at' => now()->toIso8601String(),
                'rejection_reason' => $request->rejection_reason ?? '',
            ]),
        ]);

        event(new PaymentFailed($payment));

        return back()->with('success', __('Payment rejected.'));
    }
}
