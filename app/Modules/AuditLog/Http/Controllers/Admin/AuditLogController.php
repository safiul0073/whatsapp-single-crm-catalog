<?php

namespace App\Modules\AuditLog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:audit-logs.view'),
        ];
    }

    public function index(Request $request): View
    {
        $query = AuditLog::with('user');

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($type = $request->get('auditable_type')) {
            $query->where('auditable_type', $type);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);
        $actions = AuditLog::distinct()->pluck('action');
        $types = AuditLog::distinct()->pluck('auditable_type');

        return view('audit-log::admin.index', compact('logs', 'actions', 'types'));
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user', 'auditable');

        return view('audit-log::admin.show', compact('auditLog'));
    }
}
