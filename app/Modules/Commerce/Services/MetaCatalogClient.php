<?php

namespace App\Modules\Commerce\Services;

use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaCatalogClient
{
    public function __construct(protected WhatsAppSettingsService $settings) {}

    public function catalog(string $catalogId, string $token): Response
    {
        return Http::withToken($token)->get($this->url($catalogId), ['fields' => 'id,name,vertical']);
    }

    public function commerceSettings(string $phoneNumberId, string $token): Response
    {
        return Http::withToken($token)->get($this->url($phoneNumberId.'/whatsapp_commerce_settings'));
    }

    public function updateCommerceSettings(string $phoneNumberId, string $token, bool $cartEnabled, bool $catalogVisible): Response
    {
        return Http::withToken($token)->post($this->url($phoneNumberId.'/whatsapp_commerce_settings'), [
            'is_cart_enabled' => $cartEnabled,
            'is_catalog_visible' => $catalogVisible,
        ]);
    }

    public function upsertProduct(string $catalogId, string $token, string $retailerId, array $data): Response
    {
        return Http::withToken($token)->post($this->url($catalogId.'/batch'), [
            'requests' => [[
                'method' => 'UPDATE',
                'retailer_id' => $retailerId,
                'data' => $data,
            ]],
        ]);
    }

    public function deleteProduct(string $catalogId, string $token, string $retailerId): Response
    {
        return Http::withToken($token)->post($this->url($catalogId.'/batch'), [
            'requests' => [['method' => 'DELETE', 'retailer_id' => $retailerId]],
        ]);
    }

    protected function url(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
