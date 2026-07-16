<?php

namespace App\Modules\NotificationTemplates\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Services\NotificationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class NotificationLogsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:notification-logs.view', only: ['index', 'show']),
        ];
    }

    public function __construct(
        protected NotificationLogService $service
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'channel' => $request->get('channel'),
            'sort_by' => $request->get('sort_by'),
            'sort_order' => $request->get('sort_order'),
        ];

        $perPage = $request->integer('per_page') ?: null;
        $notificationLogs = $this->service->listPaginated($filters, $perPage);
        $stats = $this->service->getStats();

        if ($request->ajax()) {
            $html = view('notification-templates::admin.notification-logs._table-rows', compact('notificationLogs'))->render();
            $pagination = view('components.tables.pagination', ['paginator' => $notificationLogs])->render();

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
                'total' => $notificationLogs->total(),
            ]);
        }

        return view('notification-templates::admin.notification-logs.index', compact('notificationLogs', 'stats'));
    }

    public function show(NotificationLog $notificationLog): View
    {
        $notificationLog->load('template');

        return view('notification-templates::admin.notification-logs.show', compact('notificationLog'));
    }
}
