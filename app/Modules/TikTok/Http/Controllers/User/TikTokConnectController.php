<?php

namespace App\Modules\TikTok\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\TikTok\Services\TikTokConnectService;
use App\Modules\TikTok\Services\TikTokSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TikTokConnectController extends Controller
{
    public function __construct(
        protected TikTokConnectService $service,
        protected TikTokSettingsService $settings,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('tiktok::user.index', [
            'accounts' => ChannelAccount::query()->where('workspace_id', $workspace->id)->where('provider', 'tiktok')->latest()->get(),
            'ready' => $this->settings->ready(),
            'loginEnabled' => $this->settings->loginEnabled(),
            'shopReady' => $this->settings->shopReady(),
        ]);
    }

    public function connect(): RedirectResponse
    {
        return redirect()->away($this->service->authorizationUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('user.tiktok.index')->with('error', __('TikTok sign-in was cancelled.'));
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect()->route('user.tiktok.index')->with('error', __('TikTok did not return a sign-in code.'));
        }

        try {
            $channel = $this->service->connect($request->user(), $code, $state);
        } catch (ValidationException $exception) {
            return redirect()->route('user.tiktok.index')->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('user.tiktok.index')->with('status', __(':channel connected successfully.', ['channel' => $channel->name]));
    }

    public function disconnect(Request $request, ChannelAccount $channel): RedirectResponse
    {
        $this->service->disconnect($request->user(), $channel);

        return redirect()->route('user.tiktok.index')->with('status', __('Channel disconnected.'));
    }
}
