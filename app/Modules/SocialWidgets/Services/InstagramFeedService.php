<?php

namespace App\Modules\SocialWidgets\Services;

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use App\Modules\SocialWidgets\Models\SocialWidget;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InstagramFeedService
{
    public function __construct(protected MetaSocialClient $client) {}

    /**
     * Normalised `{source, profile, posts}` for a widget: searched username, then the connected
     * account, then demo data. Graph failures fall back to the last good copy, then to demo data.
     *
     * @return array{source: string, profile: array<string, mixed>, posts: array<int, array<string, mixed>>}
     */
    public function feedFor(SocialWidget $widget): array
    {
        $account = $this->connectedAccount($widget);

        if (! $account) {
            return $this->demoFeed();
        }

        $username = ltrim((string) data_get($widget->mergedSettings(), 'source.username'), '@');
        $limit = min(100, (int) data_get($widget->mergedSettings(), 'post.limit', 12) * 3);
        $cacheKey = 'social-widgets:instagram:'.$account->id.':'.($username ?: 'self').':'.$limit;

        $fresh = Cache::get($cacheKey);
        if (is_array($fresh)) {
            return $fresh;
        }

        $feed = $this->fetchFeed($account, $username, $limit);

        if ($feed) {
            Cache::put($cacheKey, $feed, now()->addMinutes(30));
            Cache::forever($cacheKey.':last-good', $feed);

            return $feed;
        }

        return Cache::get($cacheKey.':last-good') ?? $this->demoFeed();
    }

    public function connectedAccount(SocialWidget $widget): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $widget->workspace_id)
            ->where('provider', 'instagram')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->when($widget->channel_account_id, fn ($query) => $query->whereKey($widget->channel_account_id))
            ->latest()
            ->first();
    }

    /**
     * @return array{source: string, profile: array<string, mixed>, posts: array<int, array<string, mixed>>}
     */
    public function demoFeed(): array
    {
        $demo = json_decode((string) file_get_contents(__DIR__.'/../Resources/demo/instagram.json'), true);

        return ['source' => 'demo'] + $demo;
    }

    /**
     * @return array{source: string, profile: array<string, mixed>, posts: array<int, array<string, mixed>>}|null
     */
    protected function fetchFeed(ChannelAccount $account, string $username, int $limit): ?array
    {
        $token = (string) $account->credential('access_token');
        $igUserId = (string) ($account->provider_account_id ?: data_get($account->settings, 'instagram_account_id'));

        if ($token === '' || $igUserId === '') {
            return null;
        }

        try {
            if ($username !== '') {
                $response = $this->client->instagramBusinessDiscovery($igUserId, $token, $username, $limit);

                return $this->successful($response)
                    ? $this->normalise('search', (array) $response->json('business_discovery'), (array) $response->json('business_discovery.media.data', []))
                    : null;
            }

            $profile = $this->client->instagramProfile($igUserId, $token);
            $media = $this->client->instagramMedia($igUserId, $token, $limit);

            return $this->successful($profile) && $this->successful($media)
                ? $this->normalise('account', (array) $profile->json(), (array) $media->json('data', []))
                : null;
        } catch (\Throwable $exception) {
            Log::warning('SocialWidgets: Instagram fetch failed', ['account' => $account->id, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    protected function successful(Response $response): bool
    {
        if ($response->successful()) {
            return true;
        }

        Log::warning('SocialWidgets: Instagram Graph error', ['status' => $response->status(), 'error' => $response->json('error.message')]);

        return false;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $media
     * @return array{source: string, profile: array<string, mixed>, posts: array<int, array<string, mixed>>}
     */
    protected function normalise(string $source, array $profile, array $media): array
    {
        return [
            'source' => $source,
            'profile' => [
                'username' => $profile['username'] ?? '',
                'name' => $profile['name'] ?? ($profile['username'] ?? ''),
                'biography' => $profile['biography'] ?? '',
                'avatar' => $profile['profile_picture_url'] ?? null,
                'followers' => (int) ($profile['followers_count'] ?? 0),
                'following' => (int) ($profile['follows_count'] ?? 0),
                'posts' => (int) ($profile['media_count'] ?? 0),
                'verified' => false,
            ],
            'posts' => array_values(array_map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'type' => match ($item['media_type'] ?? 'IMAGE') {
                    'VIDEO' => 'video',
                    'CAROUSEL_ALBUM' => 'carousel',
                    default => 'image',
                },
                'image' => ($item['media_type'] ?? null) === 'VIDEO' ? ($item['thumbnail_url'] ?? null) : ($item['media_url'] ?? null),
                'video' => ($item['media_type'] ?? null) === 'VIDEO' ? ($item['media_url'] ?? null) : null,
                'permalink' => $item['permalink'] ?? null,
                'caption' => (string) ($item['caption'] ?? ''),
                'likes' => (int) ($item['like_count'] ?? 0),
                'comments' => (int) ($item['comments_count'] ?? 0),
                'timestamp' => $item['timestamp'] ?? null,
            ], $media)),
        ];
    }
}
