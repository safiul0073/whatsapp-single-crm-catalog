<?php

namespace App\Modules\Inbox\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Modules\Inbox\Services\InboxAiReplyService;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $crmAvailable = Route::has('user.inbox.conversations.crm');

        return view('inbox::user.index', [
            'inboxConfig' => [
                'initialConversationId' => $request->query('conversation'),
                'initialContactId' => $request->query('contact'),
                'routes' => [
                    'conversations' => route('user.inbox.conversations'),
                    'conversation' => route('user.inbox.conversations.show', ['conversation' => '__CONVERSATION__']),
                    'send' => route('user.inbox.conversations.messages.store', ['conversation' => '__CONVERSATION__']),
                    'aiReply' => route('user.inbox.conversations.ai-reply', ['conversation' => '__CONVERSATION__']),
                    'automation' => route('user.inbox.conversations.automation', ['conversation' => '__CONVERSATION__']),
                    'contactConversation' => route('user.inbox.contacts.conversation', ['contact' => '__CONTACT__']),
                    'telegramOptIn' => route('user.telegram.contacts.opt-in-link', ['contact' => '__CONTACT__']),
                    'contacts' => route('user.contacts.index'),
                    'crm' => $crmAvailable ? route('user.inbox.conversations.crm', ['conversation' => '__CONVERSATION__']) : null,
                    'crmLead' => $crmAvailable ? route('user.crm.leads.store') : null,
                    'crmStage' => $crmAvailable ? route('user.crm.leads.stage', ['lead' => '__LEAD__']) : null,
                    'crmAssign' => $crmAvailable ? route('user.crm.leads.assign', ['lead' => '__LEAD__']) : null,
                    'crmNote' => $crmAvailable ? route('user.crm.leads.notes.store', ['lead' => '__LEAD__']) : null,
                    'crmWon' => $crmAvailable ? route('user.crm.leads.won', ['lead' => '__LEAD__']) : null,
                    'crmLost' => $crmAvailable ? route('user.crm.leads.lost', ['lead' => '__LEAD__']) : null,
                    'crmTask' => $crmAvailable ? route('user.crm.tasks.store') : null,
                    'crmTaskComplete' => $crmAvailable ? route('user.crm.tasks.complete', ['task' => '__TASK__']) : null,
                    'commerceCatalog' => Route::has('user.commerce.conversations.catalog') ? route('user.commerce.conversations.catalog', ['conversation' => '__CONVERSATION__']) : null,
                    'commerceProducts' => Route::has('user.commerce.conversations.products') ? route('user.commerce.conversations.products', ['conversation' => '__CONVERSATION__']) : null,
                    'commerceProduct' => Route::has('user.commerce.conversations.product') ? route('user.commerce.conversations.product', ['conversation' => '__CONVERSATION__']) : null,
                    'commerceProductList' => Route::has('user.commerce.conversations.product-list') ? route('user.commerce.conversations.product-list', ['conversation' => '__CONVERSATION__']) : null,
                    'commerceProductVideo' => Route::has('user.commerce.conversations.product-video') ? route('user.commerce.conversations.product-video', ['conversation' => '__CONVERSATION__']) : null,
                ],
            ],
        ]);
    }

    public function conversations(Request $request, InboxService $inbox): JsonResponse
    {
        return response()->json($inbox->conversationsForUser($request->user(), [
            'q' => $request->query('q'),
            'status' => $request->query('status', 'all'),
            'provider' => $request->query('provider', 'all'),
        ]));
    }

    public function show(Request $request, InboxService $inbox, string $conversation): JsonResponse
    {
        return response()->json($inbox->conversationForUser($request->user(), $conversation));
    }

    public function storeMessage(SendInboxMessageRequest $request, InboxService $inbox, string $conversation): JsonResponse
    {
        $result = $inbox->sendMessage($request->user(), $conversation, (string) $request->validated('body', ''), $request->file('attachment'));

        return response()->json($result, ($result['ok'] ?? false) ? 201 : 422);
    }

    public function aiReply(Request $request, InboxService $inbox, InboxAiReplyService $replies, PlanLimitService $limits, string $conversation): JsonResponse
    {
        $validated = $request->validate([
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        $conversationModel = $inbox->conversationModelForUser($request->user(), $conversation);
        $limits->ensurePlatformAiCredits($conversationModel->workspace_id);
        $draft = $replies->draft($conversationModel, $validated['instruction'] ?? null);
        $limits->consumePlatformAiCredits($conversationModel->workspace_id);

        return response()->json($draft);
    }

    public function automation(Request $request, InboxService $inbox, string $conversation): JsonResponse
    {
        $validated = $request->validate([
            'automated_reply_enabled' => ['required', 'boolean'],
        ]);

        return response()->json($inbox->updateAutomation($request->user(), $conversation, (bool) $validated['automated_reply_enabled']));
    }

    public function contactConversation(Request $request, InboxService $inbox, string $contact): JsonResponse|RedirectResponse
    {
        $payload = $inbox->openForContact($request->user(), $contact, $request->input('provider'));

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return redirect()->route('user.inbox.index', ['conversation' => $payload['conversation']['id']]);
    }
}
