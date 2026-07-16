<?php

namespace App\Modules\Campaigns\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Http\Requests\StoreCampaignRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\AudienceResolver;
use App\Modules\Campaigns\Services\CampaignDoctorService;
use App\Modules\Campaigns\Services\CampaignReportService;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $service,
        protected CampaignReportService $reports,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        $stats = Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('SUM(sent_count) as total_sent, SUM(delivered_count) as total_delivered, SUM(failed_count) as total_failed')
            ->first();

        return view('campaigns::user.index', [
            'campaigns' => $this->service->listForUser($request->user()),
            'totalSent' => (int) ($stats->total_sent ?? 0),
            'totalDelivered' => (int) ($stats->total_delivered ?? 0),
            'totalFailed' => (int) ($stats->total_failed ?? 0),
        ]);
    }

    public function create(Request $request): View
    {
        return view('campaigns::user.create', $this->service->builderData($request->user()));
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        try {
            $campaign = $this->service->create($request->user(), $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $route = $campaign->status === CampaignStatus::Draft
            ? route('user.campaigns.edit', $campaign)
            : route('user.campaigns.report', $campaign);

        return redirect()->to($route)->with('status', 'Campaign saved.');
    }

    public function doctor(StoreCampaignRequest $request, CampaignDoctorService $doctor, WorkspaceResolver $workspaces, PlanLimitService $limits): JsonResponse
    {
        $workspace = $workspaces->current($request->user());

        if (! $doctor->canUse($workspace->id)) {
            return response()->json($doctor->diagnose($request->user(), $request->validated()), 403);
        }

        $limits->ensurePlatformAiCredits($workspace->id);
        $report = $doctor->diagnose($request->user(), $request->validated());
        $limits->consumePlatformAiCredits($workspace->id);

        return response()->json($report);
    }

    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns::user.report', ['campaign' => $campaign->load('recipients')]);
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('campaigns::user.create', array_merge(
            $this->service->builderData(auth()->user()),
            ['campaign' => $campaign]
        ));
    }

    public function update(StoreCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $campaign = $this->service->update($campaign, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('user.campaigns.report', $campaign)->with('status', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('user.campaigns.index')->with('status', 'Campaign deleted.');
    }

    public function report(Campaign $campaign): View
    {
        $this->authorize('report', $campaign);
        $campaign->load(['channelAccount']);

        return view('campaigns::user.report', [
            'campaign' => $campaign,
            'summary' => $this->reports->summary($campaign),
            'recipients' => $campaign->recipients()->with('contact')->paginate(20),
        ]);
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('manage', $campaign);

        $this->service->pause($campaign);

        return back()->with('status', 'Campaign paused.');
    }

    public function resume(Campaign $campaign): RedirectResponse
    {
        $this->authorize('manage', $campaign);

        $this->service->resume($campaign);

        return back()->with('status', 'Campaign resumed.');
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        $this->authorize('manage', $campaign);

        $this->service->cancel($campaign);

        return back()->with('status', 'Campaign cancelled.');
    }

    public function duplicate(Campaign $campaign): RedirectResponse
    {
        $this->authorize('view', $campaign);

        $copy = $this->service->duplicate($campaign);

        return redirect()->route('user.campaigns.edit', $copy)->with('status', 'Campaign duplicated as draft.');
    }

    public function reRun(Campaign $campaign): RedirectResponse
    {
        $this->authorize('manage', $campaign);

        $campaign = $this->service->rerun($campaign);

        return redirect()->route('user.campaigns.report', $campaign)->with('status', 'Campaign re-run started.');
    }

    public function export(Campaign $campaign)
    {
        $this->authorize('report', $campaign);

        return $this->reports->exportCsv($campaign);
    }

    public function previewRecipients(Request $request, Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $contacts = app(AudienceResolver::class)
            ->contacts($campaign->workspace_id, [
                'audience_type' => $campaign->audience_type,
                'audience_ids' => $campaign->audience_ids,
                'segment_id' => $campaign->segment_id,
            ])
            ->take(10);

        return response()->json([
            'contacts' => $contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ]),
        ]);
    }
}
