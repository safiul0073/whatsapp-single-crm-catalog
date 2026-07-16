<?php

namespace App\Modules\Newsletter\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Http\Requests\SendNewsletterRequest;
use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\Newsletter\Services\NewsletterDispatchService;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SendNewsletterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:newsletter.send'),
        ];
    }

    public function __construct(
        protected NewsletterDispatchService $dispatchService
    ) {}

    public function create(): View
    {
        $templates = NotificationTemplate::query()
            ->active()
            ->whereJsonContains('channels', 'email')
            ->whereNotNull('email_subject')
            ->whereNotNull('email_body')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'email_subject',
                'email_body',
                'channels',
                'variables',
            ]);

        $subscriberOptions = Subscriber::query()
            ->orderBy('email')
            ->pluck('email', 'id')
            ->all();

        return view('newsletter::admin.send.create', [
            'templates' => $templates,
            'subscriberOptions' => $subscriberOptions,
            'activeSubscriberCount' => Subscriber::query()->active()->count(),
            'subscriberCount' => Subscriber::query()->count(),
        ]);
    }

    public function store(SendNewsletterRequest $request): RedirectResponse
    {
        $queuedCount = $this->dispatchService->dispatch($request->validated());

        return redirect()
            ->route('admin.subscribers.send.create')
            ->with('success', __('Newsletter queued for :count subscribers.', ['count' => $queuedCount]));
    }
}
