<?php

namespace App\Modules\MetaSocial\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MetaSocial\Http\Requests\UpdateMetaSocialSettingsRequest;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MetaSocialSettingsController extends Controller
{
    public function __construct(protected MetaSocialSettingsService $settings) {}

    public function index(): View
    {
        return view('meta-social::admin.settings', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(UpdateMetaSocialSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings());

        return back()->with('status', __('Meta social settings updated.'));
    }
}
