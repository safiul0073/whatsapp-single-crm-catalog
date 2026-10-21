<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSection;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CmsService
{
    public const TYPES = ['hero_slider', 'category_grid', 'category_carousel', 'product_carousel', 'promo_banner', 'cta', 'content_grid', 'brand_strip', 'testimonials', 'rich_text', 'newsletter', 'countdown_deal', 'benefit_banner'];

    public const SECRET_KEYS = ['platform_secret', 'session_secret', 'revalidation_secret', 'preview_secret'];

    public function validateSection(array $section): array
    {
        Validator::make($section, ['type' => 'required|in:'.implode(',', self::TYPES), 'config' => 'required|array', 'active' => 'boolean', 'visibility' => 'in:all,desktop,mobile', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at'])->validate();
        $common = ['title' => 'nullable|string|max:200', 'subtitle' => 'nullable|string|max:1000', 'button_text' => 'nullable|string|max:80', 'button_link' => ['nullable', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https?://|#)#']];
        $rules = match ($section['type']) {
            'hero_slider' => ['slider_id' => 'required|integer'],
            'product_carousel' => ['source' => 'required|in:featured,new-arrivals,deals,category,brand,manual', 'category' => 'nullable|string|max:150', 'brand' => 'nullable|string|max:150', 'product_ids' => 'nullable|array|max:24', 'product_ids.*' => 'integer', 'limit' => 'nullable|integer|min:1|max:24'],
            'promo_banner','content_grid','testimonials' => ['items' => 'nullable|array|max:12', 'items.*.title' => 'nullable|string|max:200', 'items.*.text' => 'nullable|string|max:2000', 'items.*.image' => ['nullable', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https?://)#'], 'items.*.link' => ['nullable', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https?://|#)#']],
            'benefit_banner' => ['items' => 'nullable|array|max:12', 'items.*.title' => 'nullable|string|max:200', 'items.*.text' => 'nullable|string|max:2000'],
            'rich_text' => ['text' => 'required|string|max:30000'],
            'countdown_deal' => ['ends_at' => 'required|date', 'image' => ['nullable', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https?://)#']],
            default => [],
        };
        $section['config'] = Validator::make($section['config'], array_merge($common, $rules))->validate();

        return $section;
    }

    public const CHAT_CHANNEL_DEFAULTS = [
        'enabled' => false, 'position' => 'bottom-right', 'layout' => 'stacked', 'tooltip' => 'Need help? Chat with us',
        'whatsapp' => ['enabled' => false, 'phone' => '', 'message' => ''],
        'messenger' => ['enabled' => false, 'page_id' => ''],
        'telegram' => ['enabled' => false, 'username' => ''],
        'custom_chatbot' => ['enabled' => false, 'source' => 'marketing_chatbot', 'title' => 'AI Assistant', 'icon' => 'bot', 'widget_token' => '', 'script_url' => '', 'direct_url' => ''],
    ];

    public function settings(int $workspace): array
    {
        $settings = array_replace(
            [
                'name' => 'Fashion Fiest',
                'primary' => '#0866FF',
                'hover' => '#0055D4',
                'footer_color' => '#0A2540',
                'canvas' => '#F0F2F5',
                'logo' => null,
                'logo_height' => 68,
                'logo_show_name' => false,
                'announcement' => '',
                'contact_email' => 'sales@fashionfiest.com',
                'contact_phone' => '+1 (800) 555-0199',
                'footer_text' => 'Direct from factory wholesale and retail streetwear, premium heavyweight hoodies, tees, and denim.',
                'socials' => [],
                'app_download_enabled' => true,
                'app_download_title' => 'Experience On Mobile',
                'app_download_subtitle' => 'Download our mobile apps for faster checkout and exclusive drops.',
                'app_ios_url' => '',
                'app_android_url' => '',
            ],
            array_diff_key(StorefrontSetting::query()->where('workspace_id', $workspace)->first()?->config ?? [], array_flip(['password_login', 'blog_ids', 'faq_ids', 'deployment']))
        );
        if (! empty($settings['logo']) && ! str_starts_with($settings['logo'], 'http')) {
            $clean = preg_replace('#^/?storage/#', '', ltrim($settings['logo'], '/'));
            $settings['logo'] = rtrim(config('app.url'), '/').'/storage/'.$clean;
        }
        if (! empty($settings['app_ios_url']) && ! str_starts_with($settings['app_ios_url'], 'http') && ! str_starts_with($settings['app_ios_url'], '#')) {
            $clean = preg_replace('#^/?storage/#', '', ltrim($settings['app_ios_url'], '/'));
            $settings['app_ios_url'] = rtrim(config('app.url'), '/').'/storage/'.$clean;
        }
        if (! empty($settings['app_android_url']) && ! str_starts_with($settings['app_android_url'], 'http') && ! str_starts_with($settings['app_android_url'], '#')) {
            $clean = preg_replace('#^/?storage/#', '', ltrim($settings['app_android_url'], '/'));
            $settings['app_android_url'] = rtrim(config('app.url'), '/').'/storage/'.$clean;
        }
        $settings['app_download_enabled'] = (bool) ($settings['app_download_enabled'] ?? true);
        $settings['logo_height'] = max(32, min(120, (int) ($settings['logo_height'] ?? 68)));
        $settings['logo_show_name'] = (bool) ($settings['logo_show_name'] ?? false);

        $settings['chat_channels'] = array_replace_recursive(self::CHAT_CHANNEL_DEFAULTS, $settings['chat_channels'] ?? []);
        $bot = &$settings['chat_channels']['custom_chatbot'];
        if ($bot['enabled'] && $bot['source'] === 'marketing_chatbot') {
            $widget = ChatbotWidget::query()
                ->where('workspace_id', $workspace)->where('public_token', $bot['widget_token'])->where('is_active', true)->first();
            $bot['enabled'] = $widget !== null;
            $bot['script_url'] = $widget ? route('widgets.chatbot.loader', $widget->public_token) : '';
        }

        return $settings;
    }

    /**
     * Default navigation menu items per slot when a store has not saved custom items yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultMenuItems(string $slot): array
    {
        return match ($slot) {
            'header', 'mobile' => [
                ['label' => 'Brand', 'href' => '/products', 'mega' => 'brand', 'badge' => '', 'children' => []],
                ['label' => 'Kids', 'href' => '/category/kids', 'mega' => 'kids', 'badge' => '', 'children' => []],
                ['label' => 'Man', 'href' => '/category/men', 'mega' => 'man', 'badge' => '', 'children' => []],
                ['label' => 'Woman', 'href' => '/category/women', 'mega' => 'woman', 'badge' => '', 'children' => []],
                ['label' => 'Accessories', 'href' => '/category/accessories', 'mega' => 'accessories', 'badge' => '', 'children' => []],
                ['label' => 'New Arrivals', 'href' => '/collections/new-arrivals', 'mega' => 'none', 'badge' => '', 'children' => []],
                ['label' => 'Order Tracking', 'href' => '/track-order', 'mega' => 'none', 'badge' => '', 'children' => []],
            ],
            'footer' => [
                ['label' => 'All Products', 'href' => '/products', 'mega' => 'none', 'badge' => '', 'children' => []],
                ['label' => 'New Arrivals', 'href' => '/collections/new-arrivals', 'mega' => 'none', 'badge' => '', 'children' => []],
                ['label' => 'Special Deals', 'href' => '/collections/deals', 'mega' => 'none', 'badge' => '', 'children' => []],
                ['label' => 'Custom Packs', 'href' => '/packs', 'mega' => 'none', 'badge' => 'MIX', 'children' => []],
                ['label' => 'Journal', 'href' => '/blog', 'mega' => 'none', 'badge' => '', 'children' => []],
            ],
            default => [],
        };
    }

    /**
     * Resolve or auto-generate the workspace's Storefront deployment credentials and endpoints.
     *
     * @return array{
     *     marketing_api_url: string,
     *     media_origin: string,
     *     storefront_url: string,
     *     root_domain: string,
     *     dns_target: string,
     *     platform_secret: string,
     *     session_secret: string,
     *     revalidation_secret: string,
     *     preview_secret: string
     * }
     */
    public function credentials(int $workspace, bool $autoGenerate = true): array
    {
        $record = StorefrontSetting::query()->where('workspace_id', $workspace)->first();
        $config = (array) ($record?->config ?? []);
        $deployment = (array) ($config['deployment'] ?? []);
        $dirty = false;

        foreach (self::SECRET_KEYS as $key) {
            $stored = (string) ($deployment[$key] ?? '');
            if (strlen($stored) >= 32) {
                continue;
            }

            $envVal = (string) config("storefront.{$key}", '');
            if (strlen($envVal) >= 32) {
                $deployment[$key] = $envVal;

                continue;
            }

            if ($autoGenerate) {
                $deployment[$key] = bin2hex(random_bytes(32));
                $dirty = true;
            } else {
                $deployment[$key] = '';
            }
        }

        if ($dirty) {
            $config['deployment'] = $deployment;
            StorefrontSetting::query()->updateOrCreate(
                ['workspace_id' => $workspace],
                ['config' => $config]
            );
        }

        $defaultApiUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');

        return [
            'marketing_api_url' => rtrim((string) ($deployment['marketing_api_url'] ?? $defaultApiUrl), '/') ?: $defaultApiUrl,
            'media_origin' => rtrim((string) ($deployment['media_origin'] ?? ($deployment['marketing_api_url'] ?? $defaultApiUrl)), '/') ?: $defaultApiUrl,
            'storefront_url' => rtrim((string) (($deployment['storefront_url'] ?? '') ?: config('storefront.url', 'http://localhost:3000')), '/'),
            'root_domain' => (string) (($deployment['root_domain'] ?? '') ?: config('storefront.root_domain', 'localhost')),
            'dns_target' => (string) (($deployment['dns_target'] ?? '') ?: config('storefront.dns_target', '')),
            'platform_secret' => (string) ($deployment['platform_secret'] ?? ''),
            'session_secret' => (string) ($deployment['session_secret'] ?? ''),
            'revalidation_secret' => (string) ($deployment['revalidation_secret'] ?? ''),
            'preview_secret' => (string) ($deployment['preview_secret'] ?? ''),
        ];
    }

    /**
     * Generate or rotate one or all cryptographic secrets for the storefront.
     */
    public function rotateCredentials(int $workspace, string $target = 'all'): array
    {
        $record = StorefrontSetting::query()->where('workspace_id', $workspace)->first();
        $config = (array) ($record?->config ?? []);
        $deployment = (array) ($config['deployment'] ?? []);

        $keysToRotate = $target === 'all'
            ? self::SECRET_KEYS
            : array_values(array_intersect(self::SECRET_KEYS, [$target]));

        foreach (self::SECRET_KEYS as $key) {
            if (in_array($key, $keysToRotate, true) || strlen((string) ($deployment[$key] ?? '')) < 32) {
                $deployment[$key] = bin2hex(random_bytes(32));
            }
        }

        $config['deployment'] = $deployment;
        StorefrontSetting::query()->updateOrCreate(
            ['workspace_id' => $workspace],
            ['config' => $config]
        );

        return $this->credentials($workspace, false);
    }

    /**
     * Save storefront deployment URLs and domain target settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDeploymentEndpoints(int $workspace, array $data): array
    {
        $record = StorefrontSetting::query()->where('workspace_id', $workspace)->first();
        $config = (array) ($record?->config ?? []);
        $deployment = (array) ($config['deployment'] ?? []);

        foreach (['marketing_api_url', 'media_origin', 'storefront_url', 'root_domain', 'dns_target'] as $field) {
            if (array_key_exists($field, $data)) {
                $deployment[$field] = is_string($data[$field]) ? trim($data[$field]) : '';
            }
        }

        $config['deployment'] = $deployment;
        StorefrontSetting::query()->updateOrCreate(
            ['workspace_id' => $workspace],
            ['config' => $config]
        );

        // 1. Sync system domain host with root_domain if provided
        if (! empty($deployment['root_domain'])) {
            $sysDomain = StoreDomain::query()->where('workspace_id', $workspace)->where('type', 'system')->first();
            if ($sysDomain) {
                try {
                    $newHost = DomainResolver::normalize($sysDomain->handle.'.'.$deployment['root_domain']);
                    if ($newHost !== $sysDomain->host && ! StoreDomain::query()->where('host', $newHost)->whereKeyNot($sysDomain->id)->exists()) {
                        $sysDomain->update(['host' => $newHost]);
                    }
                } catch (\Throwable) {
                }
            }
        }

        // 2. Automatically register storefront_url host as an active domain for this store
        if (! empty($deployment['storefront_url'])) {
            $parsedHost = parse_url($deployment['storefront_url'], PHP_URL_HOST);
            if ($parsedHost && ! in_array(strtolower($parsedHost), ['localhost', '127.0.0.1'], true)) {
                try {
                    $sfHost = DomainResolver::normalize($parsedHost);
                    $existing = StoreDomain::query()->where('host', $sfHost)->first();
                    if ($existing && $existing->workspace_id === $workspace) {
                        StoreDomain::query()->where('workspace_id', $workspace)->update(['is_primary' => false]);
                        $existing->update([
                            'status' => 'active',
                            'is_primary' => true,
                            'verified_at' => $existing->verified_at ?? now(),
                        ]);
                    } elseif (! $existing) {
                        StoreDomain::query()->where('workspace_id', $workspace)->update(['is_primary' => false]);
                        StoreDomain::query()->create([
                            'workspace_id' => $workspace,
                            'host' => $sfHost,
                            'handle' => $sfHost,
                            'type' => 'custom',
                            'is_primary' => true,
                            'status' => 'active',
                            'verification_token' => Str::random(48),
                            'verified_at' => now(),
                        ]);
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $this->credentials($workspace, true);
    }

    public function page(int $workspace, string $slug, bool $preview = false): array
    {
        $p = StorefrontPage::query()->where('workspace_id', $workspace)->where('slug', $slug)->firstOrFail();
        abort_unless($preview || ($p->status === 'published' && $p->published_at?->isPast()), 404);
        $sections = $preview ? StorefrontSection::query()->where('workspace_id', $workspace)->where('page_id', $p->id)->orderBy('position')->get()->toArray() : ($p->published_content['sections'] ?? []);
        $sections = array_values(array_filter($sections, fn ($s) => ($s['active'] ?? true) && (empty($s['starts_at']) || now()->gte($s['starts_at'])) && (empty($s['ends_at']) || now()->lt($s['ends_at']))));
        foreach ($sections as &$s) {
            $config = $s['config'];
            $s['data'] = match ($s['type']) {
                'hero_slider' => $this->normalizeSlides(StorefrontSlider::query()->where('workspace_id', $workspace)->find($config['slider_id'] ?? 0)?->slides ?? []),
                'product_carousel' => $this->products($workspace, $config),
                'category_grid' => Category::query()->where('workspace_id', $workspace)->where('is_active', true)->whereNull('parent_id')->get()->toArray(),
                'brand_strip' => Brand::query()->where('workspace_id', $workspace)->where('is_active', true)->get()->toArray(),
                default => [],
            };
        }

        return ['slug' => $p->slug, 'title' => $preview ? $p->title : ($p->published_content['title'] ?? $p->title), 'seo' => $preview ? $p->seo : ($p->published_content['seo'] ?? []), 'sections' => $sections];
    }

    public function normalizeSlides(array $slides): array
    {
        $appUrl = rtrim(config('app.url'), '/');
        foreach ($slides as &$slide) {
            foreach (['image', 'mobile_image', 'video'] as $key) {
                if (! empty($slide[$key]) && ! str_starts_with($slide[$key], 'http://') && ! str_starts_with($slide[$key], 'https://')) {
                    $slide[$key] = $appUrl.'/storage/'.ltrim($slide[$key], '/');
                }
            }
        }

        return $slides;
    }

    private function products(int $workspace, array $config): array
    {
        $q = app(CatalogService::class)->query($workspace, array_intersect_key($config, array_flip(['category', 'brand'])));
        if (($config['source'] ?? '') === 'manual') {
            $q->whereIn('id', $config['product_ids'] ?? []);
        } elseif (($config['source'] ?? '') === 'featured') {
            $ids = StorefrontContent::query()->where('workspace_id', $workspace)->where('type', 'collection')->where('slug', 'featured')->where('published_at', '<=', now())->first()?->data['product_ids'] ?? [];
            $q->whereIn('id', $ids);
        } elseif (($config['source'] ?? '') === 'deals') {
            $q->whereHas('variants', fn ($variants) => $variants->where('status', 'active')->whereColumn('compare_at_price', '>', 'price'));
        }

        return ProductResource::collection($q->latest('id')->limit($config['limit'] ?? 8)->get())->resolve();
    }

    public function previewToken(int $workspace, string $slug): string
    {
        $secret = (string) config('storefront.preview_secret');
        if (strlen($secret) < 32) {
            $secret = $this->credentials($workspace, false)['preview_secret'];
        }
        abort_unless(strlen($secret) >= 32, 503, 'Configure the storefront preview secret.');
        $data = rtrim(strtr(base64_encode(json_encode(['workspace' => $workspace, 'slug' => $slug, 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $data.'.'.hash_hmac('sha256', $data, $secret);
    }

    public function verifyPreview(int $workspace, string $slug, string $token): bool
    {
        [$data, $sig] = array_pad(explode('.', $token, 2), 2, '');
        $candidates = array_filter([
            (string) config('storefront.preview_secret'),
            $this->credentials($workspace, false)['preview_secret'],
        ], fn (string $s): bool => strlen($s) >= 32);

        $matched = false;
        foreach ($candidates as $secret) {
            if (hash_equals(hash_hmac('sha256', $data, $secret), $sig)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            return false;
        }

        $payload = json_decode(base64_decode(strtr($data, '-_', '+/')), true);

        return is_array($payload) && ($payload['workspace'] ?? null) === $workspace && ($payload['slug'] ?? null) === $slug && ($payload['expires'] ?? 0) > time();
    }
}
