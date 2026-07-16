<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PlansSubscriptions\Models\Subscription;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        return view('plans-subscriptions::admin.subscriptions', [
            'subscriptions' => Subscription::query()
                ->with(['plan', 'workspace.owner'])
                ->latest()
                ->paginate(20),
        ]);
    }
}
