<?php

namespace App\Modules\MetaAds\Services;

use App\Modules\MetaSocial\Services\MetaSocialClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaAdsClient
{
    public const INSIGHT_FIELDS = 'spend,impressions,clicks,actions,action_values,purchase_roas,account_currency';

    public function __construct(protected MetaSocialClient $social) {}

    /**
     * @param  array{since: string, until: string}  $timeRange
     */
    public function insights(string $adAccountId, string $token, array $timeRange, string $level = 'account'): Response
    {
        $fields = $level === 'campaign' ? 'campaign_id,campaign_name,'.self::INSIGHT_FIELDS : self::INSIGHT_FIELDS;

        return Http::withToken($token)->get($this->social->graphUrl($adAccountId.'/insights'), [
            'level' => $level,
            'fields' => $fields,
            'time_range' => json_encode($timeRange),
            'limit' => 50,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function createCampaign(string $adAccountId, string $token, array $fields): Response
    {
        return $this->post($adAccountId.'/campaigns', $token, $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function createAdSet(string $adAccountId, string $token, array $fields): Response
    {
        return $this->post($adAccountId.'/adsets', $token, $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function createCreative(string $adAccountId, string $token, array $fields): Response
    {
        return $this->post($adAccountId.'/adcreatives', $token, $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function createAd(string $adAccountId, string $token, array $fields): Response
    {
        return $this->post($adAccountId.'/ads', $token, $fields);
    }

    /**
     * Sets ACTIVE or PAUSED on a campaign, ad set or ad.
     */
    public function updateStatus(string $objectId, string $token, string $status): Response
    {
        return $this->post($objectId, $token, ['status' => $status]);
    }

    public function delete(string $objectId, string $token): Response
    {
        return Http::withToken($token)->timeout(20)->delete($this->social->graphUrl($objectId));
    }

    public function adInsights(string $adId, string $token): Response
    {
        return Http::withToken($token)->timeout(20)->get($this->social->graphUrl($adId.'/insights'), [
            'fields' => 'spend,impressions,clicks,reach',
            'date_preset' => 'maximum',
        ]);
    }

    /**
     * Creator posts that tag the brand and can run as partnership (branded content) ads, on Instagram and
     * Facebook (facebook_/instagram_branded_content_ads_brand + business_management).
     *
     * @param  array<string, mixed>  $filters
     */
    public function partnershipContent(string $businessId, string $token, array $filters): Response
    {
        return Http::withToken($token)->timeout(20)->get($this->social->graphUrl($businessId.'/partnership-ads-advertisable-content'), array_filter(array_merge([
            'fields' => 'content_id,platform,media_type,caption,permalink,creation_time,author,partnership_info',
            'limit' => 25,
        ], $filters), fn ($value): bool => $value !== null && $value !== ''));
    }

    /**
     * Asks a creator for permission to run their posts that tag the brand as partnership ads.
     */
    public function requestPartnershipPermission(string $brandInstagramId, string $token, string $creatorUsername): Response
    {
        return $this->post($brandInstagramId.'/branded_content_ad_permissions', $token, [
            'creator_instagram_username' => ltrim($creatorUsername, '@'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function post(string $path, string $token, array $fields): Response
    {
        $form = array_map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value, $fields);

        return Http::withToken($token)->asForm()->timeout(30)->post($this->social->graphUrl($path), $form);
    }
}
