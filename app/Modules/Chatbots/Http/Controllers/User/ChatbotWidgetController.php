<?php

namespace App\Modules\Chatbots\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Http\Requests\StoreChatbotWidgetRequest;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Services\ChatbotWidgetService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotWidgetController extends Controller
{
    public function index(Request $request, ChatbotWidgetService $widgets): View
    {
        return view('chatbots::user.widgets.index', [
            'widgets' => $widgets->listForUser($request->user()),
        ]);
    }

    public function create(Request $request, WorkspaceResolver $workspaces): View
    {
        return view('chatbots::user.widgets.form', [
            'widget' => null,
            'chatbots' => $this->activeChatbots($request, $workspaces),
        ]);
    }

    public function store(StoreChatbotWidgetRequest $request, ChatbotWidgetService $widgets): RedirectResponse
    {
        $widget = $widgets->create($request->user(), $request->validated());

        return redirect()->route('user.chatbots.widgets.edit', $widget)->with('status', 'Website widget created.');
    }

    public function edit(Request $request, ChatbotWidget $widget, ChatbotWidgetService $widgets, WorkspaceResolver $workspaces): View
    {
        $widget = $widgets->forUser($request->user(), $widget)->load('chatbot');

        return view('chatbots::user.widgets.form', [
            'widget' => $widget,
            'chatbots' => $this->activeChatbots($request, $workspaces),
        ]);
    }

    public function update(StoreChatbotWidgetRequest $request, ChatbotWidget $widget, ChatbotWidgetService $widgets): RedirectResponse
    {
        $widgets->update($request->user(), $widget, $request->validated());

        return redirect()->route('user.chatbots.widgets.edit', $widget)->with('status', 'Website widget updated.');
    }

    public function destroy(Request $request, ChatbotWidget $widget, ChatbotWidgetService $widgets): RedirectResponse
    {
        $widgets->delete($request->user(), $widget);

        return redirect()->route('user.chatbots.widgets.index')->with('status', 'Website widget deleted.');
    }

    protected function activeChatbots(Request $request, WorkspaceResolver $workspaces)
    {
        $workspace = $workspaces->current($request->user());

        return Chatbot::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
