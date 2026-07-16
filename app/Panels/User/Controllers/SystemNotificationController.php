<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemNotificationController extends Controller
{
    public function __construct(
        protected SystemNotificationService $service
    ) {}

    /**
     * Full notifications page.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];

        $perPage = $request->integer('per_page') ?: 15;
        $notifications = $this->service->listPaginated($user, $filters, $perPage);

        return view('panels.user.system-notifications.index', compact('notifications'));
    }

    /**
     * Get unread notification count (AJAX).
     */
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'count' => $this->service->getUnreadCount($user),
        ]);
    }

    /**
     * Get recent notifications (AJAX).
     */
    public function recent(): JsonResponse
    {
        $user = auth()->user();
        $notifications = $this->service->getRecent($user, 10);

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

    /**
     * Mark a single notification as read (AJAX).
     */
    public function markRead(string $notification): JsonResponse
    {
        $this->service->markAsRead($notification);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read — redirects from form, JSON for AJAX.
     */
    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $this->service->markAllAsRead($user);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.system-notifications.index')
            ->with('success', __('All notifications marked as read.'));
    }

    /**
     * Get CSS classes for notification icon background by type.
     */
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
