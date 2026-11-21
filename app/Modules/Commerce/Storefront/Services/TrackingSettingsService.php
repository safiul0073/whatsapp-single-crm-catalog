<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Ad-tracking settings stored under StorefrontSetting.config['tracking'].
 * Pixel IDs are public; the Conversions API token is encrypted and never leaves the backend.
 */
class TrackingSettingsService
{
    public const DEFAULTS = [
        'meta_pixel_id' => '',
        'tiktok_pixel_id' => '',
        'meta_capi_enabled' => false,
        'meta_test_event_code' => '',
        'tiktok_events_enabled' => false,
        'tiktok_test_event_code' => '',
        'consent_required' => false,
    ];

    /**
     * @return array<string, bool|string>
     */
    public function admin(int $workspace): array
    {
        $stored = $this->stored($workspace);

        return $this->normalized($stored) + [
            'meta_capi_token_set' => filled($stored['meta_capi_token'] ?? null),
            'tiktok_events_token_set' => filled($stored['tiktok_events_token'] ?? null),
        ];
    }

    /**
     * @return array{meta_pixel_id: string, tiktok_pixel_id: string, consent_required: bool}
     */
    public function public(int $workspace): array
    {
        return $this->publicFromConfig(['tracking' => $this->stored($workspace)]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{meta_pixel_id: string, tiktok_pixel_id: string, consent_required: bool}
     */
    public function publicFromConfig(array $config): array
    {
        $tracking = $this->normalized((array) ($config['tracking'] ?? []));

        return ['meta_pixel_id' => $tracking['meta_pixel_id'], 'tiktok_pixel_id' => $tracking['tiktok_pixel_id'], 'consent_required' => $tracking['consent_required']];
    }

    /**
     * Whether the store asks visitors for tracking consent before any pixel or server event.
     */
    public function consentRequired(int $workspace): bool
    {
        return $this->normalized($this->stored($workspace))['consent_required'];
    }

    /**
     * Server-side Meta Conversions API credentials, or null when the feature is off or incomplete.
     *
     * @return array{pixel_id: string, token: string, test_event_code: string}|null
     */
    public function metaConversions(int $workspace): ?array
    {
        $stored = $this->stored($workspace);
        $tracking = $this->normalized($stored);
        $token = $this->decryptToken($stored['meta_capi_token'] ?? null);

        if (! config('services.meta.capi_enabled', true) || ! $tracking['meta_capi_enabled'] || $tracking['meta_pixel_id'] === '' || $token === '') {
            return null;
        }

        return ['pixel_id' => $tracking['meta_pixel_id'], 'token' => $token, 'test_event_code' => $tracking['meta_test_event_code']];
    }

    /**
     * Server-side TikTok Events API credentials, or null when the feature is off or incomplete.
     *
     * @return array{pixel_code: string, token: string, test_event_code: string}|null
     */
    public function tiktokEvents(int $workspace): ?array
    {
        $stored = $this->stored($workspace);
        $tracking = $this->normalized($stored);
        $token = $this->decryptToken($stored['tiktok_events_token'] ?? null);

        if (! $tracking['tiktok_events_enabled'] || $tracking['tiktok_pixel_id'] === '' || $token === '') {
            return null;
        }

        return ['pixel_code' => $tracking['tiktok_pixel_id'], 'token' => $token, 'test_event_code' => $tracking['tiktok_test_event_code']];
    }

    /**
     * A blank token keeps the saved one; clear_token and clear_tiktok_token remove them.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(int $workspace, array $data): void
    {
        $setting = StorefrontSetting::query()->firstOrNew(['workspace_id' => $workspace]);
        $config = (array) ($setting->config ?? []);
        $stored = (array) ($config['tracking'] ?? []);

        $tracking = $this->normalized($data);
        $tracking['meta_capi_token'] = $this->secret($data, $stored, 'meta_capi_token', 'clear_token');
        $tracking['tiktok_events_token'] = $this->secret($data, $stored, 'tiktok_events_token', 'clear_tiktok_token');

        $config['tracking'] = $tracking;
        $setting->config = $config;
        $setting->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function stored(int $workspace): array
    {
        return (array) (StorefrontSetting::query()->where('workspace_id', $workspace)->first()?->config['tracking'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{meta_pixel_id: string, tiktok_pixel_id: string, meta_capi_enabled: bool, meta_test_event_code: string, tiktok_events_enabled: bool, tiktok_test_event_code: string, consent_required: bool}
     */
    protected function normalized(array $data): array
    {
        return [
            'meta_pixel_id' => trim((string) ($data['meta_pixel_id'] ?? '')),
            'tiktok_pixel_id' => trim((string) ($data['tiktok_pixel_id'] ?? '')),
            'meta_capi_enabled' => (bool) ($data['meta_capi_enabled'] ?? false),
            'meta_test_event_code' => trim((string) ($data['meta_test_event_code'] ?? '')),
            'tiktok_events_enabled' => (bool) ($data['tiktok_events_enabled'] ?? false),
            'tiktok_test_event_code' => trim((string) ($data['tiktok_test_event_code'] ?? '')),
            'consent_required' => (bool) ($data['consent_required'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $stored
     */
    protected function secret(array $data, array $stored, string $key, string $clearKey): ?string
    {
        return match (true) {
            (bool) ($data[$clearKey] ?? false) => null,
            filled($data[$key] ?? null) => Crypt::encryptString((string) $data[$key]),
            default => $stored[$key] ?? null,
        };
    }

    protected function decryptToken(?string $encrypted): string
    {
        if (blank($encrypted)) {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }
}
