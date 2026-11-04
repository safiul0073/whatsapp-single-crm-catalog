<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Http\Requests\ActivateMetaBusinessAssetsRequest;
use App\Modules\MetaSocial\Http\Requests\ConnectMetaBusinessRequest;
use App\Modules\MetaSocial\Services\MetaBusinessConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MetaBusinessController extends Controller
{
    protected const RESULT_KEY = 'meta_business.activation_result';

    public function __construct(
        protected MetaBusinessConnectService $service,
        protected ChannelReadinessService $readiness,
    ) {}

    public function show(): View|RedirectResponse
    {
        $connection = $this->service->connection(auth()->user());

        if (! $connection) {
            return redirect()->route('user.channels.index');
        }

        return view('meta-social::user.business', [
            'connection' => $connection,
            'activeIds' => $this->service->activeIds(auth()->user()),
            'canManageWhatsApp' => Gate::allows('channels.manage'),
            'signup' => $this->service->signupConfig(),
        ]);
    }

    public function connect(ConnectMetaBusinessRequest $request): RedirectResponse
    {
        $this->service->connect(auth()->user(), $request->validated('code'));

        return redirect()->route('user.meta-social.business.show')
            ->with('status', __('Meta Business connected. Choose what to turn on.'));
    }

    public function refresh(): RedirectResponse
    {
        $this->service->refresh(auth()->user());

        return redirect()->route('user.meta-social.business.show')->with('status', __('Assets refreshed from Meta.'));
    }

    public function activate(ActivateMetaBusinessAssetsRequest $request): RedirectResponse
    {
        $result = $this->service->activate(auth()->user(), $request->validated(), Gate::allows('channels.manage'));

        if ($result['channels'] === [] && $result['errors'] === []) {
            return redirect()->route('user.channels.index')->with('status', __('Meta Business selection saved.'));
        }

        foreach ($result['channels'] as $channel) {
            $this->readiness->check($channel);
        }

        return redirect()->route('user.meta-social.business.result')->with(self::RESULT_KEY, [
            'channel_ids' => collect($result['channels'])->pluck('id')->unique()->values()->all(),
            'errors' => $result['errors'],
        ]);
    }

    /**
     * What happened for each asset the user turned on, and what each channel still needs.
     */
    public function result(WorkspaceResolver $workspaces): View|RedirectResponse
    {
        $result = session(self::RESULT_KEY);

        if (! is_array($result)) {
            return redirect()->route('user.channels.index');
        }

        session()->keep([self::RESULT_KEY]);

        $channels = ChannelAccount::query()
            ->where('workspace_id', $workspaces->current(auth()->user())->id)
            ->whereIn('id', $result['channel_ids'] ?? [])
            ->get();

        return view('meta-social::user.business-result', [
            'channels' => $channels->map(fn (ChannelAccount $channel): array => [
                'channel' => $channel,
                'report' => $this->readiness->report($channel),
                'manage_url' => $channel->provider === 'whatsapp' ? route('user.whatsapp-cloud.channel-setup') : route('user.meta-social.setup').'#'.$channel->provider,
            ]),
            'activationErrors' => $result['errors'] ?? [],
        ]);
    }

    public function disconnect(): RedirectResponse
    {
        $this->service->disconnect(auth()->user());

        return redirect()->route('user.channels.index')
            ->with('status', __('Meta Business login removed. Connected channels keep working.'));
    }
}
