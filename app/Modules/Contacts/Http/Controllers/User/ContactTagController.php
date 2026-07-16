<?php

namespace App\Modules\Contacts\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Http\Requests\StoreContactTagRequest;
use App\Modules\Contacts\Services\ContactTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactTagController extends Controller
{
    public function index(Request $request, ContactTagService $service): View
    {
        return view('contacts::user.tags', [
            'tags' => $service->listForUser($request->user()),
        ]);
    }

    public function store(StoreContactTagRequest $request, ContactTagService $service): RedirectResponse
    {
        $service->storeForUser($request->user(), $request->validated());

        return back()->with('status', 'Tag created.');
    }

    public function update(StoreContactTagRequest $request, ContactTagService $service, string $tag): RedirectResponse
    {
        $service->updateForUser($request->user(), $tag, $request->validated());

        return back()->with('status', 'Tag updated.');
    }

    public function destroy(Request $request, ContactTagService $service, string $tag): RedirectResponse
    {
        $service->deleteForUser($request->user(), $tag);

        return back()->with('status', 'Tag deleted.');
    }
}
