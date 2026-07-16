<?php

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Services\SettingsService;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SettingsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    protected function bootModule(array $module): void
    {
        $this->applyMailSettings();
        $this->applyStorageSettings();
        $this->applySocialSettings();
    }

    protected function applySocialSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            /** @var SettingsService $settings */
            $settings = $this->app->make(SettingsService::class);

            foreach (['google', 'facebook', 'github'] as $provider) {
                if (! (bool) $settings->get("social_{$provider}_enabled", false)) {
                    continue;
                }

                config([
                    "services.{$provider}.client_id" => (string) $settings->get("social_{$provider}_client_id", ''),
                    "services.{$provider}.client_secret" => (string) $settings->get("social_{$provider}_client_secret", ''),
                    "services.{$provider}.redirect" => url("auth/{$provider}/callback"),
                ]);
            }
        } catch (Throwable) {
            // Ignore runtime social overrides if database or routes are not ready yet.
        }
    }

    protected function applyStorageSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            /** @var SettingsService $settings */
            $settings = $this->app->make(SettingsService::class);

            $provider = (string) $settings->get('storage_provider', 'local');

            if ($provider === 'local') {
                return;
            }

            $isR2 = $provider === 'r2';
            $region = (string) $settings->get('storage_s3_region', '');

            config([
                'filesystems.disks.public' => [
                    'driver' => 's3',
                    'key' => (string) $settings->get('storage_s3_key', ''),
                    'secret' => (string) $settings->get('storage_s3_secret', ''),
                    'region' => $region !== '' ? $region : ($isR2 ? 'auto' : 'us-east-1'),
                    'bucket' => (string) $settings->get('storage_s3_bucket', ''),
                    'endpoint' => $settings->get('storage_s3_endpoint') ?: null,
                    'url' => $settings->get('storage_s3_url') ?: null,
                    'use_path_style_endpoint' => $isR2,
                    'visibility' => 'public',
                    'throw' => false,
                    'report' => false,
                ],
            ]);
        } catch (Throwable) {
            // Ignore runtime storage overrides if database is not ready yet.
        }
    }

    protected function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            /** @var SettingsService $settings */
            $settings = $this->app->make(SettingsService::class);

            $mailer = (string) $settings->get('mail_mailer', config('mail.default', 'log'));
            $smtpEncryption = (string) $settings->get('mail_encryption', 'tls');
            $smtpScheme = match ($smtpEncryption) {
                'ssl' => 'smtps',
                default => 'smtp',
            };

            config([
                'mail.default' => $mailer,
                'mail.from.name' => (string) $settings->get('mail_from_name', config('mail.from.name')),
                'mail.from.address' => (string) $settings->get('mail_from_address', config('mail.from.address')),
                'mail.mailers.smtp.host' => (string) $settings->get('mail_host', config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) $settings->get('mail_port', config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => $settings->get('mail_username') ?: null,
                'mail.mailers.smtp.password' => $settings->get('mail_password') ?: null,
                'mail.mailers.smtp.scheme' => $smtpScheme,
                'services.mailgun.domain' => (string) $settings->get('mailgun_domain', config('services.mailgun.domain')),
                'services.mailgun.secret' => (string) $settings->get('mailgun_secret', config('services.mailgun.secret')),
                'services.mailgun.endpoint' => (string) $settings->get('mailgun_endpoint', config('services.mailgun.endpoint')),
                'services.mailgun.scheme' => (string) $settings->get('mailgun_scheme', config('services.mailgun.scheme', 'https')),
            ]);
        } catch (Throwable) {
            // Ignore runtime mail overrides if database is not ready yet.
        }
    }
}
