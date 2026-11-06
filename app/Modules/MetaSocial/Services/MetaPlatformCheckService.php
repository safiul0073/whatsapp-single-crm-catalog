<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Admin-side checklist: what the platform owner must finish in Meta for Developers before
 * customers can connect. Runs only when an admin asks, and the result is cached for display.
 */
class MetaPlatformCheckService
{
    protected const CACHE_KEY = 'meta_platform_check';

    public const PERMISSIONS = [
        'whatsapp_business_management' => 'WhatsApp: manage numbers and templates',
        'whatsapp_business_messaging' => 'WhatsApp: send and receive messages',
        'business_management' => 'Read the business portfolio (Connect with Meta)',
        'pages_show_list' => 'List Facebook Pages',
        'pages_messaging' => 'Messenger: send and receive messages',
        'pages_manage_metadata' => 'Subscribe Pages to webhooks',
        'pages_read_engagement' => 'Read Page data and Facebook Live comments',
        'pages_manage_posts' => 'Publish and schedule Page posts, and start/end Facebook Live videos',
        'instagram_content_publish' => 'Publish and schedule Instagram posts',
        'publish_video' => 'Facebook Live: stream video to the Page',
        'human_agent' => 'Messenger/Instagram: team replies up to 7 days after the last customer message',
        'Business Asset User Profile Access' => 'Messenger/Instagram: show customer names and photos in the inbox',
        'instagram_basic' => 'Instagram: read the account',
        'instagram_manage_messages' => 'Instagram: send and receive DMs',
        'instagram_manage_comments' => 'Instagram: reply to comments',
        'catalog_management' => 'Catalogs for WhatsApp commerce and product sync',
        'Live Video API' => 'Facebook Live from the storefront',
        'ads_read' => 'Meta Ads reporting (with the Marketing API access tier)',
        'ads_management' => 'Meta Ads: create, start, pause and delete ads',
        'pages_manage_ads' => 'Meta Ads: run ads for the Page and boost its posts',
        'facebook_branded_content_ads_brand' => 'Partnership ads from Facebook creator posts',
        'instagram_branded_content_ads_brand' => 'Partnership ads from Instagram creator posts',
        'marketing_messages_messenger' => 'Messenger offer broadcasts to customers who tapped Get messages',
    ];

    public function __construct(
        protected MetaAppSettingsService $metaApp,
        protected WhatsAppSettingsService $whatsapp,
        protected MetaSocialSettingsService $social,
        protected MetaSocialClient $client,
        protected MetaErrorTranslator $errors,
    ) {}

    /**
     * @return array{checked_at: string, items: array<int, array{label: string, ok: bool|null, detail: string}>}|null
     */
    public function last(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    /**
     * @return array{checked_at: string, items: array<int, array{label: string, ok: bool|null, detail: string}>}
     */
    public function run(): array
    {
        $appId = (string) ($this->whatsapp->get('whatsapp_meta_app_id') ?: $this->social->get('meta_social_app_id'));
        $secret = (string) ($this->whatsapp->get('whatsapp_meta_app_secret') ?: $this->social->get('meta_social_app_secret'));
        $webhookBase = (string) ($this->whatsapp->get('whatsapp_webhook_base_url') ?: config('app.url'));
        $items = [];

        $items[] = $this->appCredentials($appId, $secret);
        $items[] = $this->configured(__('WhatsApp Embedded Signup configuration ID'), $this->whatsapp->embeddedSignupReady(), __('Needed for "Add a WhatsApp number". Create it in Facebook Login for Business → Configurations with the WhatsApp Embedded Signup login variation, and turn Embedded Signup on.'));
        $items[] = $this->configured(__('Business (all assets) configuration ID'), $this->metaApp->filled('meta_business_config_id') !== null, __('Needed for "Connect with Meta". Without it users connect Messenger and Instagram with their own configuration IDs instead.'));
        $items[] = $this->configured(__('Messenger configuration ID'), $this->social->embeddedSignupReady('messenger'), __('Optional when the Business configuration is set.'), optional: true);
        $items[] = $this->configured(__('Instagram configuration ID'), $this->social->embeddedSignupReady('instagram'), __('Optional when the Business configuration is set.'), optional: true);
        $items[] = $this->configured(__('Threads app ID and secret'), $this->metaApp->filled('meta_threads_app_id') !== null && $this->metaApp->filled('meta_threads_app_secret') !== null, __('Only needed if customers should connect Threads.'), optional: true);
        $items[] = [
            'label' => __('Webhook address uses HTTPS'),
            'ok' => str_starts_with($webhookBase, 'https://'),
            'detail' => str_starts_with($webhookBase, 'https://')
                ? $webhookBase
                : __('Meta only delivers webhooks and allows Facebook login on HTTPS. Current address: :url', ['url' => $webhookBase ?: __('not set')]),
        ];

        $result = ['checked_at' => now()->toISOString(), 'items' => $items];
        Cache::put(self::CACHE_KEY, $result, now()->addDays(7));

        return $result;
    }

    /**
     * @return array{label: string, ok: bool|null, detail: string}
     */
    protected function appCredentials(string $appId, string $secret): array
    {
        $label = __('Meta App ID and secret are valid');

        if ($appId === '' || $secret === '') {
            return ['label' => $label, 'ok' => false, 'detail' => __('Add the App ID and App Secret from Meta for Developers → App settings → Basic.')];
        }

        try {
            $response = Http::timeout(15)->get($this->client->graphUrl($appId), [
                'fields' => 'id,name',
                'access_token' => $appId.'|'.$secret,
            ]);
        } catch (\Throwable) {
            return ['label' => $label, 'ok' => null, 'detail' => __('Meta could not be reached. Try again.')];
        }

        return $response->successful()
            ? ['label' => $label, 'ok' => true, 'detail' => __('App: :name', ['name' => (string) $response->json('name', $appId)])]
            : ['label' => $label, 'ok' => false, 'detail' => $this->errors->summary($response->json(), __('Meta rejected the App ID or secret.'))];
    }

    /**
     * @return array{label: string, ok: bool|null, detail: string}
     */
    protected function configured(string $label, bool $ok, string $hint, bool $optional = false): array
    {
        return ['label' => $label, 'ok' => $ok ? true : ($optional ? null : false), 'detail' => $ok ? __('Set') : $hint];
    }
}
