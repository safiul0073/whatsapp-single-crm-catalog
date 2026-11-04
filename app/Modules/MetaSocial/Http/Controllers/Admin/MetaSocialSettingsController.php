<?php

namespace App\Modules\MetaSocial\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MetaSocial\Http\Requests\UpdateMetaSocialSettingsRequest;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use Illuminate\Http\RedirectResponse;

class MetaSocialSettingsController extends Controller
{
    public function __construct(protected MetaSocialSettingsService $settings) {}

    /**
     * Messenger and Instagram settings now live on the shared Meta App page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.meta-app.settings.index');
    }

    public function update(UpdateMetaSocialSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings());

        return back()->with('status', __('Meta social settings updated.'));
    }
}
