<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TelegramBotProvider implements MarketingChannelDriver
{
    public function provider(): string
    {
        return 'telegram';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $token = (string) $account->credential('access_token');
        $chatId = (string) ($recipient['to'] ?? $recipient['chat_id'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $type = (string) ($payload['type'] ?? 'text');

        if (blank($token)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Telegram bot token is missing.',
            ];
        }

        if (blank($chatId)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Telegram chat ID is missing.',
            ];
        }

        if (in_array($type, ['image', 'video', 'audio', 'document'], true)) {
            return $this->sendAttachment($token, $chatId, $payload);
        }

        if (blank($body)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Message body is required.',
            ];
        }

        $telegramPayload = [
            'chat_id' => $chatId,
            'text' => $body,
            'parse_mode' => $payload['parse_mode'] ?? 'HTML',
        ];

        if (! empty($payload['buttons'])) {
            $telegramPayload['reply_markup'] = json_encode(['inline_keyboard' => $this->buildButtons($payload['buttons'])]);
        }

        try {
            $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", $telegramPayload);
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful() && data_get($json, 'ok') === true,
                'provider' => $this->provider(),
                'provider_message_id' => data_get($json, 'result.message_id'),
                'status' => ($response->successful() && data_get($json, 'ok') === true) ? 'sent' : 'failed',
                'response' => $json,
                'error' => data_get($json, 'description'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function sendAttachment(string $token, string $chatId, array $payload): array
    {
        $type = (string) $payload['type'];
        $url = (string) ($payload['url'] ?? '');

        if (blank($url)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Attachment URL is required.',
            ];
        }

        $method = match ($type) {
            'image' => 'sendPhoto',
            'video' => 'sendVideo',
            'audio' => 'sendAudio',
            default => 'sendDocument',
        };

        $field = match ($type) {
            'image' => 'photo',
            'video' => 'video',
            'audio' => 'audio',
            default => 'document',
        };

        try {
            $fileContent = null;
            $filename = null;
            $isLocal = false;

            $localPath = null;
            $appUrl = (string) config('app.url');
            $parsedUrl = parse_url($url);
            $parsedAppUrl = parse_url($appUrl);
            $path = $parsedUrl['path'] ?? '';

            // 1. Check if the path contains "/storage/" - this indicates it is a storage file.
            // If we can find it on our local disks, we use it directly, bypassing HTTP loopbacks.
            if (str_contains($path, '/storage/')) {
                $storagePath = substr($path, strpos($path, '/storage/') + 9); // strip everything up to and including /storage/

                if (Storage::disk('public')->exists($storagePath)) {
                    $localPath = Storage::disk('public')->path($storagePath);
                } elseif (Storage::disk()->exists($storagePath)) {
                    $localPath = Storage::disk()->path($storagePath);
                }
            }

            // 2. If not resolved via storage path, check if it's a relative path or local domain
            if (! $localPath) {
                $requestHost = request()->getHost();
                $appHost = $parsedAppUrl['host'] ?? null;
                $urlHost = $parsedUrl['host'] ?? null;

                $isLocalDomain = ! $urlHost
                    || ($urlHost === $requestHost)
                    || ($urlHost === $appHost)
                    || ($urlHost === 'localhost')
                    || ($urlHost === '127.0.0.1')
                    || str_ends_with($urlHost, '.ngrok-free.app')
                    || str_ends_with($urlHost, '.ngrok.io');

                if ($isLocalDomain) {
                    $relativeRawPath = ltrim($path, '/');
                    if (str_starts_with($relativeRawPath, 'public/')) {
                        $relativeRawPath = substr($relativeRawPath, 7);
                    }
                    $candidatePath = public_path($relativeRawPath);
                    if (file_exists($candidatePath)) {
                        $localPath = $candidatePath;
                    }
                }
            }

            if ($localPath && file_exists($localPath)) {
                $fileContent = fopen($localPath, 'r');
                $filename = basename($localPath);
                $isLocal = true;
            } else {
                // Remote/External URL
                $downloadResponse = Http::timeout(30)->get($url);
                if ($downloadResponse->successful()) {
                    $fileContent = $downloadResponse->body();
                    $filename = basename($parsedUrl['path'] ?? 'file');
                }
            }

            if ($fileContent) {
                $response = Http::timeout(60)
                    ->asMultipart()
                    ->attach($field, $fileContent, $filename)
                    ->post("https://api.telegram.org/bot{$token}/{$method}", [
                        'chat_id' => $chatId,
                        'parse_mode' => $payload['parse_mode'] ?? 'HTML',
                        'caption' => $payload['caption'] ?? null,
                    ]);

                if ($isLocal && is_resource($fileContent)) {
                    fclose($fileContent);
                }
            } else {
                // Fallback to sending as URL if download failed
                $telegramPayload = [
                    'chat_id' => $chatId,
                    $field => $url,
                    'parse_mode' => $payload['parse_mode'] ?? 'HTML',
                ];

                if (filled($payload['caption'] ?? null)) {
                    $telegramPayload['caption'] = (string) $payload['caption'];
                }

                $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/{$method}", $telegramPayload);
            }

            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful() && data_get($json, 'ok') === true,
                'provider' => $this->provider(),
                'provider_message_id' => data_get($json, 'result.message_id'),
                'status' => ($response->successful() && data_get($json, 'ok') === true) ? 'sent' : 'failed',
                'response' => $json,
                'error' => data_get($json, 'description'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function verifyWebhook(Request $request, ChannelAccount $account): bool
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        return $mode === 'subscribe'
            && filled($challenge)
            && filled($token)
            && hash_equals((string) $account->webhook_verify_token, (string) $token);
    }

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        return ['ok' => true];
    }

    public function syncTemplates(ChannelAccount $account): array
    {
        return ['ok' => true, 'synced' => 0];
    }

    public function getHealthStatus(ChannelAccount $account): array
    {
        return [
            'provider' => $this->provider(),
            'status' => $account->status,
            'connected' => $account->status?->value === 'connected',
            'has_token' => filled($account->credential('access_token')),
        ];
    }

    public function testConnection(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');

        if (blank($token)) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => 'Bot token is missing.'];
        }

        try {
            $response = Http::timeout(30)->get("https://api.telegram.org/bot{$token}/getMe");
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful() && data_get($json, 'ok') === true,
                'provider' => $this->provider(),
                'bot' => data_get($json, 'result.username'),
                'error' => data_get($json, 'description'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function setWebhook(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');

        if (blank($token)) {
            return ['ok' => false, 'message' => 'Bot token is missing.'];
        }

        $url = $this->webhookUrl($account);

        try {
            $payload = [
                'url' => $url,
            ];

            if (filled($account->webhook_verify_token)) {
                $payload['secret_token'] = $account->webhook_verify_token;
            }

            $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful() && data_get($json, 'ok') === true,
                'message' => data_get($json, 'description', 'Webhook updated.'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    protected function webhookUrl(ChannelAccount $account): string
    {
        $baseUrl = app(WhatsAppSettingsService::class)->webhookBaseUrl()
            ?: rtrim((string) config('app.url'), '/');

        return $baseUrl.'/webhooks/channels/telegram/'.$account->webhook_code;
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        $events = [];
        $message = data_get($payload, 'message');
        $callback = data_get($payload, 'callback_query');

        if ($message) {
            $text = (string) data_get($message, 'text', '');
            $startToken = null;

            if (preg_match('/^\/start(?:@\S+)?\s+([A-Za-z0-9_-]{8,64})$/', trim($text), $matches) === 1) {
                $startToken = $matches[1];
            }

            $events[] = [
                'type' => 'message',
                'provider_message_id' => data_get($message, 'message_id'),
                'provider_contact_id' => data_get($message, 'from.id'),
                'chat_id' => data_get($message, 'chat.id'),
                'username' => data_get($message, 'from.username'),
                'body' => $text !== '' ? $text : data_get($message, 'caption'),
                'start_token' => $startToken,
                'contact' => data_get($message, 'contact'),
                'payload' => $message,
            ];
        }

        if ($callback) {
            $events[] = [
                'type' => 'callback',
                'provider_message_id' => data_get($callback, 'message.message_id'),
                'provider_contact_id' => data_get($callback, 'from.id'),
                'payload' => $callback,
            ];
        }

        return ['ok' => true, 'events' => $events];
    }

    public function getCapabilities(): array
    {
        return ['Campaigns', 'Inbox', 'Templates', 'Automation', 'Webhooks'];
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        if (blank($account->credential('access_token'))) {
            throw ValidationException::withMessages([
                'channel_account_id' => 'Telegram bot token is missing.',
            ]);
        }

        if ($campaign->message_type === 'automation') {
            return;
        }

        if (! in_array($campaign->message_type, ['custom', 'template'], true)) {
            throw ValidationException::withMessages([
                'message_type' => 'Telegram campaigns support custom content, templates, or automation flows.',
            ]);
        }

        if ($campaign->message_type === 'template') {
            if (blank($campaign->message_template_id)) {
                throw ValidationException::withMessages([
                    'message_template_id' => 'Telegram template campaigns require a template.',
                ]);
            }

            return;
        }

        if (blank($campaign->message_body)) {
            throw ValidationException::withMessages([
                'message_body' => 'Telegram campaigns require a message body.',
            ]);
        }
    }

    /**
     * @param  array<int, array{text: string, url?: string, callback_data?: string}>  $buttons
     * @return array<int, array<array-key, mixed>>
     */
    protected function buildButtons(array $buttons): array
    {
        return array_map(fn (array $button): array => [array_filter([
            'text' => $button['text'] ?? '',
            'url' => $button['url'] ?? null,
            'callback_data' => $button['callback_data'] ?? null,
        ])], $buttons);
    }
}
