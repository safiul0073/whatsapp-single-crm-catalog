<?php

namespace App\Modules\MetaSocial\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Graph calls for the "Connect your Meta Business" flow: token inspection and asset discovery.
 */
class MetaBusinessClient
{
    public function __construct(
        protected MetaSocialClient $social,
        protected MetaSocialSettingsService $settings,
    ) {}

    public function exchangeCode(string $code): Response
    {
        return $this->social->exchangeEmbeddedSignupCode($code);
    }

    public function exchangeLongLivedToken(string $token): Response
    {
        return $this->social->exchangeLongLivedToken($token);
    }

    /**
     * granular_scopes lists exactly which WABAs, catalogs and businesses the user granted.
     */
    public function debugToken(string $token): Response
    {
        return Http::get($this->social->graphUrl('debug_token'), [
            'input_token' => $token,
            'access_token' => $this->settings->get('meta_social_app_id').'|'.$this->settings->get('meta_social_app_secret'),
        ]);
    }

    public function node(string $id, string $token, string $fields): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl($id), ['fields' => $fields]);
    }

    public function businesses(string $token): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl('me/businesses'), ['fields' => 'id,name', 'limit' => 50]);
    }

    public function ownedWhatsAppAccounts(string $businessId, string $token): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl($businessId.'/owned_whatsapp_business_accounts'), ['fields' => 'id,name', 'limit' => 50]);
    }

    public function phoneNumbers(string $wabaId, string $token): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl($wabaId.'/phone_numbers'), [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,name_status,status',
            'limit' => 50,
        ]);
    }

    public function pageAccounts(string $token): Response
    {
        return $this->social->pageAccounts($token);
    }

    public function adAccounts(string $token): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl('me/adaccounts'), [
            'fields' => 'id,account_id,name,currency,account_status',
            'limit' => 100,
        ]);
    }

    public function ownedCatalogs(string $businessId, string $token): Response
    {
        return Http::withToken($token)->get($this->social->graphUrl($businessId.'/owned_product_catalogs'), ['fields' => 'id,name', 'limit' => 100]);
    }
}
