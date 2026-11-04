<?php

namespace App\Modules\MarketingChannels\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\ChannelHubService;
use App\Modules\MetaSocial\Services\MetaBusinessConnectService;
use App\Modules\MetaSocial\Services\MetaSocialSetupService;
use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelHubController extends Controller
{
    public function index(
        Request $request,
        ChannelHubService $hub,
        MetaBusinessConnectService $metaBusiness,
        ChannelSetupService $whatsapp,
        MetaSocialSetupService $social,
    ): View {
        return view('marketing-channels::user.index', [
            'cards' => $hub->cards($request->user()),
            'metaBusiness' => [
                'signup' => $metaBusiness->signupConfig(),
                'connection' => $metaBusiness->connection($request->user()),
            ],
            'metaConnect' => [
                'whatsapp' => $whatsapp->embeddedSignupConfig(),
                'messenger' => $social->embeddedSignupConfig('messenger'),
                'instagram' => $social->embeddedSignupConfig('instagram'),
            ],
        ]);
    }
}
