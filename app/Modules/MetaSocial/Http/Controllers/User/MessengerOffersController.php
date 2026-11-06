<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Models\MessengerMarketingOptin;
use App\Modules\MetaSocial\Services\MessengerMarketingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Messenger marketing messages: who subscribed to offers on each Page, the opt-in settings,
 * and a shortcut to send an offer broadcast from Campaigns.
 */
class MessengerOffersController extends Controller
{
    public function __construct(
        protected MessengerMarketingService $offers,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $pages = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'messenger')
            ->latest('id')
            ->get()
            ->map(fn (ChannelAccount $account): array => [
                'account' => $account,
                'settings' => $this->offers->settings($account),
                'subscribers' => $this->offers->subscriberCount($account),
                'optins' => MessengerMarketingOptin::query()
                    ->with('contact:id,name')
                    ->where('channel_account_id', $account->id)
                    ->latest('updated_at')
                    ->limit(100)
                    ->get(),
            ]);

        return view('meta-social::user.offers', ['pages' => $pages]);
    }
}
