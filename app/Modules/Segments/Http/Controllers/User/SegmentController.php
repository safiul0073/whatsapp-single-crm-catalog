<?php

namespace App\Modules\Segments\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Segments\Http\Requests\StoreSegmentRequest;
use App\Modules\Segments\Http\Requests\UpdateSegmentRequest;
use App\Modules\Segments\Services\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SegmentController extends Controller
{
    public function index(Request $request, SegmentService $service): View
    {
        return view('segments::user.index', [
            'segments' => $service->listForUser($request->user()),
            'stats' => $service->statsForUser($request->user()),
            ...$service->formDataForUser($request->user()),
        ]);
    }

    public function store(StoreSegmentRequest $request, SegmentService $service): RedirectResponse
    {
        $service->storeForUser($request->user(), $request->validated());

        return back()->with('status', 'Segment saved.');
    }

    public function update(UpdateSegmentRequest $request, SegmentService $service, string $segment): RedirectResponse
    {
        $service->updateForUser($request->user(), $segment, $request->validated());

        return back()->with('status', 'Segment updated.');
    }

    public function destroy(Request $request, SegmentService $service, string $segment): RedirectResponse
    {
        $service->deleteForUser($request->user(), $segment);

        return back()->with('status', 'Segment deleted.');
    }

    public function duplicate(Request $request, SegmentService $service, string $segment): RedirectResponse
    {
        $service->duplicateForUser($request->user(), $segment);

        return back()->with('status', 'Segment duplicated.');
    }

    public function preview(Request $request, SegmentService $service, string $segment): JsonResponse
    {
        return response()->json(['data' => $service->previewForUser($request->user(), $segment)]);
    }
}
