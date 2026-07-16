<?php

namespace App\Modules\Contacts\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Http\Requests\StoreContactRequest;
use App\Modules\Contacts\Http\Requests\UpdateContactRequest;
use App\Modules\Contacts\Services\ContactGroupService;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Contacts\Services\ContactTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request, ContactService $service, ContactTagService $tags, ContactGroupService $groups): View
    {
        return view('contacts::user.index', [
            'contacts' => $service->listForUser($request->user()),
            'tags' => $tags->allForUser($request->user()),
            'groups' => $groups->allForUser($request->user()),
        ]);
    }

    public function export(Request $request, ContactService $service): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'optin' => ['nullable', 'in:all,opted-in,not-opted-in'],
        ]);

        return $service->exportCsvForUser($request->user(), $filters);
    }

    public function store(StoreContactRequest $request, ContactService $service): RedirectResponse
    {
        $service->storeForUser($request->user(), $request->validated());

        return back()->with('status', 'Contact saved.');
    }

    public function update(UpdateContactRequest $request, ContactService $service, string $contact): RedirectResponse
    {
        $service->updateForUser($request->user(), $contact, $request->validated());

        return back()->with('status', 'Contact updated.');
    }

    public function destroy(Request $request, ContactService $service, string $contact): RedirectResponse
    {
        $service->deleteForUser($request->user(), $contact);

        return back()->with('status', 'Contact deleted.');
    }

    public function bulkTag(Request $request, ContactService $service): RedirectResponse
    {
        $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['integer'],
            'tag_id' => ['required', 'integer', 'exists:contact_tags,id'],
        ]);

        $service->bulkTag($request->user(), $request->contact_ids, $request->tag_id);

        return back()->with('status', 'Tag added to contacts.');
    }

    public function bulkGroup(Request $request, ContactService $service): RedirectResponse
    {
        $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['integer'],
            'group_id' => ['required', 'integer', 'exists:contact_groups,id'],
        ]);

        $service->bulkGroup($request->user(), $request->contact_ids, $request->group_id);

        return back()->with('status', 'Contacts added to group.');
    }

    public function bulkDelete(Request $request, ContactService $service): RedirectResponse
    {
        $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['integer'],
        ]);

        $service->bulkDelete($request->user(), $request->contact_ids);

        return back()->with('status', 'Contacts deleted.');
    }
}
