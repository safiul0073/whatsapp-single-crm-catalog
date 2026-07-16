<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class MetaSocialSettingsService
{
    protected const CACHE_KEY = 'meta_social_settings';

    protected array $defaults = [
        'meta_social_graph_api_version' => 'v20.0',
        'meta_social_app_id' => '',
        'meta_social_app_secret' => '',
        'meta_social_default_verify_token' => '',
        'meta_social_webhook_base_url' => '',
        'meta_social_embedded_signup_enabled' => '0',
        'meta_social_messenger_config_id' => '',
        'meta_social_instagram_config_id' => '',
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

    public function update(array $settings): void
    {
        foreach (array_intersect_key($settings, $this->defaults) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function graphApiVersion(): string
    {
        return trim((string) $this->get('meta_social_graph_api_version', 'v20.0'), '/');
    }

    public function webhookBaseUrl(): ?string
    {
        $value = trim((string) $this->get('meta_social_webhook_base_url', ''));

        return $value !== '' ? rtrim($value, '/') : null;
    }

    public function enabled(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function embeddedSignupReady(string $provider): bool
    {
        $configKey = $provider === 'instagram' ? 'meta_social_instagram_config_id' : 'meta_social_messenger_config_id';

        return $this->enabled('meta_social_embedded_signup_enabled')
            && trim((string) $this->get('meta_social_app_id', '')) !== ''
            && trim((string) $this->get('meta_social_app_secret', '')) !== ''
            && trim((string) $this->get($configKey, '')) !== '';
    }
}
