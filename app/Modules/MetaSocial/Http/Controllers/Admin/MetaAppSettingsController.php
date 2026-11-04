<?php

namespace App\Modules\MetaSocial\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MetaSocial\Http\Requests\UpdateMetaAppSettingsRequest;
use App\Modules\MetaSocial\Services\MetaAppSettingsService;
use App\Modules\MetaSocial\Services\MetaPlatformCheckService;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MetaAppSettingsController extends Controller
{
    public function __construct(
        protected MetaAppSettingsService $metaApp,
        protected WhatsAppSettingsService $whatsapp,
        protected MetaSocialSettingsService $social,
    ) {}

    public function index(): View
    {
        $whatsapp = $this->whatsapp->all();
        $social = $this->social->all();
        $legacyAppIds = collect([$whatsapp['whatsapp_meta_app_id'] ?? '', $social['meta_social_app_id'] ?? ''])
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique();

        return view('meta-social::admin.meta-app', [
            'settings' => array_merge($whatsapp, $social, [
                'meta_app_id' => $this->whatsapp->get('whatsapp_meta_app_id') ?: $this->social->get('meta_social_app_id'),
                'meta_app_secret' => $this->whatsapp->get('whatsapp_meta_app_secret') ?: $this->social->get('meta_social_app_secret'),
                'meta_graph_api_version' => $this->metaApp->filled('meta_graph_api_version') ?? $this->whatsapp->graphApiVersion(),
                'meta_default_verify_token' => $this->whatsapp->get('whatsapp_default_verify_token') ?: $this->social->get('meta_social_default_verify_token'),
                'meta_webhook_base_url' => $this->whatsapp->get('whatsapp_webhook_base_url') ?: $this->social->get('meta_social_webhook_base_url'),
                'meta_business_config_id' => $this->metaApp->get('meta_business_config_id'),
                'meta_ads_config_id' => $this->metaApp->get('meta_ads_config_id'),
                'meta_threads_app_id' => $this->metaApp->get('meta_threads_app_id'),
                'meta_threads_app_secret' => $this->metaApp->get('meta_threads_app_secret'),
            ]),
            'platformCheck' => app(MetaPlatformCheckService::class)->last(),
            'platformPermissions' => MetaPlatformCheckService::PERMISSIONS,
            'hasSeparateLegacyApps' => $this->metaApp->filled('meta_app_id') === null && $legacyAppIds->count() > 1,
        ]);
    }

    public function check(MetaPlatformCheckService $checks): RedirectResponse
    {
        $result = $checks->run();
        $failing = collect($result['items'])->where('ok', false)->count();

        return back()->with($failing === 0 ? 'success' : 'error', $failing === 0
            ? __('Platform check passed. Customers can connect with Meta.')
            : trans_choice(':count item needs attention before customers can connect.|:count items need attention before customers can connect.', $failing, ['count' => $failing]));
    }

    public function update(UpdateMetaAppSettingsRequest $request): RedirectResponse
    {
        $settings = $request->grouped();

        $this->metaApp->update($settings['meta_app']);
        $this->whatsapp->update($settings['whatsapp']);
        $this->social->update($settings['social']);

        return back()->with('success', __('Meta app settings updated.'));
    }
}
