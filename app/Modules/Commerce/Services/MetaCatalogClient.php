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
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->get($this->url($catalogId), ['fields' => 'id,name']);
    }

    /**
     * Lists catalogs linked to a WhatsApp Business Account. WhatsApp system user tokens can
     * read this edge even when they lack the catalog_management scope a direct node read needs.
     */
    public function wabaCatalogs(string $wabaId, string $token): Response
    {
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->get($this->url($wabaId.'/product_catalogs'), ['fields' => 'id,name']);
    }

    public function commerceSettings(string $phoneNumberId, string $token): Response
    {
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->get($this->url($phoneNumberId.'/whatsapp_commerce_settings'));
    }

    public function updateCommerceSettings(string $phoneNumberId, string $token, bool $cartEnabled, bool $catalogVisible): Response
    {
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->post($this->url($phoneNumberId.'/whatsapp_commerce_settings'), [
            'is_cart_enabled' => $cartEnabled,
            'is_catalog_visible' => $catalogVisible,
        ]);
    }

    public function upsertProduct(string $catalogId, string $token, string $retailerId, array $data): Response
    {
        $data['retailer_product_group_id'] = $data['item_group_id'];
        unset($data['item_group_id'], $data['retailer_id']);

        return Http::withToken($token)->connectTimeout(5)->timeout(20)->post($this->url($catalogId.'/batch'), [
            'allow_upsert' => true,
            'requests' => [[
                'method' => 'UPDATE',
                'retailer_id' => $retailerId,
                'data' => $data,
            ]],
        ]);
    }

    public function batchStatus(string $catalogId, string $token, string $handle): Response
    {
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->get($this->url($catalogId.'/check_batch_request_status'), [
            'handle' => $handle,
            'fields' => 'handle,status,errors,errors_total_count,ids_of_invalid_requests',
        ]);
    }

    public function deleteProduct(string $catalogId, string $token, string $retailerId): Response
    {
        return Http::withToken($token)->connectTimeout(5)->timeout(20)->post($this->url($catalogId.'/batch'), [
            'requests' => [['method' => 'DELETE', 'retailer_id' => $retailerId]],
        ]);
    }

    protected function url(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
