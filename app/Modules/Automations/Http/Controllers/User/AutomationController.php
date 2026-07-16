<?php

namespace App\Modules\Automations\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Http\Requests\GenerateAutomationFlowRequest;
use App\Modules\Automations\Http\Requests\SaveAutomationRequest;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationFlowGenerator;
use App\Modules\Automations\Services\AutomationFlowTestService;
use App\Modules\Automations\Services\AutomationService;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    private const AUTOMATION_AI_FEATURE = 'automation_ai_builder';

    public function __construct(
        protected PipelineService $crmPipelines,
        protected LeadAssignmentService $crmAssignments,
    ) {}

    public function index(Request $request, AutomationService $service, WorkspaceResolver $workspaces, PlanLimitService $limits): View
    {
        return view('automations::user.index', [
            'automations' => $service->listForUser($request->user()),
            'stats' => $service->statsForUser($request->user()),
            'canUseAutomationAi' => $this->canUseAutomationAi($request, $workspaces, $limits),
        ]);
    }

    public function create(Request $request, AutomationService $service, AutomationFlowGenerator $generator, WorkspaceResolver $workspaces, PlanLimitService $limits): View|RedirectResponse
    {
        $generated = null;
        $canUseAutomationAi = $this->canUseAutomationAi($request, $workspaces, $limits);

        if ($request->filled('ai_prompt')) {
            if (! $canUseAutomationAi) {
                return redirect()
                    ->route('user.automations.index')
                    ->with('status', 'AI automation generation is available on premium plans. Upgrade to use this feature.');
            }

            $generated = $generator->generate($request->user(), (string) $request->query('ai_prompt'));
        }

        return view('automations::user.builder', [
            'automation' => null,
            'flow' => $generated['flow'] ?? $service->blankFlow(),
            'aiDraft' => $generated,
            'canUseAutomationAi' => $canUseAutomationAi,
            'chatbots' => $this->activeChatbots($request, $workspaces),
            'crmBuilder' => $this->crmBuilderData($request, $workspaces),
        ]);
    }

    public function generate(GenerateAutomationFlowRequest $request, AutomationFlowGenerator $generator, WorkspaceResolver $workspaces, PlanLimitService $limits): JsonResponse
    {
        if (! $this->canUseAutomationAi($request, $workspaces, $limits)) {
            return response()->json([
                'message' => 'AI automation generation is available on premium plans. Upgrade to use this feature.',
            ], 403);
        }

        $validated = $request->validated();

        return response()->json(
            $generator->generate($request->user(), (string) $validated['prompt'])
        );
    }

    public function testFlow(Request $request, AutomationFlowTestService $tester): JsonResponse
    {
        $validated = $request->validate([
            'nodes' => ['required', 'array', 'min:1'],
            'edges' => ['nullable', 'array'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($tester->test(
            $request->user(),
            $validated['nodes'],
            $validated['edges'] ?? [],
            $validated['message'] ?? 'Can I get pricing details?'
        ));
    }

    public function store(SaveAutomationRequest $request, AutomationService $service): RedirectResponse
    {
        $service->create($request->user(), [
            ...$request->validated(),
            'nodes' => $request->decodedJson('nodes'),
            'edges' => $request->decodedJson('edges'),
            'activate' => $request->boolean('activate'),
        ]);

        return redirect()->route('user.automations.index')->with('status', 'Automation saved.');
    }

    public function edit(Request $request, Automation $automation, AutomationService $service, WorkspaceResolver $workspaces, PlanLimitService $limits): View
    {
        $automation = $service->forUser($request->user(), $automation);

        return view('automations::user.builder', [
            'automation' => $automation,
            'flow' => [
                'nodes' => $automation->nodes ?? [],
                'edges' => $automation->edges ?? [],
            ],
            'aiDraft' => null,
            'canUseAutomationAi' => $this->canUseAutomationAi($request, $workspaces, $limits),
            'chatbots' => $this->activeChatbots($request, $workspaces),
            'crmBuilder' => $this->crmBuilderData($request, $workspaces),
        ]);
    }

    public function update(SaveAutomationRequest $request, Automation $automation, AutomationService $service): RedirectResponse
    {
        $service->update($request->user(), $automation, [
            ...$request->validated(),
            'nodes' => $request->decodedJson('nodes'),
            'edges' => $request->decodedJson('edges'),
            'activate' => $request->boolean('activate'),
        ]);

        return redirect()->route('user.automations.index')->with('status', 'Automation updated.');
    }

    public function toggle(Request $request, Automation $automation, AutomationService $service): RedirectResponse
    {
        $updated = $service->toggle($request->user(), $automation);

        return back()->with('status', $updated->is_active ? 'Automation activated.' : 'Automation deactivated.');
    }

    public function destroy(Request $request, Automation $automation, AutomationService $service): RedirectResponse
    {
        $service->delete($request->user(), $automation);

        return back()->with('status', 'Automation deleted.');
    }

    private function canUseAutomationAi(Request $request, WorkspaceResolver $workspaces, PlanLimitService $limits): bool
    {
        $workspace = $workspaces->current($request->user());

        if (! $workspace) {
            return false;
        }

        return $limits->featureEnabled($workspace->id, self::AUTOMATION_AI_FEATURE);
    }

    private function activeChatbots(Request $request, WorkspaceResolver $workspaces): array
    {
        $workspace = $workspaces->current($request->user());

        if (! $workspace) {
            return [];
        }

        return Chatbot::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Chatbot $chatbot): array => [
                'id' => $chatbot->id,
                'name' => $chatbot->name,
            ])
            ->all();
    }

    private function crmBuilderData(Request $request, WorkspaceResolver $workspaces): array
    {
        $workspace = $workspaces->current($request->user());
        if (! $workspace) {
            return ['pipelines' => [], 'stages' => [], 'tags' => [], 'agents' => []];
        }

        $pipelines = $this->crmPipelines->pipelinesForWorkspace($workspace->id);

        return [
            'pipelines' => $pipelines->map(fn ($pipeline): array => ['id' => $pipeline->id, 'name' => $pipeline->name, 'is_default' => $pipeline->is_default])->all(),
            'stages' => $pipelines->flatMap(fn ($pipeline) => $pipeline->stages->map(fn ($stage): array => ['id' => $stage->id, 'pipeline_id' => $pipeline->id, 'name' => $stage->name, 'pipeline' => $pipeline->name]))->values()->all(),
            'tags' => ContactTag::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name'])->toArray(),
            'agents' => $this->crmAssignments->assignableUsers($workspace->id)->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name])->all(),
        ];
    }
}
