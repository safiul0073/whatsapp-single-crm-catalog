<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class WhatsAppSettingsService
{
    protected const CACHE_KEY = 'whatsapp_cloud_settings';

    protected array $defaults = [
        'whatsapp_graph_api_version' => 'v20.0',
        'whatsapp_meta_app_id' => '',
        'whatsapp_meta_app_secret' => '',
        'whatsapp_default_verify_token' => '',
        'whatsapp_webhook_base_url' => '',
        'whatsapp_auto_sync_templates' => '1',
        'whatsapp_auto_sync_phone_numbers' => '1',
        'whatsapp_embedded_signup_enabled' => '0',
        'whatsapp_embedded_signup_config_id' => '',
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
        return trim((string) $this->get('whatsapp_graph_api_version', 'v20.0'), '/');
    }

    public function webhookBaseUrl(): ?string
    {
        $value = trim((string) $this->get('whatsapp_webhook_base_url', ''));

        return $value !== '' ? rtrim($value, '/') : null;
    }

    public function enabled(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function embeddedSignupReady(): bool
    {
        return $this->enabled('whatsapp_embedded_signup_enabled')
            && trim((string) $this->get('whatsapp_meta_app_id', '')) !== ''
            && trim((string) $this->get('whatsapp_meta_app_secret', '')) !== ''
            && trim((string) $this->get('whatsapp_embedded_signup_config_id', '')) !== '';
    }
}
