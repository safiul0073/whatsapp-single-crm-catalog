<?php

namespace App\Modules\AutoReplies\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\AutoReplies\Http\Requests\StoreAutoReplyRuleRequest;
use App\Modules\AutoReplies\Http\Requests\UpdateAutoReplyRuleRequest;
use App\Modules\AutoReplies\Models\AutoReplyRule;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoReplyController extends Controller
{
    public function index(Request $request, AutoReplyService $service): View
    {
        return view('auto-replies::user.index', [
            'rules' => $service->listForUser($request->user()),
        ]);
    }

    public function create(Request $request, WorkspaceResolver $workspaces): View
    {
        return view('auto-replies::user.create', [
            'rule' => null,
            ...$this->formOptions($request, $workspaces),
        ]);
    }

    public function store(StoreAutoReplyRuleRequest $request, AutoReplyService $service): RedirectResponse
    {
        $service->create($request->user(), $request->validated());

        return redirect()->route('user.auto-replies.index')->with('status', 'Auto-reply rule saved.');
    }

    public function edit(Request $request, AutoReplyRule $autoReply, AutoReplyService $service): View
    {
        return view('auto-replies::user.create', [
            'rule' => $service->forUser($request->user(), $autoReply),
            ...$this->formOptions($request, app(WorkspaceResolver::class)),
        ]);
    }

    public function update(UpdateAutoReplyRuleRequest $request, AutoReplyRule $autoReply, AutoReplyService $service): RedirectResponse
    {
        $service->update($request->user(), $autoReply, $request->validated());

        return redirect()->route('user.auto-replies.index')->with('status', 'Auto-reply rule updated.');
    }

    public function toggle(Request $request, AutoReplyRule $autoReply, AutoReplyService $service): RedirectResponse
    {
        $rule = $service->toggle($request->user(), $autoReply);

        return back()->with('status', $rule->is_active ? 'Auto-reply rule enabled.' : 'Auto-reply rule disabled.');
    }

    public function destroy(Request $request, AutoReplyRule $autoReply, AutoReplyService $service): RedirectResponse
    {
        $service->delete($request->user(), $autoReply);

        return back()->with('status', 'Auto-reply rule deleted.');
    }

    protected function formOptions(Request $request, WorkspaceResolver $workspaces): array
    {
        $workspace = $workspaces->current($request->user());

        return [
            'templates' => MessageTemplate::query()
                ->where('workspace_id', $workspace?->id)
                ->where('provider', 'whatsapp')
                ->where('status', MessageTemplateStatus::Approved->value)
                ->orderBy('name')
                ->get(['id', 'name', 'language', 'category', 'status']),
            'mediaItems' => Media::query()
                ->where('uploaded_by', $request->user()?->id)
                ->whereIn('type', ['image', 'video', 'audio', 'document'])
                ->latest()
                ->limit(50)
                ->get(),
        ];
    }
}
