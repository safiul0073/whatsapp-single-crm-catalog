<?php

namespace App\Modules\MarketingChannels\Services;

use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\Request;

/**
 * Checks Meta's X-Hub-Signature-256 header: an HMAC-SHA256 of the raw body made with the Meta app secret.
 */
class WebhookSignatureVerifier
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const MISSING = 'missing';

    public const UNCONFIGURED = 'unconfigured';

    public const NOT_APPLICABLE = 'not_applicable';

    private const SIGNED_PROVIDERS = ['whatsapp', 'messenger', 'instagram', 'threads'];

    public function __construct(
        protected WhatsAppSettingsService $whatsapp,
        protected MetaSocialSettingsService $social,
    ) {}

    public function verify(Request $request, string $provider): string
    {
        if (! in_array($provider, self::SIGNED_PROVIDERS, true)) {
            return self::NOT_APPLICABLE;
        }

        $secrets = $this->secrets();

        if ($secrets === []) {
            return self::UNCONFIGURED;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signature, 'sha256=')) {
            return self::MISSING;
        }

        foreach ($secrets as $secret) {
            if (hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
                return self::VALID;
            }
        }

        return self::INVALID;
    }

    /**
     * Every saved Meta app secret is accepted, because WhatsApp, Messenger and Threads may run on different Meta apps.
     *
     * @return array<int, string>
     */
    protected function secrets(): array
    {
        return collect([
            $this->whatsapp->get('whatsapp_meta_app_secret'),
            $this->social->get('meta_social_app_secret'),
        ])->map(fn ($secret): string => trim((string) $secret))->filter()->unique()->values()->all();
    }
}
