<?php

namespace App\Modules\Crm\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Crm\Http\Requests\AssignCrmLeadRequest;
use App\Modules\Crm\Http\Requests\DeleteCrmStageRequest;
use App\Modules\Crm\Http\Requests\ManageCrmRequest;
use App\Modules\Crm\Http\Requests\MarkCrmLeadLostRequest;
use App\Modules\Crm\Http\Requests\MoveCrmLeadRequest;
use App\Modules\Crm\Http\Requests\StoreCrmLeadRequest;
use App\Modules\Crm\Http\Requests\StoreCrmNoteRequest;
use App\Modules\Crm\Http\Requests\StoreCrmPipelineRequest;
use App\Modules\Crm\Http\Requests\StoreCrmStageRequest;
use App\Modules\Crm\Http\Requests\StoreCrmTaskRequest;
use App\Modules\Crm\Services\ContactTimelineService;
use App\Modules\Crm\Services\CRMLeadService;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Crm\Services\TaskService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected PipelineService $pipelines,
        protected CRMLeadService $leads,
        protected TaskService $tasks,
        protected ContactTimelineService $timeline,
        protected LeadAssignmentService $assignments,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());
        $pipelines = $this->pipelines->pipelinesForWorkspace($workspace->id);
        $pipeline = filled($request->query('pipeline'))
            ? $this->pipelines->pipelineForWorkspace($workspace->id, (int) $request->query('pipeline'))->load('stages')
            : $pipelines->firstWhere('is_default', true);
        $filters = $request->only(['q', 'status', 'source', 'assigned_to']);

        return view('crm::user.index', [
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
            'leads' => $this->leads->boardLeads($workspace->id, $pipeline->id, $filters)->groupBy('stage_id'),
            'filters' => $filters,
            'agents' => $this->assignments->assignableUsers($workspace->id),
        ]);
    }

    public function sidebar(Request $request, int $conversation): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $canManage = $request->user()->can('crm.manage');
        $crm = $this->timeline->sidebar($workspace->id, $conversation);
        $crm['allowed_actions'] = $canManage
            ? ['create_lead', 'add_note', 'create_task', 'move_stage', 'assign_agent', 'mark_won', 'mark_lost']
            : [];

        return response()->json([
            'crm' => $crm,
            'permissions' => ['view' => $request->user()->can('crm.view'), 'manage' => $canManage],
        ]);
    }

    public function storeLead(StoreCrmLeadRequest $request): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $lead = $this->leads->createOrUpdate($workspace->id, (int) $request->validated('contact_id'), $request->validated(), $request->user());

        return $this->respond($request, ['lead' => $lead], __('Lead saved.'));
    }

    public function moveLead(MoveCrmLeadRequest $request, int $lead): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $lead = $this->leads->moveStage($workspace->id, $lead, (int) $request->validated('stage_id'), $request->user());

        return $this->respond($request, ['lead' => $lead], __('Lead stage updated.'));
    }

    public function assignLead(AssignCrmLeadRequest $request, int $lead): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $lead = $this->assignments->assign($workspace->id, $lead, (int) $request->validated('assigned_to'), $request->user());

        return $this->respond($request, ['lead' => $lead], __('Lead assigned.'));
    }

    public function addNote(StoreCrmNoteRequest $request, int $lead): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $activity = $this->leads->addNote($workspace->id, $lead, (string) $request->validated('description'), $request->user());

        return $this->respond($request, ['activity' => $activity], __('Note added.'));
    }

    public function markWon(ManageCrmRequest $request, int $lead): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());

        return $this->respond($request, ['lead' => $this->leads->markWon($workspace->id, $lead, $request->user())], __('Lead marked won.'));
    }

    public function markLost(MarkCrmLeadLostRequest $request, int $lead): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $lead = $this->leads->markLost($workspace->id, $lead, $request->validated('lost_reason'), $request->user());

        return $this->respond($request, ['lead' => $lead], __('Lead marked lost.'));
    }

    public function storeTask(StoreCrmTaskRequest $request): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $task = $this->tasks->create($workspace->id, $request->validated(), $request->user());

        return $this->respond($request, ['task' => $task], __('Follow-up task created.'));
    }

    public function completeTask(ManageCrmRequest $request, int $task): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());

        return $this->respond($request, ['task' => $this->tasks->updateStatus($workspace->id, $task, CrmTaskStatus::Completed, $request->user())], __('Task completed.'));
    }

    public function cancelTask(ManageCrmRequest $request, int $task): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());

        return $this->respond($request, ['task' => $this->tasks->updateStatus($workspace->id, $task, CrmTaskStatus::Cancelled, $request->user())], __('Task cancelled.'));
    }

    public function storePipeline(StoreCrmPipelineRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $pipeline = $this->pipelines->create($workspace->id, $request->validated());

        return redirect()->route('user.crm.index', ['pipeline' => $pipeline->id])->with('status', __('Pipeline created.'));
    }

    public function updatePipeline(StoreCrmPipelineRequest $request, int $pipeline): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $this->pipelines->update($workspace->id, $pipeline, $request->validated());

        return back()->with('status', __('Pipeline updated.'));
    }

    public function destroyPipeline(ManageCrmRequest $request, int $pipeline): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $this->pipelines->delete($workspace->id, $pipeline);

        return redirect()->route('user.crm.index')->with('status', __('Pipeline deleted.'));
    }

    public function storeStage(StoreCrmStageRequest $request, int $pipeline): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $this->pipelines->createStage($workspace->id, $pipeline, $request->validated());

        return back()->with('status', __('Stage created.'));
    }

    public function updateStage(StoreCrmStageRequest $request, int $stage): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $this->pipelines->updateStage($workspace->id, $stage, $request->validated());

        return back()->with('status', __('Stage updated.'));
    }

    public function destroyStage(DeleteCrmStageRequest $request, int $stage): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $this->pipelines->deleteStage($workspace->id, $stage, $request->validated('replacement_stage_id'));

        return back()->with('status', __('Stage deleted.'));
    }

    protected function respond(Request $request, array $payload, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload + ['message' => $message], 201);
        }

        return back()->with('status', $message);
    }
}
