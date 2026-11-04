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
}
