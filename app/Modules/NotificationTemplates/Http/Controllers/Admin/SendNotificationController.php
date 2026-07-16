<?php

namespace App\Modules\NotificationTemplates\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Http\Requests\SendNotificationRequest;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SendNotificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:system-notifications.send'),
        ];
    }

    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function create(): View
    {
        $templates = NotificationTemplate::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'slug',
                'name',
                'description',
                'email_subject',
                'email_body',
                'sms_body',
                'channels',
                'variables',
            ]);

        $roleOptions = Role::query()
            ->orderBy('guard_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [
                $role->id => sprintf('%s (%s)', ucfirst($role->name), strtoupper($role->guard_name)),
            ])
            ->all();

        $channelAvailability = [
            'email' => (bool) setting('enable_email_notifications', true),
            'sms' => (bool) setting('enable_sms_notifications', false),
        ];

        return view('notification-templates::admin.send-notification.create', [
            'templates' => $templates,
            'roleOptions' => $roleOptions,
            'channelAvailability' => $channelAvailability,
        ]);
    }

    public function store(SendNotificationRequest $request): RedirectResponse
    {
        $queuedCount = $this->dispatchService->dispatch($request->validated());

        return redirect()
            ->route('admin.notification-send.create')
            ->with('success', __('Notification queued for :count recipients.', ['count' => $queuedCount]));
    }
}
