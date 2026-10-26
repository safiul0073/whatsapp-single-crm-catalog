<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Events\LiveCommentCreated;
use App\Modules\Commerce\Storefront\Events\LiveEngagementUpdated;
use App\Modules\Commerce\Storefront\Models\LiveStream;
use App\Modules\Commerce\Storefront\Models\LiveStreamComment;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

class FacebookLiveService
{
    public function __construct(protected MetaSocialSettingsService $metaSettings) {}

    /**
     * Resolve effective Facebook Live credentials for the workspace.
     *
     * @return array{
     *     enabled: bool,
     *     use_connected_messenger_page: bool,
     *     page_id: string,
     *     page_name: string,
     *     page_access_token: string,
     *     source: string,
     *     sync_facebook_comments: bool,
     *     auto_upload_vod_fallback: bool,
     *     verification_status: string,
     *     last_verified_at: ?string,
     *     last_error: ?string
     * }
     */
    public function resolveCredentials(int $workspaceId): array
    {
        $setting = StorefrontSetting::query()->where('workspace_id', $workspaceId)->first();
        $liveConfig = (array) data_get($setting?->config, 'live', []);
        $fbConfig = (array) ($liveConfig['facebook'] ?? []);

        $useConnectedPage = (bool) ($fbConfig['use_connected_messenger_page'] ?? true);
        $pageId = trim((string) ($fbConfig['page_id'] ?? ''));
        $pageName = trim((string) ($fbConfig['page_name'] ?? ''));
        $accessToken = trim((string) ($fbConfig['page_access_token'] ?? ''));
        $source = 'custom';

        if ($useConnectedPage || $pageId === '' || $accessToken === '') {
            $messengerAccount = ChannelAccount::query()
                ->where('workspace_id', $workspaceId)
                ->where('provider', 'messenger')
                ->where('status', ChannelAccountStatus::Connected->value)
                ->latest('id')
                ->first();

            if ($messengerAccount) {
                $channelToken = trim((string) $messengerAccount->credential('access_token', ''));
                $channelPageId = trim((string) ($messengerAccount->provider_account_id ?: data_get($messengerAccount->settings, 'page_id', '')));
                if ($channelPageId !== '' && $channelToken !== '') {
                    $pageId = $channelPageId;
                    $pageName = trim((string) ($messengerAccount->provider_display_id ?: $messengerAccount->name ?: $pageName));
                    $accessToken = $channelToken;
                    $source = 'messenger_channel';
                }
            }
        }

        $enabled = array_key_exists('enabled', $fbConfig)
            ? (bool) $fbConfig['enabled']
            : ($pageId !== '' && $accessToken !== '');

        $hasCredentials = $pageId !== '' && $accessToken !== '';
        $defaultStatus = $hasCredentials ? 'ready' : 'unconfigured';

        return [
            'enabled' => $enabled,
            'use_connected_messenger_page' => $useConnectedPage,
            'page_id' => $pageId,
            'page_name' => $pageName,
            'page_access_token' => $accessToken,
            'source' => $source,
            'sync_facebook_comments' => (bool) ($fbConfig['sync_facebook_comments'] ?? true),
            'auto_upload_vod_fallback' => (bool) ($fbConfig['auto_upload_vod_fallback'] ?? true),
            'verification_status' => (string) ($fbConfig['verification_status'] ?? $defaultStatus),
            'last_verified_at' => $fbConfig['last_verified_at'] ?? null,
            'last_error' => $fbConfig['last_error'] ?? null,
        ];
    }

