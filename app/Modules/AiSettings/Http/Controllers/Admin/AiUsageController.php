<?php

namespace App\Modules\AiSettings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\AiSettings\Services\AiUsageReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AiUsageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ai-usage.view'),
        ];
    }

    public function index(Request $request, AiUsageReportService $reports): View
    {
        $filters = $request->validate([
            'range' => ['nullable', 'integer', 'in:7,14,30,90'],
            'status' => ['nullable', 'string', 'in:success,failed'],
            'provider' => ['nullable', 'string', 'max:255'],
            'feature' => ['nullable', 'string', 'max:255'],
            'workspace' => ['nullable', 'string', 'max:255'],
        ]);

        $range = (int) ($filters['range'] ?? 30);
        $filters['range'] = $range;

        return view('ai-settings::admin.usage', [
            'filters' => $filters,
            'range' => $range,
            'overview' => $reports->overview($filters),
            'chart' => $reports->chart($range, $filters),
            'logs' => $reports->logs($filters),
            'filterOptions' => $reports->filterOptions(),
        ]);
    }
}
