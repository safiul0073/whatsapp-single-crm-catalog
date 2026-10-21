<?php

namespace App\Modules\Inbox\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Modules\Inbox\Services\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileInboxController extends Controller
{
    public function index(Request $request, InboxService $inbox): JsonResponse
    {
        abort_unless($request->user()->can('inbox.view') || $request->user()->can('inbox.assigned_only'), 403);

        return response()->json($inbox->conversationsForUser($request->user(), $request->only('q', 'status', 'provider')));
    }

    public function show(Request $request, InboxService $inbox, string $workspace, string $conversation): JsonResponse
    {
        abort_unless($request->user()->can('inbox.view') || $request->user()->can('inbox.assigned_only'), 403);

        $conversationId = (string) ($request->route('conversation') ?? $conversation);

        return response()->json($inbox->conversationForUser($request->user(), $conversationId, $request->only('before_id')));
    }

    public function store(SendInboxMessageRequest $request, InboxService $inbox, string $workspace, string $conversation): JsonResponse
    {
        abort_unless($request->user()->can('inbox.reply'), 403);

        $conversationId = (string) ($request->route('conversation') ?? $conversation);
        $result = $inbox->sendMessage($request->user(), $conversationId, (string) $request->validated('body', ''), $request->file('attachment'));

        return response()->json($result, ($result['ok'] ?? false) ? 201 : 422);
    }
}
