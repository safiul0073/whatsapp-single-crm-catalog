<?php

namespace App\Modules\MarketingChannels\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\View\View;

class ChannelAccountController extends Controller
{
    public function index(): View
    {
        return view('marketing-channels::admin.index', [
            'channels' => ChannelAccount::query()->with('workspace')->latest()->paginate(20),
        ]);
    }
}
