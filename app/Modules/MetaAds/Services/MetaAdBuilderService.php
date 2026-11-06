<?php

namespace App\Modules\MetaAds\Services;

use App\Models\User;
use App\Modules\Commerce\AiTools\ChatbotProductPresenter;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaAds\Jobs\PublishMetaAdJob;
use App\Modules\MetaAds\Models\MetaAd;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\MetaSocial\Services\MetaBusinessConnectService;
use App\Modules\MetaSocial\Services\MetaPageTokenResolver;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Creates and controls Facebook/Instagram ads with the Marketing API (ads_management, pages_manage_ads):
 * promote a store product, boost a Page post, or run a creator's post as a partnership (branded content) ad.
 * Every ad is created PAUSED; money is only spent after the owner presses Start.
 */
class MetaAdBuilderService
{
    public const MANAGE_SCOPE = 'ads_management';

    public const PARTNERSHIP_SCOPES = ['facebook_branded_content_ads_brand', 'instagram_branded_content_ads_brand'];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaBusinessConnectService $business,
        protected MetaAdsClient $client,
        protected MetaSocialClient $social,
        protected MetaErrorTranslator $errors,
        protected MetaPageTokenResolver $pageTokens,
    ) {}

    /**
     * Everything the "Create ad" page needs.
     *
     * @return array<string, mixed>
     */
    public function context(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $connection = $this->business->connection($user);
        $scopes = (array) ($connection?->scopes ?? []);

        return [
            'connected' => $connection !== null && filled($connection->accessToken()),
            'can_manage' => in_array(self::MANAGE_SCOPE, $scopes, true),
            'can_partner' => array_intersect(self::PARTNERSHIP_SCOPES, $scopes) !== [],
            'ad_accounts' => $connection ? $this->adAccounts($connection) : [],
            'pages' => $connection ? $connection->assetsOfType('messenger') : [],
            'instagram_accounts' => $connection ? $connection->assetsOfType('instagram') : [],
            'products' => Product::query()->where('workspace_id', $workspace->id)->latest('id')->limit(200)->get(['id', 'name'])->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name])->all(),
            'ads' => MetaAd::query()->where('workspace_id', $workspace->id)->where('status', '!=', MetaAd::STATUS_DELETED)->latest()->limit(50)->get(),
        ];
    }

    /**
     * Saves the ad and creates it in Meta in the background.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(?User $user, array $data): MetaAd
    {
        $workspace = $this->workspaces->current($user);
        $connection = $this->managingConnection($user);
        $account = collect($this->adAccounts($connection))->firstWhere('id', $data['ad_account_id']);
        $page = collect($connection->assetsOfType('messenger'))->firstWhere('id', $data['page_id']);

        if (! $account) {
            throw ValidationException::withMessages(['ad_account_id' => __('Choose one of the ad accounts you turned on for this workspace.')]);
        }

        if (! $page) {
            throw ValidationException::withMessages(['page_id' => __('Choose a Facebook Page shared with this workspace.')]);
        }

        $kind = (string) $data['kind'];

        if ($kind === MetaAd::KIND_PRODUCT && ! Product::query()->where('workspace_id', $workspace->id)->whereKey($data['product_id'] ?? 0)->exists()) {
            throw ValidationException::withMessages(['product_id' => __('Choose a product from your store.')]);
        }

        if ($kind === MetaAd::KIND_PARTNERSHIP && array_intersect(self::PARTNERSHIP_SCOPES, (array) $connection->scopes) === []) {
            throw ValidationException::withMessages(['kind' => __('Reconnect Meta and allow branded content (partnership) ads first.')]);
        }

        $destination = $kind === MetaAd::KIND_PRODUCT ? (string) ($data['destination'] ?? 'website') : null;

        $ad = MetaAd::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user?->id,
            'ad_account_id' => $account['id'],
            'page_id' => $page['id'],
            'instagram_account_id' => $data['instagram_account_id'] ?? null,
            'kind' => $kind,
            'name' => (string) $data['name'],
            'objective' => $this->objective($kind, $destination),
            'destination' => $destination,
            'daily_budget_minor' => OrderMoney::minor((string) $data['daily_budget'], 2),
            'currency' => $account['currency'] ?: 'USD',
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
            'targeting' => [
                'countries' => array_values(array_map('strtoupper', (array) ($data['countries'] ?? []))),
                'age_min' => (int) ($data['age_min'] ?? 18),
                'age_max' => (int) ($data['age_max'] ?? 65),
                'gender' => (string) ($data['gender'] ?? 'all'),
            ],
            'source' => array_filter([
                'product_id' => $kind === MetaAd::KIND_PRODUCT ? (int) $data['product_id'] : null,
                'post_id' => $kind === MetaAd::KIND_BOOST_POST ? (string) $data['post_id'] : null,
                'content_id' => $kind === MetaAd::KIND_PARTNERSHIP ? ($data['content_id'] ?? null) : null,
                'ad_code' => $kind === MetaAd::KIND_PARTNERSHIP ? ($data['ad_code'] ?? null) : null,
                'platform' => $kind === MetaAd::KIND_PARTNERSHIP ? strtoupper((string) ($data['platform'] ?? 'INSTAGRAM')) : null,
                'message' => $data['message'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== ''),
            'status' => MetaAd::STATUS_CREATING,
        ]);

        PublishMetaAdJob::dispatch($ad->id);

        return $ad;
    }

    /**
     * Campaign → ad set → creative → ad, all paused. On any failure the half-built campaign is deleted.
     */
    public function publish(MetaAd $ad): MetaAd
    {
        $token = $this->tokenFor($ad);
        $account = $ad->ad_account_id;

        try {
            $campaignId = $this->createdId($this->client->createCampaign($account, $token, [
                'name' => $ad->name,
                'objective' => $ad->objective,
                'status' => 'PAUSED',
                'special_ad_categories' => [],
                'is_adset_budget_sharing_enabled' => 'false',
            ]));
            $ad->update(['meta_campaign_id' => $campaignId]);

            $adSetId = $this->createdId($this->client->createAdSet($account, $token, $this->adSetFields($ad, $campaignId)));
            $creativeId = $this->createdId($this->client->createCreative($account, $token, $this->creativeFields($ad)));
            $adId = $this->createdId($this->client->createAd($account, $token, [
                'name' => $ad->name,
                'adset_id' => $adSetId,
                'creative' => ['creative_id' => $creativeId],
                'status' => 'PAUSED',
            ]));

            $ad->update([
                'meta_adset_id' => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id' => $adId,
                'status' => MetaAd::STATUS_PAUSED,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            if (filled($ad->meta_campaign_id)) {
                try {
                    $this->client->delete((string) $ad->meta_campaign_id, $token);
                } catch (Throwable) {
                }
            }

            $ad->update(['status' => MetaAd::STATUS_FAILED, 'meta_campaign_id' => null, 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
        }

        return $ad->refresh();
    }

    /**
     * Starts spending: the campaign, ad set and ad are all set to ACTIVE.
     */
    public function start(MetaAd $ad): MetaAd
    {
        return $this->setStatus($ad, 'ACTIVE', MetaAd::STATUS_ACTIVE);
    }

    public function pause(MetaAd $ad): MetaAd
    {
        return $this->setStatus($ad, 'PAUSED', MetaAd::STATUS_PAUSED);
    }

    public function remove(MetaAd $ad): void
    {
        if (filled($ad->meta_campaign_id)) {
            $this->check($this->client->delete((string) $ad->meta_campaign_id, $this->tokenFor($ad)));
        }

        $ad->update(['status' => MetaAd::STATUS_DELETED]);
    }

    /**
     * @return array{spend: string, impressions: int, clicks: int, reach: int}|null
     */
    public function results(MetaAd $ad): ?array
    {
        if (blank($ad->meta_ad_id)) {
            return null;
        }

        return Cache::remember('meta-ad-results:'.$ad->id, now()->addMinutes(15), function () use ($ad): ?array {
            try {
                $response = $this->client->adInsights((string) $ad->meta_ad_id, $this->tokenFor($ad));
            } catch (Throwable) {
                return null;
            }

            $row = (array) data_get($response->json(), 'data.0', []);

            return $response->successful() ? [
                'spend' => (string) ($row['spend'] ?? '0'),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'reach' => (int) ($row['reach'] ?? 0),
            ] : null;
        });
    }

    /**
     * Recent Page posts to boost.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pagePosts(?User $user, string $pageId): array
    {
        $connection = $this->managingConnection($user);
        $this->ensurePage($connection, $pageId);
        $response = $this->social->pagePublishedPosts($pageId, $this->pageToken($connection, $pageId));

        return collect((array) data_get($this->check($response), 'data', []))
            ->map(fn (array $post): array => [
                'id' => (string) ($post['id'] ?? ''),
                'message' => mb_strimwidth((string) ($post['message'] ?? ''), 0, 140, '…'),
                'picture' => $post['full_picture'] ?? null,
                'permalink' => $post['permalink_url'] ?? null,
                'created_time' => $post['created_time'] ?? null,
            ])
            ->filter(fn (array $post): bool => $post['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * Creator posts tagging the brand that can run as partnership ads.
     *
     * @return array<int, array<string, mixed>>
     */
    public function partnershipContent(?User $user, string $pageId, ?string $instagramId, ?string $search = null): array
    {
        $connection = $this->managingConnection($user);
        $this->ensurePage($connection, $pageId);

        if (blank($connection->business_id)) {
            throw ValidationException::withMessages(['kind' => __('Reconnect Meta and choose your business portfolio to find creator posts.')]);
        }

        $response = $this->client->partnershipContent((string) $connection->business_id, (string) $connection->accessToken(), [
            'fb_page_id' => $pageId,
            'ig_user_id' => $instagramId,
            'search_key' => $search,
        ]);

        return collect((array) data_get($this->check($response), 'data', []))
            ->map(fn (array $item): array => [
                'content_id' => (string) ($item['content_id'] ?? ''),
                'platform' => (string) ($item['platform'] ?? ''),
                'caption' => mb_strimwidth((string) ($item['caption'] ?? ''), 0, 140, '…'),
                'permalink' => $item['permalink'] ?? null,
                'creator' => data_get($item, 'author.display_name'),
                'ad_code' => data_get($item, 'partnership_info.ad_code'),
                'eligibility' => data_get($item, 'partnership_info.ad_eligibility'),
                'permission' => data_get($item, 'partnership_info.permission_status'),
                'errors' => array_values((array) data_get($item, 'partnership_info.eligibility_errors', [])),
            ])
            ->filter(fn (array $item): bool => $item['content_id'] !== '')
            ->values()
            ->all();
    }

    public function requestCreatorPermission(?User $user, string $instagramId, string $creatorUsername): void
    {
        $connection = $this->managingConnection($user);

        if (! collect($connection->assetsOfType('instagram'))->contains('id', $instagramId)) {
            throw ValidationException::withMessages(['instagram_account_id' => __('Choose your Instagram account.')]);
        }

        $this->check($this->client->requestPartnershipPermission($instagramId, (string) $connection->accessToken(), $creatorUsername));
    }

    public function objective(string $kind, ?string $destination): string
    {
        return match (true) {
            $kind === MetaAd::KIND_PRODUCT && $destination === 'website' => 'OUTCOME_TRAFFIC',
            $kind === MetaAd::KIND_PARTNERSHIP => 'OUTCOME_AWARENESS',
            default => 'OUTCOME_ENGAGEMENT',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function adSetFields(MetaAd $ad, string $campaignId): array
    {
        $targeting = (array) $ad->targeting;
        $gender = match ($targeting['gender'] ?? 'all') {
            'men' => [1],
            'women' => [2],
            default => null,
        };

        [$goal, $destinationType] = match (true) {
            $ad->kind === MetaAd::KIND_PRODUCT && $ad->destination === 'website' => ['LINK_CLICKS', 'WEBSITE'],
            $ad->kind === MetaAd::KIND_PRODUCT && $ad->destination === 'messenger' => ['CONVERSATIONS', 'MESSENGER'],
            $ad->kind === MetaAd::KIND_PRODUCT && $ad->destination === 'whatsapp' => ['CONVERSATIONS', 'WHATSAPP'],
            $ad->kind === MetaAd::KIND_BOOST_POST => ['POST_ENGAGEMENT', 'ON_POST'],
            default => ['REACH', null],
        };

        return array_filter([
            'name' => $ad->name,
            'campaign_id' => $campaignId,
            'daily_budget' => $ad->daily_budget_minor,
            'billing_event' => 'IMPRESSIONS',
            'optimization_goal' => $goal,
            'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
            'destination_type' => $destinationType,
            'promoted_object' => in_array($destinationType, ['MESSENGER', 'WHATSAPP', 'ON_POST'], true) ? ['page_id' => $ad->page_id] : null,
            'targeting' => array_filter([
                'geo_locations' => ['countries' => $targeting['countries'] ?? []],
                'age_min' => $targeting['age_min'] ?? 18,
                'age_max' => $targeting['age_max'] ?? 65,
                'genders' => $gender,
                'targeting_automation' => ['advantage_audience' => 0],
            ], fn ($value): bool => $value !== null),
            'start_time' => $ad->starts_at?->toIso8601String(),
            'end_time' => $ad->ends_at?->toIso8601String(),
            'status' => 'PAUSED',
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function creativeFields(MetaAd $ad): array
    {
        $source = (array) $ad->source;

        if ($ad->kind === MetaAd::KIND_BOOST_POST) {
            return ['name' => $ad->name, 'object_story_id' => (string) $source['post_id']];
        }

        if ($ad->kind === MetaAd::KIND_PARTNERSHIP) {
            return array_filter([
                'name' => $ad->name,
                'object_id' => $ad->page_id,
                'source_instagram_media_id' => blank($source['ad_code'] ?? null) ? ($source['content_id'] ?? null) : null,
                'branded_content' => array_filter([
                    'ad_format' => 1,
                    'instagram_boost_post_access_token' => $source['ad_code'] ?? null,
                ]),
                'facebook_branded_content' => ['sponsor_page_id' => $ad->page_id],
                'instagram_branded_content' => filled($ad->instagram_account_id) ? ['sponsor_id' => $ad->instagram_account_id] : null,
            ], fn ($value): bool => $value !== null);
        }

        $product = Product::query()->where('workspace_id', $ad->workspace_id)->findOrFail((int) ($source['product_id'] ?? 0));
        $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => (int) $ad->workspace_id]);
        $showcase = $presenter->showcase($product->loadMissing(['colors.swatchMedia', 'gallery.media', 'primaryMedia']));

        $callToAction = match ($ad->destination) {
            'messenger' => ['type' => 'MESSAGE_PAGE', 'value' => ['app_destination' => 'MESSENGER']],
            'whatsapp' => ['type' => 'WHATSAPP_MESSAGE', 'value' => ['app_destination' => 'WHATSAPP']],
            default => ['type' => 'SHOP_NOW', 'value' => ['link' => $showcase['url']]],
        };

        return [
            'name' => $ad->name,
            'object_story_spec' => array_filter([
                'page_id' => $ad->page_id,
                'instagram_user_id' => $ad->instagram_account_id,
                'link_data' => array_filter([
                    'link' => $showcase['url'],
                    'message' => $source['message'] ?? $product->name,
                    'name' => $product->name,
                    'description' => $presenter->priceLines($product)[0] ?? null,
                    'picture' => $showcase['colors'][0]['image_url'] ?? null,
                    'call_to_action' => $callToAction,
                ]),
            ]),
        ];
    }

    protected function setStatus(MetaAd $ad, string $metaStatus, string $status): MetaAd
    {
        if (! $ad->isLive()) {
            throw ValidationException::withMessages(['ad' => __('This ad is not ready in Meta yet.')]);
        }

        $token = $this->tokenFor($ad);

        foreach (array_filter([$ad->meta_campaign_id, $ad->meta_adset_id, $ad->meta_ad_id]) as $objectId) {
            $this->check($this->client->updateStatus((string) $objectId, $token, $metaStatus));
        }

        $ad->update(['status' => $status, 'last_error' => null]);

        return $ad->refresh();
    }

    protected function managingConnection(?User $user): MetaBusinessConnection
    {
        $connection = $this->business->connection($user);

        if (! $connection || blank($connection->accessToken())) {
            throw ValidationException::withMessages(['ad_account_id' => __('Connect your Meta Business first.')]);
        }

        if (! in_array(self::MANAGE_SCOPE, (array) $connection->scopes, true)) {
            throw ValidationException::withMessages(['ad_account_id' => __('Reconnect Meta and allow ad management to create ads.')]);
        }

        return $connection;
    }

    protected function tokenFor(MetaAd $ad): string
    {
        $token = MetaBusinessConnection::query()->where('workspace_id', $ad->workspace_id)->first()?->accessToken();

        if (blank($token)) {
            throw new RuntimeException(__('Reconnect your Meta Business to manage this ad.'));
        }

        return (string) $token;
    }

    /**
     * @return array<int, array{id: string, name: string, currency: string}>
     */
    protected function adAccounts(MetaBusinessConnection $connection): array
    {
        $selected = $connection->selectedOfType('ad_accounts');

        return collect($connection->assetsOfType('ad_accounts'))
            ->filter(fn (array $account): bool => in_array((string) ($account['id'] ?? ''), $selected, true))
            ->map(fn (array $account): array => ['id' => (string) $account['id'], 'name' => (string) ($account['name'] ?? $account['id']), 'currency' => (string) ($account['currency'] ?? '')])
            ->values()
            ->all();
    }

    protected function ensurePage(MetaBusinessConnection $connection, string $pageId): void
    {
        if (! collect($connection->assetsOfType('messenger'))->contains('id', $pageId)) {
            throw ValidationException::withMessages(['page_id' => __('Choose a Facebook Page shared with this workspace.')]);
        }
    }

    protected function pageToken(MetaBusinessConnection $connection, string $pageId): string
    {
        return $this->pageTokens->forPage((int) $connection->workspace_id, $pageId);
    }

    protected function createdId(Response $response): string
    {
        $id = data_get($this->check($response), 'id');

        if (blank($id)) {
            throw new RuntimeException(__('Meta did not return an id.'));
        }

        return (string) $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function check(Response $response): array
    {
        $json = (array) ($response->json() ?? []);

        if ($response->successful() && ! isset($json['error'])) {
            return $json;
        }

        $message = $this->errors->summary(['error' => (array) ($json['error'] ?? ['message' => __('Meta request failed.')])]);
        $userMessage = data_get($json, 'error.error_user_msg');

        throw new RuntimeException(trim($message.(filled($userMessage) && ! str_contains($message, (string) $userMessage) ? ' '.$userMessage : '')));
    }
}
