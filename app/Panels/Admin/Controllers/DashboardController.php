<?php

namespace App\Panels\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WidgetRegistry;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected WidgetRegistry $widgetRegistry) {}

    public function index(): View
    {
        $widgets = $this->widgetRegistry->getForPanel('admin', auth()->user());

        return view('panels.admin.dashboard', compact('widgets'));
    }
}
