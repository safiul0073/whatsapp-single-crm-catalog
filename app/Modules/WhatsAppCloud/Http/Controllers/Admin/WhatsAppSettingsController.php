<?php

namespace App\Modules\WhatsAppCloud\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\WhatsAppCloud\Http\Requests\UpdateWhatsAppSettingsRequest;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WhatsAppSettingsController extends Controller
{
    public function index(WhatsAppSettingsService $settings): View
    {
        return view('whatsapp-cloud::admin.settings', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request, WhatsAppSettingsService $settings): RedirectResponse
    {
        $payload = $request->validated('settings');
        $payload['whatsapp_auto_sync_templates'] = $request->boolean('settings.whatsapp_auto_sync_templates');
        $payload['whatsapp_auto_sync_phone_numbers'] = $request->boolean('settings.whatsapp_auto_sync_phone_numbers');
        $payload['whatsapp_embedded_signup_enabled'] = $request->boolean('settings.whatsapp_embedded_signup_enabled');

        $settings->update($payload);

        return back()->with('success', __('WhatsApp settings updated successfully.'));
    }
}
