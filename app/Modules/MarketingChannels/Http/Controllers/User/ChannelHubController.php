<?php

namespace App\Modules\MarketingChannels\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\ChannelHubService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelHubController extends Controller
{
    public function index(Request $request, ChannelHubService $hub): View
    {
        return view('marketing-channels::user.index', ['cards' => $hub->cards($request->user())]);
    }
}
