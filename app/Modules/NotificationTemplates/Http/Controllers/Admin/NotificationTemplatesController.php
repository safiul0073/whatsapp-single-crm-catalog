<?php

namespace App\Modules\NotificationTemplates\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\NotificationTemplateService;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class NotificationTemplatesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:notification-templates.view', only: ['index']),
            new Middleware('permission:notification-templates.edit', only: ['edit', 'update', 'toggleStatus']),
        ];
    }

    public function __construct(
        protected NotificationTemplateService $service
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'sort_by' => $request->get('sort_by'),
            'sort_order' => $request->get('sort_order'),
        ];

        $perPage = $request->integer('per_page') ?: null;
        $notificationTemplates = $this->service->listPaginated($filters, $perPage);

        if ($request->ajax()) {
            $html = view('notification-templates::admin.notification-templates._table-rows', compact('notificationTemplates'))->render();
            $pagination = view('components.tables.pagination', ['paginator' => $notificationTemplates])->render();

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
                'total' => $notificationTemplates->total(),
            ]);
        }

        return view('notification-templates::admin.notification-templates.index', compact('notificationTemplates'));
    }

    public function edit(NotificationTemplate $notificationTemplate): View
    {
        return view('notification-templates::admin.notification-templates.edit', compact('notificationTemplate'));
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $data = $request->validated();
        $data['channels'] = $request->input('channels', []);
        $data['is_active'] = $request->boolean('is_active');

        $this->service->update($notificationTemplate, $data);

        return redirect()
            ->route('admin.notification-templates.edit', $notificationTemplate)
            ->with('success', __('Template updated successfully.'));
    }

    public function toggleStatus(NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $this->service->toggleStatus($notificationTemplate);

        return back()->with('success', __('Status updated successfully.'));
    }

    public function preview(Request $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        $renderer = app(TemplateRenderer::class);

        $sampleVars = [];
        foreach ($notificationTemplate->variables ?? [] as $key => $description) {
            $sampleVars[$key] = "{{$key}}";
        }

        $channel = $request->get('channel', 'email');

        $preview = match ($channel) {
            'email' => [
                'subject' => $renderer->render($notificationTemplate->email_subject, $sampleVars),
                'body' => $renderer->render($notificationTemplate->email_body, $sampleVars),
            ],
            'sms' => [
                'body' => $renderer->render($notificationTemplate->sms_body, $sampleVars),
            ],
            'in_app' => [
                'title' => $renderer->render($notificationTemplate->in_app_title, $sampleVars),
                'body' => $renderer->render($notificationTemplate->in_app_body, $sampleVars),
            ],
            'push' => [
                'title' => $renderer->render($notificationTemplate->push_title, $sampleVars),
                'body' => $renderer->render($notificationTemplate->push_body, $sampleVars),
            ],
            default => [],
        };

        return response()->json(['preview' => $preview]);
    }
}
