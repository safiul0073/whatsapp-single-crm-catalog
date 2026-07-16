<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardStatsService $statsService
    ) {}

    public function index(Request $request): View
    {
        $range = $request->get('range', '30d');
        if (! in_array($range, ['7d', '30d', '90d'])) {
            $range = '30d';
        }

        $data = $this->statsService->getStats($request->user(), $range);

        return view('panels.user.dashboard', array_merge($data, [
            'range' => $range,
        ]));
    }
}
