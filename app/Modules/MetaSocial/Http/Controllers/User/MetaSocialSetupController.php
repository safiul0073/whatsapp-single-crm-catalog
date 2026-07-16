<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Http\Requests\ConnectMetaSocialChannelRequest;
use App\Modules\MetaSocial\Services\MetaSocialSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MetaSocialSetupController extends Controller
{
    public function __construct(protected MetaSocialSetupService $service) {}

    public function index(): View
    {
        return view('meta-social::user.setup', $this->service->pageData(auth()->user()));
    }

    public function embedded(ConnectMetaSocialChannelRequest $request, string $provider): RedirectResponse
    {
        $channel = $this->service->connectFromEmbeddedSignup(auth()->user(), $provider, $request->validated());

        return back()->with($channel->status->value === 'error' ? 'error' : 'status', $channel->status->value === 'error'
            ? __(':channel saved, but Meta validation failed. Check the channel warning.', ['channel' => $channel->name])
            : __(':channel connected successfully.', ['channel' => $channel->name]));
    }

    public function disconnect(ChannelAccount $channel): RedirectResponse
    {
        $this->service->disconnect(auth()->user(), $channel);

        return back()->with('status', __('Channel disconnected.'));
    }
}
