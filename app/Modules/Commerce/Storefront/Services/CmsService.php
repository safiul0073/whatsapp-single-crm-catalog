<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSection;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use Illuminate\Support\Facades\Validator;

class CmsService
{
    public const TYPES = ['hero_slider', 'category_grid', 'product_carousel', 'promo_banner', 'cta', 'content_grid', 'brand_strip', 'testimonials', 'rich_text', 'newsletter', 'countdown_deal'];

    public function validateSection(array $section): array
    {
        Validator::make($section, ['type' => 'required|in:'.implode(',', self::TYPES), 'config' => 'required|array', 'active' => 'boolean', 'visibility' => 'in:all,desktop,mobile', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at'])->validate();
        $common = ['title' => 'nullable|string|max:200', 'subtitle' => 'nullable|string|max:1000', 'button_text' => 'nullable|string|max:80', 'button_link' => ['nullable', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https://)#']];
        $rules = match ($section['type']) {
            'hero_slider' => ['slider_id' => 'required|integer'],
            'product_carousel' => ['source' => 'required|in:featured,new-arrivals,deals,category,brand,manual', 'category' => 'nullable|string|max:150', 'brand' => 'nullable|string|max:150', 'product_ids' => 'nullable|array|max:24', 'product_ids.*' => 'integer', 'limit' => 'nullable|integer|min:1|max:24'],
            'promo_banner','content_grid','testimonials' => ['items' => 'required|array|max:12', 'items.*.title' => 'nullable|string|max:200', 'items.*.text' => 'nullable|string|max:2000', 'items.*.image' => 'nullable|url:http,https', 'items.*.link' => ['nullable', 'string', 'regex:#^(?:/(?!/)|https://)#']],
            'rich_text' => ['text' => 'required|string|max:30000'],
            'countdown_deal' => ['ends_at' => 'required|date', 'image' => 'nullable|url:http,https'],
            default => [],
        };
        $section['config'] = Validator::make($section['config'], array_merge($common, $rules))->validate();

        return $section;
    }

    public function settings(int $workspace): array
    {
        $settings = array_replace(['name' => 'Store', 'primary' => '#0866FF', 'hover' => '#0055D4', 'footer_color' => '#0A2540', 'canvas' => '#F0F2F5', 'logo' => null, 'announcement' => '', 'contact_email' => '', 'contact_phone' => '', 'footer_text' => '', 'socials' => []], array_diff_key(StorefrontSetting::query()->where('workspace_id', $workspace)->first()?->config ?? [], array_flip(['password_login', 'blog_ids', 'faq_ids'])));
        if (! empty($settings['logo']) && ! str_starts_with($settings['logo'], 'http')) {
            $settings['logo'] = rtrim(config('app.url'), '/').'/storage/'.ltrim($settings['logo'], '/');
        }

        return $settings;
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
        abort_unless(strlen($secret) >= 32, 503, 'Configure the storefront preview secret.');
        $data = rtrim(strtr(base64_encode(json_encode(['workspace' => $workspace, 'slug' => $slug, 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $data.'.'.hash_hmac('sha256', $data, $secret);
    }

    public function verifyPreview(int $workspace, string $slug, string $token): bool
    {
        $secret = (string) config('storefront.preview_secret');
        [$data,$sig] = array_pad(explode('.', $token, 2), 2, '');
        if (strlen($secret) < 32 || ! hash_equals(hash_hmac('sha256', $data, $secret), $sig)) {
            return false;
        }
        $payload = json_decode(base64_decode(strtr($data, '-_', '+/')), true);

        return is_array($payload) && ($payload['workspace'] ?? null) === $workspace && ($payload['slug'] ?? null) === $slug && ($payload['expires'] ?? 0) > time();
    }
}
