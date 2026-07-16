<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $searchService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $guard = Auth::guard('admin')->check() ? 'admin' : 'web';

        $results = $this->searchService->search(
            $request->input('q'),
            $guard,
        );

        return response()->json(['groups' => $results]);
    }
}
