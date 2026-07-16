<?php

namespace App\Modules\Leads\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Http\Requests\BulkConvertLeadsRequest;
use App\Modules\Leads\Http\Requests\BulkDeleteLeadsRequest;
use App\Modules\Leads\Http\Requests\ConvertLeadRequest;
use App\Modules\Leads\Http\Requests\GenerateLeadsRequest;
use App\Modules\Leads\Http\Requests\SendLeadMessageRequest;
use App\Modules\Leads\Http\Requests\UpdateLeadRequest;
use App\Modules\Leads\Services\LeadMessageService;
use App\Modules\Leads\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request, LeadService $service): View
    {
        return view('leads::user.index', array_merge([
            'leads' => $service->listForUser($request->user(), $request->only([
                'search',
                'stage',
                'source',
                'verification_status',
                'country',
                'category',
            ])),
            'stats' => $service->statsForUser($request->user()),
            'filters' => $request->only([
                'search',
                'stage',
                'source',
                'verification_status',
                'country',
                'category',
            ]),
        ], $service->formDataForUser($request->user())));
    }

    public function update(UpdateLeadRequest $request, LeadService $service, string $lead): RedirectResponse
    {
        $service->updateForUser($request->user(), $lead, $request->validated());

        return back()->with('status', 'Lead updated.');
    }

    public function destroy(Request $request, LeadService $service, string $lead): RedirectResponse
    {
        $service->deleteForUser($request->user(), $lead);

        return back()->with('status', 'Lead deleted.');
    }

    public function generate(GenerateLeadsRequest $request, LeadService $service): RedirectResponse
    {
        $result = $service->generateForUser($request->user(), $request->validated());
        $count = $result['leads']->count();
        $skipped = $result['skipped'];

        $message = trans_choice(':count Google Places lead generated.|:count Google Places leads generated.', $count, ['count' => $count]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice(':count draft skipped because no phone or email was available.|:count drafts skipped because no phone or email was available.', $skipped, ['count' => $skipped]);
        }

        return back()->with('status', $message);
    }

    public function convert(ConvertLeadRequest $request, LeadService $service, string $lead): RedirectResponse
    {
        $service->convertForUser($request->user(), $lead, $request->validated());

        return back()->with('status', 'Lead converted to contact.');
    }

    public function bulkConvert(BulkConvertLeadsRequest $request, LeadService $service): RedirectResponse
    {
        $validated = $request->validated();
        $count = $service->bulkConvertForUser(
            $request->user(),
            $validated['lead_ids'],
            collect($validated)->except('lead_ids')->all(),
        );

        return back()->with('status', trans_choice(':count lead converted to contacts.|:count leads converted to contacts.', $count, ['count' => $count]));
    }

    public function bulkDelete(BulkDeleteLeadsRequest $request, LeadService $service): RedirectResponse
    {
        $count = $service->bulkDeleteForUser($request->user(), $request->validated('lead_ids'));

        return back()->with('status', trans_choice(':count lead deleted.|:count leads deleted.', $count, ['count' => $count]));
    }

    public function sendMessage(SendLeadMessageRequest $request, LeadMessageService $messages, string $lead): RedirectResponse
    {
        $result = $messages->sendForUser($request->user(), $lead, $request->validated());

        return back()->with(($result['ok'] ?? false) ? 'status' : 'error', $result['message'] ?? ($result['error'] ?? 'Message could not be sent.'));
    }
}