    /**
     * Check whether Facebook Live settings are configured and up to date.
     *
     * @return array{
     *     ready: bool,
     *     enabled: bool,
     *     status: string,
     *     page_id: string,
     *     page_name: string,
     *     source: string,
     *     message: string,
     *     last_verified_at: ?string
     * }
     */
    public function readiness(int $workspaceId, bool $verifyRemote = false): array
    {
        $creds = $this->resolveCredentials($workspaceId);

        if (! $creds['enabled']) {
            return [
                'ready' => false,
                'enabled' => false,
                'status' => 'disabled',
                'page_id' => $creds['page_id'],
                'page_name' => $creds['page_name'],
                'source' => $creds['source'],
                'message' => 'Facebook Live is disabled in Storefront Live Settings.',
                'last_verified_at' => $creds['last_verified_at'],
            ];
        }

        if ($creds['page_id'] === '' || $creds['page_access_token'] === '') {
            return [
                'ready' => false,
                'enabled' => true,
                'status' => 'unconfigured',
                'page_id' => '',
                'page_name' => '',
                'source' => $creds['source'],
                'message' => 'Facebook Page ID or Page Access Token is not configured. Connect a Facebook Page in Marketing App.',
                'last_verified_at' => null,
            ];
        }

        if ($verifyRemote) {
            return $this->verifyConnection($workspaceId);
        }

        $isReady = $creds['verification_status'] !== 'invalid';

        return [
            'ready' => $isReady,
            'enabled' => true,
            'status' => $isReady ? 'ready' : 'invalid',
            'page_id' => $creds['page_id'],
            'page_name' => $creds['page_name'] ?: 'Facebook Page #'.$creds['page_id'],
            'source' => $creds['source'],
            'message' => $isReady
                ? 'Facebook Live settings are up to date and ready.'
                : ($creds['last_error'] ?: 'Facebook Page token needs to be updated in Marketing App.'),
            'last_verified_at' => $creds['last_verified_at'],
        ];
    }

    /**
     * Perform a live Meta Graph API verification of the workspace's Facebook Page credentials.
     *
     * @return array{
     *     ready: bool,
     *     enabled: bool,
     *     status: string,
     *     page_id: string,
     *     page_name: string,
     *     source: string,
     *     message: string,
     *     last_verified_at: ?string
     * }
     */
    public function verifyConnection(int $workspaceId): array
    {
        $creds = $this->resolveCredentials($workspaceId);

        if ($creds['page_id'] === '' || $creds['page_access_token'] === '') {
            $this->persistVerificationResult($workspaceId, 'unconfigured', '', 'Facebook Page ID and Access Token are required.');

            return [
                'ready' => false,
                'enabled' => $creds['enabled'],
                'status' => 'unconfigured',
                'page_id' => '',
                'page_name' => '',
                'source' => $creds['source'],
                'message' => 'Facebook Page ID and Access Token are required.',
                'last_verified_at' => null,
            ];
        }

        try {
            $response = Http::withToken($creds['page_access_token'])
                ->timeout(10)
                ->get($this->graphUrl($creds['page_id']), [
                    'fields' => 'id,name',
                ]);

            if ($response->successful() && filled($response->json('id'))) {
                $pageName = (string) ($response->json('name') ?: $creds['page_name'] ?: 'Facebook Page');
                $verifiedAt = now()->toIso8601String();
                $this->persistVerificationResult($workspaceId, 'ready', $pageName, null, $verifiedAt);

                return [
                    'ready' => true,
                    'enabled' => true,
                    'status' => 'ready',
                    'page_id' => (string) $response->json('id'),
                    'page_name' => $pageName,
                    'source' => $creds['source'],
                    'message' => "Connected to Facebook Page: {$pageName}",
                    'last_verified_at' => $verifiedAt,
                ];
            }

            $errorMsg = (string) (data_get($response->json(), 'error.message') ?: 'Facebook Graph API rejected the Page Access Token.');
            $this->persistVerificationResult($workspaceId, 'invalid', $creds['page_name'], $errorMsg);

            return [
                'ready' => false,
                'enabled' => $creds['enabled'],
                'status' => 'invalid',
                'page_id' => $creds['page_id'],
                'page_name' => $creds['page_name'],
                'source' => $creds['source'],
                'message' => $errorMsg,
                'last_verified_at' => null,
            ];
        } catch (Throwable $e) {
            $errorMsg = 'Could not reach Facebook Graph API: '.$e->getMessage();
            $this->persistVerificationResult($workspaceId, 'invalid', $creds['page_name'], $errorMsg);

            return [
                'ready' => false,
                'enabled' => $creds['enabled'],
                'status' => 'invalid',
                'page_id' => $creds['page_id'],
                'page_name' => $creds['page_name'],
                'source' => $creds['source'],
                'message' => $errorMsg,
                'last_verified_at' => null,
            ];
        }
    }

