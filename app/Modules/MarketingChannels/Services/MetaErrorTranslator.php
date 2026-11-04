<?php

namespace App\Modules\MarketingChannels\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a Meta Graph API error into something a business owner can act on: what happened,
 * what to do next, and a support code. Mappings live in Config/meta-errors.php.
 */
class MetaErrorTranslator
{
    /**
     * @param  array<string, mixed>|null  $payload  A Graph response body ({"error": {...}}) or the error object itself.
     * @return array{title: string, message: string, action: string|null, link: string|null, reconnect: bool, retryable: bool, window: bool, meta_code: string|null, meta_message: string|null, trace_id: string|null}
     */
    public function translate(?array $payload, ?string $fallback = null): array
    {
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : ($payload ?? []);
        $code = filled($error['code'] ?? null) ? (string) $error['code'] : null;
        $subcode = filled($error['error_subcode'] ?? null) ? (string) $error['error_subcode'] : null;
        $metaMessage = $this->metaMessage($error) ?? $fallback;
        $entry = $this->entry($code, $subcode);

        return [
            'title' => __($entry['title'] ?? 'Meta returned an error'),
            'message' => __($entry['message'] ?? ($metaMessage ?: 'Meta did not complete the request.')),
            'action' => isset($entry['action']) ? __($entry['action']) : ($code !== null ? __('Try again. If it keeps failing, share the support code with us.') : null),
            'link' => $entry['link'] ?? null,
            'reconnect' => (bool) ($entry['reconnect'] ?? false),
            'retryable' => (bool) ($entry['retryable'] ?? false),
            'window' => (bool) ($entry['window'] ?? false),
            'meta_code' => $code !== null ? trim($code.($subcode ? ':'.$subcode : '')) : null,
            'meta_message' => $metaMessage ? Str::limit($metaMessage, 500) : null,
            'trace_id' => filled($error['fbtrace_id'] ?? null) ? (string) $error['fbtrace_id'] : null,
        ];
    }

    /**
     * One line for flashes and last_error: message, then the next step.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function summary(?array $payload, ?string $fallback = null): string
    {
        $translated = $this->translate($payload, $fallback);

        return trim($translated['message'].' '.($translated['action'] ?? ''));
    }

    /**
     * Logs the raw Meta error for support without any token.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, ?array $payload, array $context = []): void
    {
        $translated = $this->translate($payload);

        Log::warning($event, array_merge($context, [
            'meta_code' => $translated['meta_code'],
            'meta_message' => $translated['meta_message'],
            'fbtrace_id' => $translated['trace_id'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function entry(?string $code, ?string $subcode): array
    {
        if ($code === null) {
            return [];
        }

        $codes = config('meta-errors.codes', []);

        if ($subcode !== null && isset($codes[$code.':'.$subcode])) {
            return $codes[$code.':'.$subcode];
        }

        if (isset($codes[$code])) {
            return $codes[$code];
        }

        foreach (config('meta-errors.ranges', []) as $range) {
            if ((int) $code >= (int) $range['from'] && (int) $code <= (int) $range['to']) {
                return $range['entry'];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $error
     */
    protected function metaMessage(array $error): ?string
    {
        foreach (['error_user_msg', 'error_data.details', 'message'] as $key) {
            $value = data_get($error, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
