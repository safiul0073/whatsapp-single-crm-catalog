<?php

namespace App\Modules\SystemNotifications\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SystemNotificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:system-notifications.view', only: ['index']),
            new Middleware('permission:system-notifications.send', only: ['send']),
        ];
    }

    public function __construct(
        protected SystemNotificationService $service
    ) {}

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();

        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];

        $perPage = $request->integer('per_page') ?: 15;
        $notifications = $this->service->listPaginated($admin, $filters, $perPage);

        return view('system-notifications::admin.index', compact('notifications'));
    }

    public function unreadCount(): JsonResponse
    {
        $admin = auth('admin')->user();

        return response()->json([
            'count' => $this->service->getUnreadCount($admin),
        ]);
    }

    public function recent(): JsonResponse
    {
        $admin = auth('admin')->user();
        $notifications = $this->service->getRecent($admin, 10);

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->getTitle(),
                'body' => $n->getBody(),
                'icon' => $n->getIcon(),
                'icon_bg' => $this->getIconBgClass($n->getType()),
                'url' => $n->getUrl(),
                'read_at' => $n->read_at,
                'time_ago' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function markRead(string $notification): JsonResponse
    {
        $this->service->markAsRead($notification);

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $admin = auth('admin')->user();
        $this->service->markAllAsRead($admin);

        return response()->json(['success' => true]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:500'],
            'recipient_type' => ['required', 'string', 'in:all_admins,all_users,role'],
            'role_id' => ['required_if:recipient_type,role', 'nullable', 'integer', 'exists:roles,id'],
        ]);

        $data = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'icon' => 'ph-megaphone',
            'type' => 'info',
        ];

        if (! empty($validated['url'])) {
            $data['url'] = $validated['url'];
        }

        $count = 0;

        switch ($validated['recipient_type']) {
            case 'all_admins':
                $admins = Admin::where('is_active', true)->get();
                $count = $this->service->sendToMany($admins, $data, 'announcement');
                break;

            case 'all_users':
                $users = User::where('is_active', true)->get();
                $count = $this->service->sendToMany($users, $data, 'announcement');
                break;

            case 'role':
                $role = Role::findOrFail($validated['role_id']);
                if ($role->guard_name === 'admin') {
                    $notifiables = Admin::role($role->name)->where('is_active', true)->get();
                } else {
                    $notifiables = User::role($role->name)->where('is_active', true)->get();
                }
                $count = $this->service->sendToMany($notifiables, $data, 'announcement');
                break;
        }

        return response()->json([
            'success' => true,
            'message' => __('Notification sent to :count recipients.', ['count' => $count]),
        ]);
    }

    protected function getIconBgClass(string $type): string
    {
        return match ($type) {
            'success' => 'bg-success/10 text-success',
            'warning' => 'bg-warning/10 text-warning',
            'danger' => 'bg-error/10 text-error',
            default => 'bg-primary/10 text-primary',
        };
    }
}