    /**
     * Start a simultaneous Facebook Live broadcast on the connected Facebook Page.
     */
    public function startLiveVideo(LiveStream $stream): LiveStream
    {
        if (! $stream->hasPlatform('facebook')) {
            $stream->update([
                'facebook_status' => 'not_selected',
                'facebook_error' => null,
            ]);

            return $stream;
        }

        $creds = $this->resolveCredentials($stream->workspace_id);
        if (! $creds['enabled'] || $creds['page_id'] === '' || $creds['page_access_token'] === '') {
            $stream->update([
                'facebook_status' => 'failed',
                'facebook_error' => 'Facebook Live settings are not configured or up to date.',
            ]);

            return $stream;
        }

        try {
            $response = Http::withToken($creds['page_access_token'])
                ->timeout(15)
                ->post($this->graphUrl($creds['page_id'].'/live_videos'), [
                    'status' => 'LIVE_NOW',
                    'title' => $stream->title,
                    'description' => $stream->description ?: $stream->title,
                ]);

            if ($response->successful() && filled($response->json('id'))) {
                $fbLiveId = (string) $response->json('id');
                $streamUrl = (string) ($response->json('secure_stream_url') ?: $response->json('stream_url') ?: '');
                $permalink = (string) ($response->json('permalink_url') ?: '');
                if ($permalink !== '' && ! str_starts_with($permalink, 'http')) {
                    $permalink = 'https://www.facebook.com/'.ltrim($permalink, '/');
                } elseif ($permalink === '') {
                    $permalink = "https://www.facebook.com/{$creds['page_id']}/videos/{$fbLiveId}";
                }

                $stream->update([
                    'facebook_live_id' => $fbLiveId,
                    'facebook_stream_url' => $streamUrl,
                    'facebook_permalink_url' => $permalink,
                    'facebook_status' => 'live',
                    'facebook_error' => null,
                ]);

                return $stream->refresh();
            }

            $errorMsg = (string) (data_get($response->json(), 'error.message') ?: 'Failed to create Facebook Live video.');
            $stream->update([
                'facebook_status' => 'failed',
                'facebook_error' => $errorMsg,
            ]);
        } catch (Throwable $e) {
            $stream->update([
                'facebook_status' => 'failed',
                'facebook_error' => $e->getMessage(),
            ]);
        }

        return $stream->refresh();
    }

    /**
     * End the active Facebook Live broadcast.
     */
    public function endLiveVideo(LiveStream $stream): LiveStream
    {
        if (blank($stream->facebook_live_id)) {
            if ($stream->facebook_status === 'live' || $stream->facebook_status === 'starting') {
                $stream->update(['facebook_status' => 'ended']);
            }

            return $stream;
        }

        $creds = $this->resolveCredentials($stream->workspace_id);
        if ($creds['page_access_token'] !== '') {
            try {
                Http::withToken($creds['page_access_token'])
                    ->timeout(10)
                    ->post($this->graphUrl($stream->facebook_live_id), [
                        'end_live_video' => true,
                    ]);
            } catch (Throwable) {
            }
        }

        $stream->update([
            'facebook_status' => 'ended',
        ]);

        return $stream->refresh();
    }

