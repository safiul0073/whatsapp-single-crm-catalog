<?php

namespace App\Modules\Contacts\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Http\Requests\StoreContactGroupRequest;
use App\Modules\Contacts\Services\ContactGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactGroupController extends Controller
{
    public function index(Request $request, ContactGroupService $service): View
    {
        return view('contacts::user.groups', [
            'groups' => $service->listForUser($request->user()),
            ...$service->formDataForUser($request->user()),
        ]);
    }

    public function store(StoreContactGroupRequest $request, ContactGroupService $service): RedirectResponse
    {
        $service->storeForUser($request->user(), $request->validated());

        return back()->with('status', 'Group created.');
    }

    public function update(StoreContactGroupRequest $request, ContactGroupService $service, string $group): RedirectResponse
    {
        $service->updateForUser($request->user(), $group, $request->validated());

        return back()->with('status', 'Group updated.');
    }

    public function destroy(Request $request, ContactGroupService $service, string $group): RedirectResponse
    {
        $service->deleteForUser($request->user(), $group);

        return back()->with('status', 'Group deleted.');
    }

    public function duplicate(Request $request, ContactGroupService $service, string $group): RedirectResponse
    {
        $service->duplicateForUser($request->user(), $group);

        return back()->with('status', 'Group duplicated.');
    }

    public function preview(Request $request, ContactGroupService $service, string $group): JsonResponse
    {
        return response()->json(['data' => $service->previewForUser($request->user(), $group)]);
    }
}
