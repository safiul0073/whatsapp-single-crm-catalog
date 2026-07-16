<?php

namespace App\Modules\PaymentGateways\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\PaymentGateways\Services\WebhookLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class WebhookLogsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:webhook-logs.view', only: ['index', 'show']),
        ];
    }

    public function __construct(
        protected WebhookLogService $service
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'gateway' => $request->get('gateway'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->integer('per_page') ?: null;
        $webhookLogs = $this->service->listPaginated($filters, $perPage);

        if ($request->ajax()) {
            $html = view('payment-gateways::admin.webhook-logs._table-rows', compact('webhookLogs'))->render();
            $pagination = view('components.tables.pagination', ['paginator' => $webhookLogs])->render();

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
                'total' => $webhookLogs->total(),
            ]);
        }

        return view('payment-gateways::admin.webhook-logs.index', compact('webhookLogs'));
    }

    public function show(WebhookLog $webhookLog): View
    {
        return view('payment-gateways::admin.webhook-logs.show', compact('webhookLog'));
    }
}
