<?php

namespace App\Modules\Chatbots\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Http\Requests\ChannelAiSettingsRequest;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ChannelAiReplyService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelAiSettingsController extends Controller
{
    public function edit(Request $request, WorkspaceResolver $workspaces, ChannelAiReplyService $service): View
    {
        $workspace = $workspaces->current($request->user());

        return view('chatbots::user.channel-ai', [
            'settings' => $service->settings($workspace),
            'channels' => ['whatsapp' => __('WhatsApp'), 'messenger' => __('Messenger'), 'instagram' => __('Instagram')],
            'chatbots' => Chatbot::query()->where('workspace_id', $workspace->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(ChannelAiSettingsRequest $request, WorkspaceResolver $workspaces, ChannelAiReplyService $service): RedirectResponse
    {
        $service->updateSettings($workspaces->current($request->user()), (array) $request->validated('channels', []));

        return back()->with('status', __('AI auto-reply settings saved.'));
    }
}
