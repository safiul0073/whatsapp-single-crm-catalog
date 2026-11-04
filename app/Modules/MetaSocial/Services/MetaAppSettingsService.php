<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The single Meta app shared by WhatsApp, Messenger, Instagram and Ads. Product settings services
 * read these values first and fall back to their legacy per-product keys while those still exist.
 */
class MetaAppSettingsService
{
    public const DEFAULT_GRAPH_API_VERSION = 'v24.0';

    protected const CACHE_KEY = 'meta_app_settings';

    protected array $defaults = [
        'meta_app_id' => '',
        'meta_app_secret' => '',
        'meta_graph_api_version' => '',
        'meta_default_verify_token' => '',
        'meta_webhook_base_url' => '',
        'meta_business_config_id' => '',
        'meta_ads_config_id' => '',
        'meta_threads_app_id' => '',
        'meta_threads_app_secret' => '',
    ];

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 86400, function (): array {
            $values = Setting::query()
                ->whereIn('key', array_keys($this->defaults))
                ->pluck('value', 'key')
                ->toArray();

            return array_replace($this->defaults, $values);
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * The shared value, or null when it is blank so callers can fall back to a legacy key.
     */
    public function filled(string $key): ?string
    {
        $value = trim((string) $this->get($key, ''));

        return $value !== '' ? $value : null;
    }

    public function update(array $settings): void
    {
        foreach (array_intersect_key($settings, $this->defaults) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function normalizeGraphApiVersion(?string $version, string $fallback = self::DEFAULT_GRAPH_API_VERSION): string
    {
        $version = trim((string) $version, " \t\n\r\0\x0B/");

        return preg_match('/^v\d+\.\d+$/', $version) === 1 ? $version : $fallback;
    }
}
