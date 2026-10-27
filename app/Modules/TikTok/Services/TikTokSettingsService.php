<?php

namespace App\Modules\TikTok\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class TikTokSettingsService
{
    protected const CACHE_KEY = 'tiktok_settings';

    public const DEFAULT_SCOPES = 'user.info.basic,video.upload';

    protected array $defaults = [
        'tiktok_client_key' => '',
        'tiktok_client_secret' => '',
        'tiktok_scopes' => self::DEFAULT_SCOPES,
        'tiktok_login_enabled' => '1',
        'tiktok_shop_enabled' => '0',
        'tiktok_shop_app_key' => '',
        'tiktok_shop_app_secret' => '',
    ];

    protected const ENCRYPTED = ['tiktok_client_secret', 'tiktok_shop_app_secret'];

    /**
     * Every saved value; the client secret stays encrypted.
     *
     * @return array<string, string>
     */
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

    /**
     * A blank secret keeps the saved one.
     *
     * @param  array<string, mixed>  $settings
     */
    public function update(array $settings): void
    {
        $settings = array_intersect_key($settings, $this->defaults);

        foreach (self::ENCRYPTED as $key) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }

            if (blank($settings[$key])) {
                unset($settings[$key]);
            } else {
                $settings[$key] = Crypt::encryptString((string) $settings[$key]);
            }
        }

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function clientKey(): string
    {
        return trim((string) $this->all()['tiktok_client_key']);
    }

    public function clientSecret(): string
    {
        return $this->decrypted('tiktok_client_secret');
    }

    public function secretSaved(): bool
    {
        return $this->clientSecret() !== '';
    }

    public function loginEnabled(): bool
    {
        return filter_var($this->all()['tiktok_login_enabled'], FILTER_VALIDATE_BOOLEAN);
    }

    public function shopAppKey(): string
    {
        return trim((string) $this->all()['tiktok_shop_app_key']);
    }

    public function shopAppSecret(): string
    {
        return $this->decrypted('tiktok_shop_app_secret');
    }

    public function shopSecretSaved(): bool
    {
        return $this->shopAppSecret() !== '';
    }

    /**
     * True once the admin has switched TikTok Shop on and saved the Partner Center app credentials.
     */
    public function shopReady(): bool
    {
        return filter_var($this->all()['tiktok_shop_enabled'], FILTER_VALIDATE_BOOLEAN)
            && $this->shopAppKey() !== ''
            && $this->shopAppSecret() !== '';
    }

    public function scopes(): string
    {
        return trim((string) $this->all()['tiktok_scopes']) ?: self::DEFAULT_SCOPES;
    }

    public function ready(): bool
    {
        return $this->loginEnabled() && $this->clientKey() !== '' && $this->clientSecret() !== '';
    }

    protected function decrypted(string $key): string
    {
        $stored = (string) $this->all()[$key];

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }
}
