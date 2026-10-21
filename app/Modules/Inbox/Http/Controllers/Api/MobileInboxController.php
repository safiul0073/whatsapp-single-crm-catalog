<?php

namespace App\Modules\Inbox\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Modules\Inbox\Services\InboxAiReplyService;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileInboxController extends Controller
{
    public function index(Request $request, InboxService $inbox): JsonResponse
    {
        abort_unless($request->user()->can('inbox.view') || $request->user()->can('inbox.assigned_only'), 403);

        return response()->json($inbox->conversationsForUser($request->user(), $request->only('q', 'status', 'provider')));
    }

    public function show(
        Request $request,
        InboxService $inbox,
        InboxAiReplyService $aiReplies,
        string $workspace,
        string $conversation
    ): JsonResponse {
        abort_unless($request->user()->can('inbox.view') || $request->user()->can('inbox.assigned_only'), 403);

        $conversationId = (string) ($request->route('conversation') ?? $conversation);
        $payload = $inbox->conversationForUser($request->user(), $conversationId, $request->only('before_id'));

        $payload['ai_available'] = $aiReplies->isAvailable();

        return response()->json($payload);
    }

    public function store(SendInboxMessageRequest $request, InboxService $inbox, string $workspace, string $conversation): JsonResponse
    {
        abort_unless($request->user()->can('inbox.reply'), 403);

        $conversationId = (string) ($request->route('conversation') ?? $conversation);
        $result = $inbox->sendMessage($request->user(), $conversationId, (string) $request->validated('body', ''), $request->file('attachment'));

        return response()->json($result, ($result['ok'] ?? false) ? 201 : 422);
    }

    public function aiReply(
        Request $request,
        InboxService $inbox,
        InboxAiReplyService $replies,
        PlanLimitService $limits,
        string $workspace,
        string $conversation
    ): JsonResponse {
        abort_unless($request->user()->can('inbox.reply'), 403);

        $validated = $request->validate([
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        $conversationId = (string) ($request->route('conversation') ?? $conversation);
        $conversationModel = $inbox->conversationModelForUser($request->user(), $conversationId);
        $limits->ensurePlatformAiCredits($conversationModel->workspace_id);
        $draft = $replies->draft($conversationModel, $validated['instruction'] ?? null);
        $limits->consumePlatformAiCredits($conversationModel->workspace_id);

        return response()->json($draft);
    }
}
