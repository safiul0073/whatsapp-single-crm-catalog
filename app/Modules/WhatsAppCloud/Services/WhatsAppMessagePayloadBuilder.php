<?php

namespace App\Modules\WhatsAppCloud\Services;

class WhatsAppMessagePayloadBuilder
{
    public function build(string $phone, array $payload): array
    {
        $phone = preg_replace('/\D+/', '', $phone) ?: $phone;

        return match ($payload['type'] ?? null) {
            'catalog_message' => $this->catalogMessage($phone, $payload),
            'product' => $this->product($phone, $payload),
            'product_list' => $this->productList($phone, $payload),
            'template' => $this->template($phone, $payload),
            'image', 'video', 'audio', 'document' => $this->media($phone, $payload),
            default => $this->text($phone, $payload),
        };
    }

    protected function catalogMessage(string $phone, array $payload): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'catalog_message',
                'body' => ['text' => (string) ($payload['body'] ?? 'Browse our catalog and add products to your cart.')],
                'action' => [
                    'name' => 'catalog_message',
                    'parameters' => array_filter([
                        'thumbnail_product_retailer_id' => $payload['thumbnail_product_retailer_id'] ?? null,
                    ]),
                ],
                'footer' => ['text' => (string) ($payload['footer'] ?? 'Availability and shipping are confirmed before payment.')],
            ],
        ];
    }

    protected function product(string $phone, array $payload): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'product',
                'body' => ['text' => (string) ($payload['body'] ?? 'Product details')],
                'action' => [
                    'catalog_id' => (string) $payload['catalog_id'],
                    'product_retailer_id' => (string) $payload['product_retailer_id'],
                ],
            ],
        ];
    }

    protected function productList(string $phone, array $payload): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'product_list',
                'header' => ['type' => 'text', 'text' => (string) ($payload['header'] ?? 'Our products')],
                'body' => ['text' => (string) ($payload['body'] ?? 'Select products to add to your cart.')],
                'action' => [
                    'catalog_id' => (string) $payload['catalog_id'],
                    'sections' => $payload['sections'] ?? [],
                ],
            ],
        ];
    }

    protected function template(string $phone, array $payload): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $payload['template_name'],
                'language' => ['code' => $payload['language'] ?? 'en_US'],
                'components' => $payload['components'] ?? [],
            ],
        ];
    }

    protected function media(string $phone, array $payload): array
    {
        $type = (string) $payload['type'];
        $media = ['link' => (string) $payload['url']];

        if (filled($payload['caption'] ?? null) && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = (string) $payload['caption'];
        }

        if ($type === 'document' && filled($payload['filename'] ?? null)) {
            $media['filename'] = (string) $payload['filename'];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => $type,
            $type => $media,
        ];
    }

    protected function text(string $phone, array $payload): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => (string) ($payload['body'] ?? '')],
        ];
    }
}
