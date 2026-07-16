<?php

namespace App\Modules\ContactMessages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ContactMessages\Http\Requests\ReplyContactMessageRequest;
use App\Modules\ContactMessages\Http\Requests\UpdateContactMessageStatusRequest;
use App\Modules\ContactMessages\Models\ContactMessage;
use App\Modules\ContactMessages\Services\ContactMessageReplyService;
use App\Modules\ContactMessages\Tables\ContactMessagesTable;
use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessagesController extends Controller
{
    public function __construct(
        protected ContactMessageReplyService $replyService
    ) {}

    public function index(Request $request): View
    {
        $query = ContactMessage::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('interest', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $sortBy = in_array($request->input('sort_by'), ['created_at', 'email', 'company', 'interest', 'status'], true)
                ? $request->input('sort_by')
                : 'created_at';

            $query->reorder($sortBy, $request->input('sort_order', 'desc'));
        }

        $messages = $query->paginate($request->integer('per_page') ?: 15)->withQueryString();
        $table = ContactMessagesTable::make();

        return view('contact-messages::admin.messages.index', compact('messages', 'table'));
    }

    public function show($contactMessage): View
    {
        $contactMessage = $this->resolveMessage($contactMessage);

        if ($contactMessage->status === ContactMessage::STATUS_NEW) {
            $contactMessage->update([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ]);
            $contactMessage->refresh();
        }

        $replies = $contactMessage->replies()
            ->with(['admin', 'notificationLog'])
            ->latest()
            ->get();
        $templates = NotificationTemplate::query()
            ->active()
            ->whereJsonContains('channels', 'email')
            ->whereNotNull('email_subject')
            ->whereNotNull('email_body')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'email_subject', 'email_body', 'variables']);

        return view('contact-messages::admin.messages.show', compact('contactMessage', 'replies', 'templates'));
    }

    public function reply(ReplyContactMessageRequest $request, $contactMessage): RedirectResponse
    {
        $contactMessage = $this->resolveMessage($contactMessage);

        $this->replyService->send($contactMessage, $request->validated(), auth('admin')->user());

        return back()->with('success', __('Reply email queued successfully.'));
    }

    public function updateStatus(UpdateContactMessageStatusRequest $request, $contactMessage): RedirectResponse
    {
        $contactMessage = $this->resolveMessage($contactMessage);

        $data = $request->validated();

        $contactMessage->update([
            'status' => $data['status'],
            'read_at' => $data['status'] === ContactMessage::STATUS_NEW ? null : ($contactMessage->read_at ?? now()),
        ]);

        return back()->with('success', __('Contact message status updated.'));
    }

    public function subscribeNewsletter($contactMessage): RedirectResponse
    {
        $contactMessage = $this->resolveMessage($contactMessage);

        Subscriber::query()->updateOrCreate(
            ['email' => $contactMessage->email],
            ['active' => true]
        );

        return back()->with('success', __('Contact email added to newsletter subscribers.'));
    }

    public function destroy($contactMessage): RedirectResponse
    {
        $contactMessage = $this->resolveMessage($contactMessage);
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')
            ->with('success', __('Contact message deleted successfully.'));
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (! empty($ids)) {
            ContactMessage::query()->whereIn('id', $ids)->delete();
        }

        return back()->with('success', __('Selected contact messages deleted.'));
    }

    protected function resolveMessage($contactMessage): ContactMessage
    {
        if ($contactMessage instanceof ContactMessage) {
            return $contactMessage;
        }

        return ContactMessage::query()->findOrFail($contactMessage);
    }
}
