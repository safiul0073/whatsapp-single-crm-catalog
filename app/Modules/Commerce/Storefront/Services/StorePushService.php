<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

/**
 * Each store (workspace) sends push through its own Firebase project, configured
 * by the owner: public app identifiers for the mobile app, and a service-account
 * key (stored encrypted) for sending.
 */
class StorePushService
{
    /** Public Firebase identifiers the mobile app needs; safe to expose. */
    public const PUBLIC_FIELDS = ['project_id', 'api_key', 'messaging_sender_id', 'android_app_id', 'ios_app_id', 'ios_bundle_id'];

    /** @var array<int, Messaging|null> */
    private array $clients = [];

    /**
     * Settings for the owner panel. The service-account key is never returned,
     * only who it belongs to.
     *
     * @return array<string, mixed>
     */
    public function settings(int $workspaceId): array
    {
        $raw = $this->raw($workspaceId);
        $account = $this->serviceAccount($workspaceId);

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            ...collect(self::PUBLIC_FIELDS)->mapWithKeys(fn (string $key): array => [$key => (string) ($raw[$key] ?? '')])->all(),
            'service_account_email' => $account['client_email'] ?? null,
            'ready' => $this->clientConfig($workspaceId) !== null && $account !== null,
        ];
    }

    /**
     * Firebase options for the mobile app, or null when push is off or incomplete.
     *
     * @return array<string, string>|null
     */
    public function clientConfig(int $workspaceId): ?array
    {
        $raw = $this->raw($workspaceId);
        if (! ($raw['enabled'] ?? false)) {
            return null;
        }
        $config = collect(self::PUBLIC_FIELDS)->mapWithKeys(fn (string $key): array => [$key => trim((string) ($raw[$key] ?? ''))])->all();
        $required = ['project_id', 'api_key', 'messaging_sender_id'];
        foreach ($required as $key) {
            if ($config[$key] === '') {
                return null;
            }
        }
        if ($config['android_app_id'] === '' && $config['ios_app_id'] === '') {
            return null;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $data  validated owner input; `service_account` is the raw JSON or absent to keep the stored key
     */
    public function save(int $workspaceId, array $data): array
    {
        $setting = StorefrontSetting::query()->firstOrNew(['workspace_id' => $workspaceId]);
        $config = (array) ($setting->config ?? []);
        $existing = (array) ($config['push'] ?? []);

        $push = ['enabled' => (bool) ($data['enabled'] ?? false)];
        foreach (self::PUBLIC_FIELDS as $key) {
            $push[$key] = trim((string) ($data[$key] ?? ''));
        }

        $json = trim((string) ($data['service_account'] ?? ''));
        if ($json !== '') {
            $account = $this->parseServiceAccount($json);
            if ($push['project_id'] !== '' && $account['project_id'] !== $push['project_id']) {
                throw ValidationException::withMessages(['service_account' => 'This key belongs to Firebase project "'.$account['project_id'].'", not "'.$push['project_id'].'".']);
            }
            $push['service_account'] = Crypt::encryptString($json);
        } else {
            $push['service_account'] = $existing['service_account'] ?? null;
        }

        if ($push['enabled'] && ($this->clientConfigFrom($push) === null || empty($push['service_account']))) {
            throw ValidationException::withMessages(['enabled' => 'Fill in the project ID, API key, sender ID, at least one app ID and the service-account key before turning push on.']);
        }

        $config['push'] = $push;
        $setting->config = $config;
        $setting->save();
        unset($this->clients[$workspaceId]);

        return $this->settings($workspaceId);
    }

    /**
     * Messaging client for this store's Firebase project, or null when not configured.
     */
    public function messaging(int $workspaceId): ?Messaging
    {
        if (array_key_exists($workspaceId, $this->clients)) {
            return $this->clients[$workspaceId];
        }
        $account = $this->clientConfig($workspaceId) !== null ? $this->serviceAccount($workspaceId) : null;

        return $this->clients[$workspaceId] = $account === null
            ? null
            : (new Factory)->withServiceAccount($account)->createMessaging();
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(int $workspaceId): array
    {
        $setting = StorefrontSetting::query()->where('workspace_id', $workspaceId)->first();

        return (array) data_get($setting?->config, 'push', []);
    }

    /**
     * @param  array<string, mixed>  $push
     */
    private function clientConfigFrom(array $push): ?array
    {
        foreach (['project_id', 'api_key', 'messaging_sender_id'] as $key) {
            if (($push[$key] ?? '') === '') {
                return null;
            }
        }

        return ($push['android_app_id'] ?? '') === '' && ($push['ios_app_id'] ?? '') === '' ? null : $push;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccount(int $workspaceId): ?array
    {
        $stored = (string) ($this->raw($workspaceId)['service_account'] ?? '');
        if ($stored === '') {
            return null;
        }
        try {
            $decoded = json_decode(Crypt::decryptString($stored), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseServiceAccount(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account' || empty($decoded['project_id']) || empty($decoded['private_key']) || empty($decoded['client_email'])) {
            throw ValidationException::withMessages(['service_account' => 'Paste the full service-account JSON from Firebase → Project settings → Service accounts → Generate new private key.']);
        }

        return $decoded;
    }
}