    /**
     * Sync live comments and viewer count from Facebook Graph API into the unified database & Reverb stream.
     *
     * @return array{imported_comments: int, facebook_viewer_count: int}
     */
    public function syncEngagement(LiveStream $stream): array
    {
        if (blank($stream->facebook_live_id) || $stream->status !== 'live') {
            return ['imported_comments' => 0, 'facebook_viewer_count' => (int) $stream->facebook_viewer_count];
        }

        $creds = $this->resolveCredentials($stream->workspace_id);
        if ($creds['page_access_token'] === '') {
            return ['imported_comments' => 0, 'facebook_viewer_count' => (int) $stream->facebook_viewer_count];
        }

        $imported = 0;

        try {
            $response = Http::withToken($creds['page_access_token'])
                ->timeout(10)
                ->get($this->graphUrl($stream->facebook_live_id), [
                    'fields' => 'live_views,comments.limit(25){id,from,message,created_time}',
                ]);

            if (! $response->successful()) {
                return ['imported_comments' => 0, 'facebook_viewer_count' => (int) $stream->facebook_viewer_count];
            }

            $fbViews = max(0, (int) $response->json('live_views', 0));
            $comments = (array) data_get($response->json(), 'comments.data', []);

            foreach ($comments as $fbComment) {
                $externalId = trim((string) ($fbComment['id'] ?? ''));
                $message = trim((string) ($fbComment['message'] ?? ''));
                if ($externalId === '' || $message === '') {
                    continue;
                }

                $authorName = trim((string) data_get($fbComment, 'from.name', 'Facebook Viewer'));
                $offset = $stream->started_at ? max(0, (int) $stream->started_at->diffInSeconds(now())) : 0;

                $comment = LiveStreamComment::query()->firstOrCreate(
                    [
                        'live_stream_id' => $stream->id,
                        'external_comment_id' => $externalId,
                    ],
                    [
                        'workspace_id' => $stream->workspace_id,
                        'author_name' => mb_substr($authorName, 0, 120),
                        'author_role' => 'facebook',
                        'body' => mb_substr($message, 0, 500),
                        'offset_seconds' => $offset,
                    ]
                );

                if ($comment->wasRecentlyCreated) {
                    $imported++;
                    broadcast(new LiveCommentCreated($stream->id, $this->formatComment($comment)));
                }
            }

            if ($fbViews !== (int) $stream->facebook_viewer_count) {
                $combinedPeak = max((int) $stream->peak_viewers, (int) $stream->viewer_count + $fbViews);
                $stream->update([
                    'facebook_viewer_count' => $fbViews,
                    'peak_viewers' => $combinedPeak,
                ]);

                broadcast(new LiveEngagementUpdated(
                    $stream->id,
                    (int) $stream->viewer_count,
                    $fbViews,
                    (int) $stream->likes_count,
                    (int) $stream->total_views,
                ));
            }
        } catch (Throwable) {
        }

        return [
            'imported_comments' => $imported,
            'facebook_viewer_count' => (int) $stream->facebook_viewer_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatComment(LiveStreamComment $comment): array
    {
        return [
            'id' => $comment->id,
            'author_name' => $comment->author_name,
            'author_role' => $comment->author_role,
            'body' => $comment->body,
            'is_pinned' => (bool) $comment->is_pinned,
            'offset_seconds' => (int) $comment->offset_seconds,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    protected function persistVerificationResult(
        int $workspaceId,
        string $status,
        string $pageName,
        ?string $error = null,
        ?string $verifiedAt = null,
    ): void {
        $setting = StorefrontSetting::query()->firstOrNew(['workspace_id' => $workspaceId]);
        $config = (array) ($setting->config ?? []);
        $live = (array) ($config['live'] ?? []);
        $fb = (array) ($live['facebook'] ?? []);

        $fb['verification_status'] = $status;
        if ($pageName !== '') {
            $fb['page_name'] = $pageName;
        }
        $fb['last_error'] = $error;
        if ($verifiedAt !== null) {
            $fb['last_verified_at'] = $verifiedAt;
        }

        $live['facebook'] = $fb;
        $config['live'] = $live;
        $setting->config = $config;
        $setting->save();
    }

    public function graphUrl(string $path): string
    {
        try {
            $version = $this->metaSettings->graphApiVersion() ?: 'v20.0';
        } catch (Throwable) {
            $version = 'v20.0';
        }

        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }
}
