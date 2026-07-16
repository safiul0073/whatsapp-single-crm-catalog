<?php

namespace App\Modules\Chatbots\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\Chatbots\Http\Requests\StoreChatbotRequest;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ChatbotPersonaGeneratorService;
use App\Modules\Chatbots\Services\ChatbotService;
use App\Modules\Chatbots\Services\ClaudeReplyService;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    public function index(Request $request, ChatbotService $chatbots): View
    {
        return view('chatbots::user.index', [
            'chatbots' => $chatbots->listForUser($request->user(), $request->only(['status', 'q'])),
            'stats' => $chatbots->statsForUser($request->user()),
            'filters' => [
                'status' => $request->query('status', 'all'),
                'q' => $request->query('q', ''),
            ],
        ]);
    }

    public function create(Request $request, WorkspaceResolver $workspaces): View
    {
        $workspace = $workspaces->current($request->user());

        return view('chatbots::user.config', [
            'chatbot' => null,
            'knowledgeBases' => KnowledgeBase::query()->where('workspace_id', $workspace->id)->latest()->get(),
        ]);
    }

    public function store(StoreChatbotRequest $request, ChatbotService $chatbots): RedirectResponse
    {
        $chatbot = $chatbots->create($request->user(), $request->validated());

        return redirect()->route('user.chatbots.config', $chatbot)->with('status', 'Chatbot created.');
    }

    public function generatePersona(Request $request, ChatbotPersonaGeneratorService $personas, WorkspaceResolver $workspaces): JsonResponse
    {
        $workspace = $workspaces->current($request->user());
        $workspaceId = $workspace?->id;
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'knowledge_bases' => ['nullable', 'array'],
            'knowledge_bases.*' => [
                'integer',
                Rule::exists(KnowledgeBase::class, 'id')->where('workspace_id', $workspaceId),
            ],
            'greeting' => ['nullable', 'string', 'max:500'],
            'instruction' => ['nullable', 'string', 'max:1000'],
        ]);

        $knowledgeBases = KnowledgeBase::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $validated['knowledge_bases'] ?? [])
            ->get();

        $persona = $personas->generate($validated, $knowledgeBases, $workspace->id);

        return response()->json($persona);
    }

    public function config(Request $request, Chatbot $chatbot, ChatbotService $chatbots, WorkspaceResolver $workspaces): View
    {
        $workspace = $workspaces->current($request->user());
        $chatbot = $chatbots->forUser($request->user(), $chatbot)->load('knowledgeBases');

        return view('chatbots::user.config', [
            'chatbot' => $chatbot,
            'knowledgeBases' => KnowledgeBase::query()->where('workspace_id', $workspace->id)->latest()->get(),
        ]);
    }

    public function update(StoreChatbotRequest $request, Chatbot $chatbot, ChatbotService $chatbots): RedirectResponse
    {
        $chatbots->update($request->user(), $chatbot, $request->validated());

        return redirect()->route('user.chatbots.config', $chatbot)->with('status', 'Chatbot updated.');
    }

    public function toggle(Request $request, Chatbot $chatbot, ChatbotService $chatbots): RedirectResponse
    {
        $chatbot = $chatbots->toggle($request->user(), $chatbot);

        return back()->with('status', $chatbot->is_active ? 'Chatbot activated.' : 'Chatbot paused.');
    }

    public function destroy(Request $request, Chatbot $chatbot, ChatbotService $chatbots): RedirectResponse
    {
        $chatbots->delete($request->user(), $chatbot);

        return redirect()->route('user.chatbots.index')->with('status', 'Chatbot deleted.');
    }

    public function test(Request $request, Chatbot $chatbot, ChatbotService $chatbots, ClaudeReplyService $replies, AiSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $chatbot = $chatbots->forUser($request->user(), $chatbot)->load('knowledgeBases');

        if (! app()->runningUnitTests() && ! $settings->hasConfiguredProvider($settings->textProvider())) {
            return response()->json([
                'message' => 'Platform AI is not configured for text generation. Configure it in Admin AI Settings.',
            ], 422);
        }

        return response()->json($replies->draftReply($validated['message'], ['chatbot' => $chatbot]));
    }
}
