<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MetaSocial\Http\Requests\ConnectMetaSocialChannelRequest;
use App\Modules\MetaSocial\Http\Requests\SelectMetaSocialAccountsRequest;
use App\Modules\MetaSocial\Services\MetaSocialSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MetaSocialSetupController extends Controller
{
    public function __construct(
        protected MetaSocialSetupService $service,
        protected ChannelReadinessService $readiness,
    ) {}

    public function index(): View
    {
        return view('meta-social::user.setup', $this->service->pageData(auth()->user()));
    }

    public function embedded(ConnectMetaSocialChannelRequest $request, string $provider): RedirectResponse
    {
        $accounts = $this->service->discoverAccounts($provider, (string) $request->validated('code'));

        if (count($accounts) === 1) {
            return $this->connectedResponse($this->service->connectSelected(auth()->user(), $provider, [$accounts[0]['id']]));
        }

        return redirect()->to(route('user.meta-social.setup').'#'.$provider)
            ->with('status', __('Choose which accounts to connect.'));
    }

    public function select(SelectMetaSocialAccountsRequest $request, string $provider): RedirectResponse
    {
        return $this->connectedResponse($this->service->connectSelected(auth()->user(), $provider, $request->validated('account_ids')));
    }

    public function cancelSelection(): RedirectResponse
    {
        $this->service->forgetPendingSelection();

        return back();
    }

    /**
     * @param  Collection<int, ChannelAccount>  $channels
     */
    protected function connectedResponse(Collection $channels): RedirectResponse
    {
        $failed = $channels->filter(fn (ChannelAccount $channel): bool => $channel->fresh()->status->value === 'error');
        $names = $channels->pluck('name')->implode(', ');
        $pending = $channels->filter(fn (ChannelAccount $channel): bool => ($this->readiness->check($channel)['status'] ?? 'ready') !== 'ready');

        if ($failed->isEmpty() && $pending->isNotEmpty()) {
            return redirect()->route('user.meta-social.setup')->with('status', __(':channels connected. A few steps are left before messages can flow. See the checklist for each account.', ['channels' => $names]));
        }

        return redirect()->route('user.meta-social.setup')->with($failed->isNotEmpty() ? 'error' : 'status', $failed->isNotEmpty()
            ? __(':channels saved, but some need attention. Check the channel warning.', ['channels' => $failed->pluck('name')->implode(', ')])
            : __(':channels connected successfully.', ['channels' => $names]));
    }

    public function disconnect(ChannelAccount $channel): RedirectResponse
    {
        $this->service->disconnect(auth()->user(), $channel);

        return back()->with('status', __('Channel disconnected.'));
    }
}
