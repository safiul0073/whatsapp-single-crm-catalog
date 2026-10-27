<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Storefront\Events\LiveCommentCreated;
use App\Modules\Commerce\Storefront\Events\LiveEngagementUpdated;
use App\Modules\Commerce\Storefront\Events\LiveProductPinned;
use App\Modules\Commerce\Storefront\Events\LiveStreamStatusChanged;
use App\Modules\Commerce\Storefront\Events\LiveWebRtcSignal;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Commerce\Storefront\Models\LiveStream;
use App\Modules\Commerce\Storefront\Models\LiveStreamComment;
use App\Modules\Commerce\Storefront\Models\LiveStreamProduct;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveStreamService
{
    public const DEFAULT_ICE_SERVERS = [
        ['urls' => ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']],
        ['urls' => ['stun:stun.cloudflare.com:3478']],
    ];

    public const VALID_PLATFORMS = ['storefront', 'facebook'];

    public function __construct(
        protected FacebookLiveService $facebook,
    ) {}

    /**
     * Retrieve workspace live shopping and Facebook simulcast configuration.
     *
     * @return array<string, mixed>
     */
    public function settings(int $workspaceId, bool $verifyFacebook = false): array
    {
        $record = StorefrontSetting::query()->where('workspace_id', $workspaceId)->first();
        $raw = (array) data_get($record?->config, 'live', []);
        $fbReadiness = $this->facebook->readiness($workspaceId, $verifyFacebook);
        $fbCreds = $this->facebook->resolveCredentials($workspaceId);

        $defaultPlatforms = $this->normalizePlatforms((array) ($raw['default_platforms'] ?? ['storefront', 'facebook']));
        $iceServers = ! empty($raw['ice_servers']) && is_array($raw['ice_servers'])
            ? $raw['ice_servers']
            : self::DEFAULT_ICE_SERVERS;

        if (! empty($raw['turn_url'])) {
            $turnEntry = ['urls' => [(string) $raw['turn_url']]];
            if (! empty($raw['turn_username'])) {
                $turnEntry['username'] = (string) $raw['turn_username'];
            }
            if (! empty($raw['turn_credential'])) {
                $turnEntry['credential'] = (string) $raw['turn_credential'];
            }
            $iceServers[] = $turnEntry;
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'default_platforms' => $defaultPlatforms,
            'show_header_live_badge' => (bool) ($raw['show_header_live_badge'] ?? true),
            'auto_save_recording' => (bool) ($raw['auto_save_recording'] ?? true),
            'auto_publish_to_slider' => (bool) ($raw['auto_publish_to_slider'] ?? false),
            'default_slider_id' => isset($raw['default_slider_id']) && $raw['default_slider_id'] !== '' ? (int) $raw['default_slider_id'] : null,
            'allow_guest_chat' => (bool) ($raw['allow_guest_chat'] ?? true),
            'stun_url' => (string) ($raw['stun_url'] ?? 'stun:stun.l.google.com:19302'),
            'turn_url' => (string) ($raw['turn_url'] ?? ''),
            'turn_username' => (string) ($raw['turn_username'] ?? ''),
            'turn_credential' => (string) ($raw['turn_credential'] ?? ''),
            'ice_servers' => $iceServers,
            'reverb' => $this->reverbConfig(),
            'facebook' => [
                'enabled' => $fbCreds['enabled'],
                'use_connected_messenger_page' => $fbCreds['use_connected_messenger_page'],
                'page_id' => $fbCreds['page_id'],
                'page_name' => $fbCreds['page_name'],
                'has_access_token' => $fbCreds['page_access_token'] !== '',
                'source' => $fbCreds['source'],
                'sync_facebook_comments' => $fbCreds['sync_facebook_comments'],
                'auto_upload_vod_fallback' => $fbCreds['auto_upload_vod_fallback'],
                'readiness' => $fbReadiness,
            ],
        ];
    }

    /**
     * Save live streaming and Facebook Live configuration for the workspace.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveSettings(int $workspaceId, array $data): array
    {
        $setting = StorefrontSetting::query()->firstOrNew(['workspace_id' => $workspaceId]);
        $config = (array) ($setting->config ?? []);
        $existingLive = (array) ($config['live'] ?? []);
        $existingFb = (array) ($existingLive['facebook'] ?? []);

        $platforms = isset($data['default_platforms']) && is_array($data['default_platforms'])
            ? $this->normalizePlatforms($data['default_platforms'])
            : ($existingLive['default_platforms'] ?? ['storefront']);

        $fbInput = (array) ($data['facebook'] ?? []);
        $newToken = trim((string) ($fbInput['page_access_token'] ?? ''));

        $facebookConfig = [
            'enabled' => filter_var($fbInput['enabled'] ?? ($existingFb['enabled'] ?? false), FILTER_VALIDATE_BOOLEAN),
            'use_connected_messenger_page' => filter_var($fbInput['use_connected_messenger_page'] ?? ($existingFb['use_connected_messenger_page'] ?? true), FILTER_VALIDATE_BOOLEAN),
            'page_id' => trim((string) ($fbInput['page_id'] ?? ($existingFb['page_id'] ?? ''))),
            'page_name' => trim((string) ($fbInput['page_name'] ?? ($existingFb['page_name'] ?? ''))),
            'page_access_token' => $newToken !== '' ? $newToken : (string) ($existingFb['page_access_token'] ?? ''),
            'sync_facebook_comments' => filter_var($fbInput['sync_facebook_comments'] ?? ($existingFb['sync_facebook_comments'] ?? true), FILTER_VALIDATE_BOOLEAN),
            'auto_upload_vod_fallback' => filter_var($fbInput['auto_upload_vod_fallback'] ?? ($existingFb['auto_upload_vod_fallback'] ?? true), FILTER_VALIDATE_BOOLEAN),
            'verification_status' => $existingFb['verification_status'] ?? 'ready',
            'last_verified_at' => $existingFb['last_verified_at'] ?? null,
            'last_error' => null,
        ];

        $stunUrl = trim((string) ($data['stun_url'] ?? ($existingLive['stun_url'] ?? 'stun:stun.l.google.com:19302')));
        $iceServers = [
            ['urls' => [$stunUrl !== '' ? $stunUrl : 'stun:stun.l.google.com:19302']],
            ['urls' => ['stun:stun.cloudflare.com:3478']],
        ];

        $config['live'] = [
            'enabled' => filter_var($data['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'default_platforms' => $platforms,
            'show_header_live_badge' => filter_var($data['show_header_live_badge'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'auto_save_recording' => filter_var($data['auto_save_recording'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'auto_publish_to_slider' => filter_var($data['auto_publish_to_slider'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'default_slider_id' => ! empty($data['default_slider_id']) ? (int) $data['default_slider_id'] : null,
            'allow_guest_chat' => filter_var($data['allow_guest_chat'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'stun_url' => $stunUrl,
            'turn_url' => trim((string) ($data['turn_url'] ?? ($existingLive['turn_url'] ?? ''))),
            'turn_username' => trim((string) ($data['turn_username'] ?? ($existingLive['turn_username'] ?? ''))),
            'turn_credential' => trim((string) ($data['turn_credential'] ?? ($existingLive['turn_credential'] ?? ''))),
            'ice_servers' => $iceServers,
            'facebook' => $facebookConfig,
        ];

        $setting->config = $config;
        $setting->save();

        RevalidateStorefront::dispatch($workspaceId, ['cms', 'settings', 'live']);

        return $this->settings($workspaceId);
    }

    /**
     * Return Reverb WebSocket connection details for Storefront and Mobile clients.
     *
     * @return array{app_key: string, host: string, port: int, scheme: string, enabled: bool}
     */
    public function reverbConfig(): array
    {
        $conn = (array) config('broadcasting.connections.reverb', []);
        $options = (array) ($conn['options'] ?? []);

        return [
            'app_key' => (string) ($conn['key'] ?? 'fashionfiest-live-key'),
            'host' => (string) ($options['host'] ?: parse_url((string) config('app.url', 'http://127.0.0.1'), PHP_URL_HOST) ?: '127.0.0.1'),
            'port' => (int) ($options['port'] ?? 8080),
            'scheme' => (string) ($options['scheme'] ?? 'http'),
            'enabled' => config('broadcasting.default') !== 'null',
        ];
    }

    /**
     * Normalize platform targets to ['storefront'], ['facebook'], or ['storefront', 'facebook'].
     *
     * @param  array<int, mixed>  $platforms
     * @return array<int, string>
     */
    public function normalizePlatforms(array $platforms): array
    {
        $filtered = array_values(array_unique(array_intersect(
            array_map(static fn ($p): string => strtolower(trim((string) $p)), $platforms),
            self::VALID_PLATFORMS,
        )));

        return ! empty($filtered) ? $filtered : ['storefront'];
    }

    /**
     * Create or schedule a new live stream.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStream(
        int $workspaceId,
        ?int $hostUserId,
        array $data,
        ?UploadedFile $thumbnail = null,
        ?UploadedFile $video = null,
    ): LiveStream {
        $liveSettings = $this->settings($workspaceId);
        $platforms = isset($data['broadcast_platforms']) && is_array($data['broadcast_platforms'])
            ? $this->normalizePlatforms($data['broadcast_platforms'])
            : $liveSettings['default_platforms'];

        $title = trim((string) $data['title']);
        $slug = $this->uniqueSlug($workspaceId, (string) ($data['slug'] ?? $title));

        $thumbnailPath = null;
        if ($thumbnail) {
            $ext = strtolower($thumbnail->getClientOriginalExtension() ?: 'jpg');
            $thumbnailPath = $thumbnail->storeAs(
                "commerce/lives/{$workspaceId}/thumbnails",
                "{$slug}-".time().'.'.$ext,
                'public'
            );
        } elseif (! empty($data['thumbnail_path']) && is_string($data['thumbnail_path'])) {
            $thumbnailPath = trim($data['thumbnail_path']);
        }

        $videoPath = null;
        $videoSize = 0;
        if ($video) {
            $ext = strtolower($video->getClientOriginalExtension() ?: 'mp4');
            $videoPath = $video->storeAs(
                "commerce/lives/{$workspaceId}",
                "{$slug}-".time().'.'.$ext,
                'public'
            );
            $videoSize = (int) $video->getSize();
        } elseif (! empty($data['video_path']) && is_string($data['video_path'])) {
            $videoPath = trim($data['video_path']);
        }

        $productIds = array_values(array_unique(array_map('intval', (array) ($data['product_ids'] ?? []))));
        $validProductIds = ! empty($productIds)
            ? Product::query()->where('workspace_id', $workspaceId)->whereIn('id', $productIds)->pluck('id')->all()
            : [];

        $pinnedProductId = ! empty($data['pinned_product_id']) && in_array((int) $data['pinned_product_id'], $validProductIds, true)
            ? (int) $data['pinned_product_id']
            : ($validProductIds[0] ?? null);

        $status = (string) ($data['status'] ?? ($videoPath ? 'ended' : 'scheduled'));

        $stream = LiveStream::query()->create([
            'workspace_id' => $workspaceId,
            'host_user_id' => $hostUserId,
            'title' => $title,
            'slug' => $slug,
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'status' => $status,
            'broadcast_platforms' => $platforms,
            'stream_key' => 'live_'.Str::lower(Str::random(28)),
            'facebook_status' => in_array('facebook', $platforms, true) ? 'ready_to_start' : 'not_selected',
            'thumbnail_path' => $thumbnailPath,
            'video_path' => $videoPath,
            'video_size_bytes' => $videoSize,
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'pinned_product_id' => $pinnedProductId,
            'chat_enabled' => filter_var($data['chat_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'scheduled_at' => ! empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
            'started_at' => $status === 'live' ? now() : null,
            'ended_at' => $status === 'ended' ? now() : null,
            'metadata' => [
                'auto_publish_to_slider' => filter_var($data['add_to_slider'] ?? $liveSettings['auto_publish_to_slider'], FILTER_VALIDATE_BOOLEAN),
                'target_slider_id' => ! empty($data['slider_id']) ? (int) $data['slider_id'] : $liveSettings['default_slider_id'],
            ],
        ]);

        $this->syncStreamProducts($stream, $validProductIds, $pinnedProductId);

        if ($videoPath && filter_var($data['add_to_slider'] ?? $liveSettings['auto_publish_to_slider'], FILTER_VALIDATE_BOOLEAN)) {
            $this->syncToSlider($stream, true, ! empty($data['slider_id']) ? (int) $data['slider_id'] : $liveSettings['default_slider_id']);
        }

        return $stream->refresh();
    }

    /**
     * Sync attached catalog products for a live stream.
     *
     * @param  array<int, int>  $productIds
     */
    public function syncStreamProducts(LiveStream $stream, array $productIds, ?int $pinnedProductId = null): void
    {
        LiveStreamProduct::query()->where('live_stream_id', $stream->id)->delete();

        foreach (array_values($productIds) as $index => $productId) {
            $isPinned = $pinnedProductId !== null && (int) $productId === $pinnedProductId;
            LiveStreamProduct::query()->create([
                'live_stream_id' => $stream->id,
                'product_id' => (int) $productId,
                'sort_order' => $index,
                'is_pinned' => $isPinned,
                'pinned_at' => $isPinned ? now() : null,
            ]);
        }
    }

    /**
     * Start a live broadcast across the selected platforms (Storefront WebRTC, Facebook Live, or Both).
     *
     * @param  array<int, string>|null  $platforms
     */
    public function startStream(LiveStream $stream, ?array $platforms = null): LiveStream
    {
        $selectedPlatforms = $platforms !== null
            ? $this->normalizePlatforms($platforms)
            : $this->normalizePlatforms($stream->broadcast_platforms ?? ['storefront']);

        $stream->update([
            'status' => 'live',
            'broadcast_platforms' => $selectedPlatforms,
            'started_at' => $stream->started_at ?? now(),
            'ended_at' => null,
            'facebook_status' => in_array('facebook', $selectedPlatforms, true) ? 'starting' : 'not_selected',
            'facebook_error' => null,
        ]);

        if (in_array('facebook', $selectedPlatforms, true)) {
            $stream = $this->facebook->startLiveVideo($stream);
        }

        $formatted = $this->formatStream($stream->refresh(), true, false);
        broadcast(new LiveStreamStatusChanged($stream->workspace_id, $stream->id, 'live', $formatted));
        RevalidateStorefront::dispatch($stream->workspace_id, ['cms', 'live']);

        return $stream;
    }

    /**
     * Append a recorded media chunk to the public storage MP4 file during an active broadcast.
     */
    public function appendRecordingChunk(LiveStream $stream, UploadedFile $chunk): LiveStream
    {
        $relativePath = $stream->video_path ?: "commerce/lives/{$stream->workspace_id}/{$stream->slug}-{$stream->id}.mp4";
        $disk = Storage::disk('public');
        $directory = dirname($relativePath);

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $absolutePath = $disk->path($relativePath);
        file_put_contents($absolutePath, file_get_contents($chunk->getRealPath()), FILE_APPEND);

        $size = is_file($absolutePath) ? (int) filesize($absolutePath) : 0;

        $stream->update([
            'video_path' => $relativePath,
            'video_size_bytes' => $size,
        ]);

        return $stream->refresh();
    }

    /**
     * Stop an active live broadcast, store the final recorded MP4 on public storage,
     * end Facebook Live, and optionally publish the video to the Storefront Hero Slider.
     */
    public function stopStream(
        LiveStream $stream,
        ?UploadedFile $videoFile = null,
        ?UploadedFile $thumbnailFile = null,
        ?bool $addToSlider = null,
        ?int $sliderId = null,
        ?int $durationSeconds = null,
    ): LiveStream {
        $disk = Storage::disk('public');

        if ($thumbnailFile) {
            $ext = strtolower($thumbnailFile->getClientOriginalExtension() ?: 'jpg');
            $thumbPath = $thumbnailFile->storeAs(
                "commerce/lives/{$stream->workspace_id}/thumbnails",
                "{$stream->slug}-".time().'.'.$ext,
                'public'
            );
            $stream->thumbnail_path = $thumbPath;
        }

        if ($videoFile) {
            $ext = strtolower($videoFile->getClientOriginalExtension() ?: 'mp4');
            $videoPath = $videoFile->storeAs(
                "commerce/lives/{$stream->workspace_id}",
                "{$stream->slug}-{$stream->id}.{$ext}",
                'public'
            );
            $stream->video_path = $videoPath;
            $stream->video_size_bytes = (int) $videoFile->getSize();
        } elseif (filled($stream->video_path) && $disk->exists($stream->video_path)) {
            $stream->video_size_bytes = (int) $disk->size($stream->video_path);
        }

        $endedAt = now();
        $computedDuration = $durationSeconds !== null && $durationSeconds > 0
            ? $durationSeconds
            : ($stream->started_at ? max(1, (int) $stream->started_at->diffInSeconds($endedAt)) : (int) $stream->duration_seconds);

        $stream->status = 'ended';
        $stream->ended_at = $endedAt;
        $stream->duration_seconds = $computedDuration;
        $stream->viewer_count = 0;
        $stream->facebook_viewer_count = 0;
        $stream->save();

        if ($stream->hasPlatform('facebook')) {
            $stream = $this->facebook->endLiveVideo($stream);
        }

        $liveSettings = $this->settings($stream->workspace_id);
        $shouldAddToSlider = $addToSlider ?? (bool) data_get($stream->metadata, 'auto_publish_to_slider', $liveSettings['auto_publish_to_slider']);
        $targetSliderId = $sliderId ?? data_get($stream->metadata, 'target_slider_id', $liveSettings['default_slider_id']);

        if ($shouldAddToSlider && filled($stream->video_path)) {
            $stream = $this->syncToSlider($stream, true, $targetSliderId ? (int) $targetSliderId : null);
        }

        $formatted = $this->formatStream($stream->refresh(), true, false);
        broadcast(new LiveStreamStatusChanged($stream->workspace_id, $stream->id, 'ended', $formatted));
        RevalidateStorefront::dispatch($stream->workspace_id, ['cms', 'live']);

        return $stream;
    }

    /**
     * Promote or remove a recorded live stream video from the Storefront Hero Slider.
     */
    public function syncToSlider(LiveStream $stream, bool $enable, ?int $sliderId = null): LiveStream
    {
        $workspaceId = $stream->workspace_id;

        if (! $enable) {
            $sliders = StorefrontSlider::query()->where('workspace_id', $workspaceId)->get();
            foreach ($sliders as $slider) {
                $slides = array_values(array_filter(
                    (array) ($slider->slides ?? []),
                    static fn (array $slide): bool => (int) ($slide['live_stream_id'] ?? 0) !== $stream->id
                ));
                $slider->update(['slides' => $slides]);
            }

            $stream->update([
                'is_in_slider' => false,
                'slider_id' => null,
            ]);

            $this->republishHomePageSections($workspaceId);
            RevalidateStorefront::dispatch($workspaceId, ['cms', 'live']);

            return $stream->refresh();
        }

        $slider = null;
        if ($sliderId) {
            $slider = StorefrontSlider::query()->where('workspace_id', $workspaceId)->find($sliderId);
        }
        if (! $slider) {
            $slider = StorefrontSlider::query()->where('workspace_id', $workspaceId)->orderBy('id')->first();
        }
        if (! $slider) {
            $slider = StorefrontSlider::query()->create([
                'workspace_id' => $workspaceId,
                'name' => 'Home Hero Slider',
                'slides' => [],
            ]);
        }

        $slides = array_values(array_filter(
            (array) ($slider->slides ?? []),
            static fn (array $slide): bool => (int) ($slide['live_stream_id'] ?? 0) !== $stream->id
        ));

        $newSlide = [
            'live_stream_id' => $stream->id,
            'title' => $stream->title,
            'subtitle' => $stream->description ?: 'Watch our recorded live session and shop the featured collection.',
            'image' => $stream->thumbnail_path ?: '/images/placeholder-live.jpg',
            'mobile_image' => $stream->thumbnail_path ?: '',
            'video' => $stream->video_path ?: '',
            'button_text' => 'Watch & Shop Live',
            'button_link' => '/live/'.$stream->slug,
            'active' => true,
        ];

        array_unshift($slides, $newSlide);
        $slider->update(['slides' => $slides]);

        $stream->update([
            'is_in_slider' => true,
            'slider_id' => $slider->id,
        ]);

        $this->republishHomePageSections($workspaceId);
        RevalidateStorefront::dispatch($workspaceId, ['cms', 'live']);

        return $stream->refresh();
    }

    /**
     * Relay WebRTC SDP Offer/Answer/ICE candidate signaling over Laravel Reverb.
     *
     * @param  array<string, mixed>  $payload
     */
    public function relaySignal(
        LiveStream $stream,
        string $type,
        string $senderPeerId,
        ?string $targetPeerId = null,
        array $payload = [],
    ): void {
        broadcast(new LiveWebRtcSignal(
            $stream->id,
            $type,
            $senderPeerId,
            $targetPeerId,
            $payload,
        ));
    }

    /**
     * Pin or unpin a featured product during a live stream and broadcast in real time.
     */
    public function pinProduct(LiveStream $stream, ?int $productId): LiveStream
    {
        LiveStreamProduct::query()
            ->where('live_stream_id', $stream->id)
            ->update(['is_pinned' => false, 'pinned_at' => null]);

        $pinnedProductPayload = null;

        if ($productId !== null) {
            $product = Product::query()
                ->where('workspace_id', $stream->workspace_id)
                ->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices'])
                ->find($productId);

            if ($product) {
                LiveStreamProduct::query()->updateOrCreate(
                    [
                        'live_stream_id' => $stream->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'is_pinned' => true,
                        'pinned_at' => now(),
                    ]
                );

                $stream->update(['pinned_product_id' => $product->id]);
                $pinnedProductPayload = (new ProductResource($product))->resolve();
            }
        } else {
            $stream->update(['pinned_product_id' => null]);
        }

        broadcast(new LiveProductPinned($stream->id, $stream->pinned_product_id, $pinnedProductPayload));

        return $stream->refresh();
    }

    /**
     * Store a live comment and broadcast it to all viewers and the owner studio.
     *
     * @param  array<string, mixed>  $data
     */
    public function addComment(LiveStream $stream, array $data): LiveStreamComment
    {
        $offset = $stream->started_at
            ? max(0, (int) $stream->started_at->diffInSeconds(now()))
            : (int) ($data['offset_seconds'] ?? 0);

        $comment = LiveStreamComment::query()->create([
            'live_stream_id' => $stream->id,
            'workspace_id' => $stream->workspace_id,
            'customer_id' => $data['customer_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'author_name' => mb_substr(trim((string) ($data['author_name'] ?? 'Guest')), 0, 120),
            'author_role' => (string) ($data['author_role'] ?? 'customer'),
            'body' => mb_substr(trim((string) $data['body']), 0, 500),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'is_hidden' => false,
            'offset_seconds' => $offset,
        ]);

        broadcast(new LiveCommentCreated($stream->id, $this->formatComment($comment)));

        return $comment;
    }

    /**
     * Update live engagement counters (like, join, leave, view) and broadcast over Reverb.
     */
    public function recordEngagement(LiveStream $stream, string $action): LiveStream
    {
        $viewerCount = (int) $stream->viewer_count;
        $likesCount = (int) $stream->likes_count;
        $totalViews = (int) $stream->total_views;
        $peakViewers = (int) $stream->peak_viewers;

        if ($action === 'like') {
            $likesCount++;
        } elseif ($action === 'join') {
            $viewerCount++;
            $totalViews++;
            $peakViewers = max($peakViewers, $viewerCount + (int) $stream->facebook_viewer_count);
        } elseif ($action === 'leave') {
            $viewerCount = max(0, $viewerCount - 1);
        } elseif ($action === 'view') {
            $totalViews++;
        }

        $stream->update([
            'viewer_count' => $viewerCount,
            'likes_count' => $likesCount,
            'total_views' => $totalViews,
            'peak_viewers' => $peakViewers,
        ]);

        broadcast(new LiveEngagementUpdated(
            $stream->id,
            $viewerCount,
            (int) $stream->facebook_viewer_count,
            $likesCount,
            $totalViews,
        ));

        return $stream->refresh();
    }

    /**
     * Format a LiveStream model for API responses.
     *
     * @return array<string, mixed>
     */
    public function formatStream(LiveStream $stream, bool $withDetails = false, bool $includeOwnerFields = false): array
    {
        $stream->loadMissing([
            'products' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
            'pinnedProduct' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
        ]);

        $products = ProductResource::collection($stream->products)->resolve();
        $pinnedProduct = $stream->pinnedProduct
            ? (new ProductResource($stream->pinnedProduct))->resolve()
            : ($products[0] ?? null);

        $payload = [
            'id' => $stream->id,
            'workspace_id' => $stream->workspace_id,
            'title' => $stream->title,
            'slug' => $stream->slug,
            'description' => $stream->description,
            'status' => $stream->status,
            'broadcast_platforms' => $stream->broadcast_platforms ?? ['storefront'],
            'stream_key' => $stream->stream_key,
            'facebook_status' => $stream->facebook_status,
            'facebook_permalink_url' => $stream->facebook_permalink_url,
            'thumbnail_url' => $stream->thumbnailUrl(),
            'video_url' => $stream->videoUrl(),
            'video_size_bytes' => (int) $stream->video_size_bytes,
            'duration_seconds' => (int) $stream->duration_seconds,
            'is_in_slider' => (bool) $stream->is_in_slider,
            'slider_id' => $stream->slider_id,
            'chat_enabled' => (bool) $stream->chat_enabled,
            'viewer_count' => (int) $stream->viewer_count + (int) $stream->facebook_viewer_count,
            'storefront_viewer_count' => (int) $stream->viewer_count,
            'facebook_viewer_count' => (int) $stream->facebook_viewer_count,
            'peak_viewers' => (int) $stream->peak_viewers,
            'total_views' => (int) $stream->total_views,
            'likes_count' => (int) $stream->likes_count,
            'pinned_product_id' => $stream->pinned_product_id,
            'pinned_product' => $pinnedProduct,
            'products_count' => count($products),
            'products' => $products,
            'scheduled_at' => $stream->scheduled_at?->toIso8601String(),
            'started_at' => $stream->started_at?->toIso8601String(),
            'ended_at' => $stream->ended_at?->toIso8601String(),
            'created_at' => $stream->created_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $comments = LiveStreamComment::query()
                ->where('live_stream_id', $stream->id)
                ->where('is_hidden', false)
                ->latest('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (LiveStreamComment $c): array => $this->formatComment($c))
                ->all();

            $liveSettings = $this->settings($stream->workspace_id);
            $payload['comments'] = $comments;
            $payload['ice_servers'] = $liveSettings['ice_servers'];
            $payload['reverb'] = $liveSettings['reverb'];
            $payload['allow_guest_chat'] = $liveSettings['allow_guest_chat'];
        }

        if ($includeOwnerFields) {
            $payload['facebook_live_id'] = $stream->facebook_live_id;
            $payload['facebook_stream_url'] = $stream->facebook_stream_url;
            $payload['facebook_error'] = $stream->facebook_error;
            $payload['video_path'] = $stream->video_path;
            $payload['thumbnail_path'] = $stream->thumbnail_path;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatComment(LiveStreamComment $comment): array
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

    protected function uniqueSlug(int $workspaceId, string $base): string
    {
        $slug = Str::slug($base) ?: 'live-stream';
        $candidate = $slug;
        $counter = 2;

        while (LiveStream::query()->where('workspace_id', $workspaceId)->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    protected function republishHomePageSections(int $workspaceId): void
    {
        $homePage = StorefrontPage::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', 'home')
            ->first();

        if ($homePage && $homePage->status === 'published') {
            $sections = $homePage->sections()->get()->toArray();
            $homePage->update([
                'published_content' => [
                    'title' => $homePage->title,
                    'seo' => $homePage->seo,
                    'sections' => $sections,
                ],
                'published_at' => now(),
            ]);
        }
    }
}
