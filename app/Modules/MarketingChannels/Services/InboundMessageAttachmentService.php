<?php

namespace App\Modules\MarketingChannels\Services;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboundMessageAttachmentService
{
    /**
     * @return array{disk: string, path: string, url: string, name: string, mime_type: string, size: int, type: string}|null
     */
    public function forWhatsApp(ChannelAccount $account, array $message): ?array
    {
        $type = (string) ($message['type'] ?? '');

        if (! in_array($type, ['image', 'document', 'video', 'audio'], true)) {
            return null;
        }

        $media = data_get($message, $type);
        $mediaId = (string) data_get($media, 'id', '');
        $token = (string) $account->credential('access_token');

        if (blank($mediaId) || blank($token)) {
            return null;
        }

        try {
            $settings = app(WhatsAppSettingsService::class);
            $mediaResponse = Http::withToken($token)->get(
                'https://graph.facebook.com/'.$settings->graphApiVersion().'/'.$mediaId,
                array_filter(['phone_number_id' => $account->provider_phone_id])
            );

            $mediaUrl = (string) $mediaResponse->json('url');

            if (! $mediaResponse->successful() || blank($mediaUrl)) {
                return null;
            }

            $download = Http::withToken($token)->get($mediaUrl);

            if (! $download->successful()) {
                return null;
            }

            $mimeType = $this->cleanMimeType((string) (data_get($media, 'mime_type') ?: $download->header('Content-Type')));
            $name = (string) (data_get($media, 'filename') ?: $this->fallbackName($type, $mediaId, $mimeType));

            return $this->store($account, $type, $name, $mimeType, $download->body());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{disk: string, path: string, url: string, name: string, mime_type: string, size: int, type: string}|null
     */
    public function forTelegram(ChannelAccount $account, array $message): ?array
    {
        $file = $this->telegramFile($message);

        if ($file === null) {
            return null;
        }

        $token = (string) $account->credential('access_token');

        if (blank($token)) {
            return null;
        }

        try {
            $fileResponse = Http::timeout(30)->get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $file['file_id'],
            ]);

            $filePath = (string) $fileResponse->json('result.file_path');

            if (! $fileResponse->successful() || $fileResponse->json('ok') !== true || blank($filePath)) {
                return null;
            }

            $download = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

            if (! $download->successful()) {
                return null;
            }

            $mimeType = $this->cleanMimeType((string) ($file['mime_type'] ?: $download->header('Content-Type')));
            $name = (string) ($file['name'] ?: basename($filePath));

            return $this->store($account, $file['type'], $name, $mimeType, $download->body());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{file_id: string, name: string|null, mime_type: string|null, type: string}|null
     */
    protected function telegramFile(array $message): ?array
    {
        $photo = collect(data_get($message, 'photo', []))
            ->filter(fn ($item): bool => is_array($item) && filled($item['file_id'] ?? null))
            ->sortByDesc(fn (array $item): int => (int) ($item['file_size'] ?? 0))
            ->first();

        if (is_array($photo)) {
            return [
                'file_id' => (string) $photo['file_id'],
                'name' => 'telegram-photo-'.($message['message_id'] ?? Str::uuid()).'.jpg',
                'mime_type' => 'image/jpeg',
                'type' => 'image',
            ];
        }

        foreach (['document', 'video', 'audio', 'voice'] as $key) {
            $file = data_get($message, $key);

            if (! is_array($file) || blank($file['file_id'] ?? null)) {
                continue;
            }

            return [
                'file_id' => (string) $file['file_id'],
                'name' => $file['file_name'] ?? 'telegram-'.$key.'-'.($message['message_id'] ?? Str::uuid()),
                'mime_type' => $file['mime_type'] ?? null,
                'type' => match ($key) {
                    'video' => 'video',
                    'audio', 'voice' => 'audio',
                    default => 'document',
                },
            ];
        }

        return null;
    }

    /**
     * @return array{disk: string, path: string, url: string, name: string, mime_type: string, size: int, type: string}
     */
    protected function store(ChannelAccount $account, string $type, string $name, string $mimeType, string $contents): array
    {
        $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';
        $name = $this->safeFileName($name, $mimeType, $type);
        $path = 'inbox/'.$account->workspace_id.'/incoming/'.$account->provider.'/'.now()->format('Y/m').'/'.$name;

        Storage::disk('public')->put($path, $contents);

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => url(Storage::disk('public')->url($path)),
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'type' => $this->messageType($type, $mimeType),
        ];
    }

    protected function safeFileName(string $name, string $mimeType, string $type): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : $this->extensionFor($mimeType, $type);
        $base = (string) pathinfo($name, PATHINFO_FILENAME);
        $base = Str::of($base)->ascii()->replaceMatches('/[^A-Za-z0-9_-]+/', '-')->trim('-')->lower()->limit(80, '')->toString();
        $base = $base !== '' ? $base : 'attachment';

        return Str::uuid()->toString().'-'.$base.'.'.$extension;
    }

    protected function cleanMimeType(string $mimeType): string
    {
        return trim(strtolower(str($mimeType)->before(';')->toString()));
    }

    protected function fallbackName(string $type, string $id, string $mimeType): string
    {
        return $type.'-'.$id.'.'.$this->extensionFor($mimeType, $type);
    }

    protected function extensionFor(string $mimeType, string $type): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => match ($type) {
                'image' => 'jpg',
                'video' => 'mp4',
                'audio' => 'mp3',
                default => 'bin',
            },
        };
    }

    protected function messageType(string $type, string $mimeType): string
    {
        return match (true) {
            in_array($type, ['image', 'video', 'audio', 'document'], true) => $type,
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
