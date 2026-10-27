<?php

namespace App\Modules\TikTok\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\TikTok\Http\Requests\UpdateTikTokSettingsRequest;
use App\Modules\TikTok\Services\TikTokSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TikTokSettingsController extends Controller
{
    public function __construct(protected TikTokSettingsService $settings) {}

    public function index(): View
    {
        return view('tiktok::admin.settings', [
            'settings' => $this->settings->all(),
            'secretSaved' => $this->settings->secretSaved(),
            'shopSecretSaved' => $this->settings->shopSecretSaved(),
            'redirectUri' => route('user.tiktok.callback'),
        ]);
    }

    public function update(UpdateTikTokSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings());

        return back()->with('status', __('TikTok settings updated.'));
    }
}
