<?php

namespace App\Modules\LoginActivity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class LoginActivityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:login-activity.view'),
        ];
    }

    public function __construct(
        protected LoginActivityService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'event' => $request->get('event'),
            'user_type' => $request->get('user_type'),
            'ip_address' => $request->get('ip_address'),
            'search' => $request->get('search'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $activities = $this->service->listPaginated($filters);

        return view('login-activity::admin.index', compact('activities'));
    }
}
