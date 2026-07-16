<?php

namespace App\Modules\Workspaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(): View
    {
        return view('workspaces::admin.index', [
            'workspaces' => Workspace::query()->latest()->paginate(20),
        ]);
    }
}
