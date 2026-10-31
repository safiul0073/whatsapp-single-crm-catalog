<?php

namespace App\Modules\Chatbots\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Http\Requests\WhatsAppAiSettingsRequest;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\WhatsAppAiReplyService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppAiSettingsController extends Controller
{
    public function edit(Request $request, WorkspaceResolver $workspaces, WhatsAppAiReplyService $service): View
    {
        $workspace = $workspaces->current($request->user());

        return view('chatbots::user.whatsapp-ai', [
            'settings' => $service->settings($workspace),
            'chatbots' => Chatbot::query()->where('workspace_id', $workspace->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(WhatsAppAiSettingsRequest $request, WorkspaceResolver $workspaces, WhatsAppAiReplyService $service): RedirectResponse
    {
        $service->updateSettings(
            $workspaces->current($request->user()),
            $request->boolean('enabled'),
            $request->filled('chatbot_id') ? (int) $request->input('chatbot_id') : null,
        );

        return back()->with('status', 'WhatsApp AI auto-reply settings saved.');
    }
}
