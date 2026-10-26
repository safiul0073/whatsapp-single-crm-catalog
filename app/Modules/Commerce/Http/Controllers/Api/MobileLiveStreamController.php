<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Storefront\Http\Requests\LiveAdminRequest;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Models\LiveStream;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use App\Modules\Commerce\Storefront\Services\FacebookLiveService;
use App\Modules\Commerce\Storefront\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MobileLiveStreamController extends Controller
{
    public function __construct(
        protected LiveStreamService $lives,
        protected FacebookLiveService $facebook,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $verifyFb = $request->boolean('verify_facebook', false);

        $settings = $this->lives->settings($workspaceId, $verifyFb);

        $streams = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->with([
                'products' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
                'pinnedProduct' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (LiveStream $s): array => $this->lives->formatStream($s, false, true))
            ->all();

        $catalogProducts = Product::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'published')
            ->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices'])
            ->latest('id')
            ->limit(100)
            ->get();

        $sliders = StorefrontSlider::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'name'])
            ->toArray();

        return response()->json([
            'data' => [
                'settings' => $settings,
                'streams' => $streams,
                'catalog_products' => ProductResource::collection($catalogProducts)->resolve(),
                'sliders' => $sliders,
            ],
        ]);
    }

    public function updateSettings(LiveAdminRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $settings = $this->lives->saveSettings($workspaceId, $request->validated());

        return response()->json(['data' => $settings]);
    }

    public function verifyFacebook(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $result = $this->facebook->verifyConnection($workspaceId);

        return response()->json(['data' => $result]);
    }

    public function store(LiveAdminRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $validated = $request->validated();

        $platforms = $this->lives->normalizePlatforms((array) ($validated['broadcast_platforms'] ?? ['storefront']));
        if (in_array('facebook', $platforms, true)) {
            $fbStatus = $this->facebook->readiness($workspaceId, false);
            if (! $fbStatus['ready'] && count($platforms) === 1) {
                return response()->json([
                    'message' => $fbStatus['message'],
                    'errors' => ['broadcast_platforms' => [$fbStatus['message']]],
                ], 422);
            }
        }

        $stream = $this->lives->createStream(
            $workspaceId,
            $request->user()?->id,
            $validated,
            $request->file('thumbnail'),
            $request->file('video'),
        );

        return response()->json([
            'data' => $this->lives->formatStream($stream, true, true),
        ], 201);
    }

    public function show(Request $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        return response()->json([
            'data' => $this->lives->formatStream($stream, true, true),
        ]);
    }

    public function start(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $validated = $request->validated();
        $platforms = isset($validated['broadcast_platforms'])
            ? $this->lives->normalizePlatforms((array) $validated['broadcast_platforms'])
            : ($stream->broadcast_platforms ?? ['storefront']);

        if (in_array('facebook', $platforms, true)) {
            $fbStatus = $this->facebook->readiness($workspaceId, false);
            if (! $fbStatus['ready'] && count($platforms) === 1) {
                return response()->json([
                    'message' => $fbStatus['message'],
                    'errors' => ['broadcast_platforms' => [$fbStatus['message']]],
                ], 422);
            }
        }

        $started = $this->lives->startStream($stream, $platforms);

        return response()->json([
            'data' => $this->lives->formatStream($started, true, true),
        ]);
    }

    public function signal(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $validated = $request->validated();
        $this->lives->relaySignal(
            $stream,
            (string) $validated['type'],
            (string) $validated['sender_peer_id'],
            isset($validated['target_peer_id']) ? (string) $validated['target_peer_id'] : null,
            (array) ($validated['payload'] ?? []),
        );

        return response()->json(['data' => ['ok' => true]]);
    }

    public function pinProduct(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $productId = $request->validated('product_id');
        $updated = $this->lives->pinProduct($stream, $productId !== null ? (int) $productId : null);

        return response()->json([
            'data' => $this->lives->formatStream($updated, true, true),
        ]);
    }

    public function comment(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $validated = $request->validated();
        $comment = $this->lives->addComment($stream, [
            'user_id' => $request->user()?->id,
            'author_name' => $request->user()?->name ?: 'Store Host',
            'author_role' => 'owner',
            'body' => (string) $validated['body'],
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
        ]);

        if (! empty($validated['sync_facebook']) && $stream->hasPlatform('facebook')) {
            $this->facebook->syncEngagement($stream);
        }

        return response()->json([
            'data' => $this->lives->formatComment($comment),
        ], 201);
    }

    public function syncFacebook(Request $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $syncResult = $this->facebook->syncEngagement($stream);

        return response()->json([
            'data' => array_merge($syncResult, [
                'stream' => $this->lives->formatStream($stream->refresh(), true, true),
            ]),
        ]);
    }

    public function chunk(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $updated = $this->lives->appendRecordingChunk($stream, $request->file('chunk'));

        return response()->json([
            'data' => [
                'video_url' => $updated->videoUrl(),
                'video_size_bytes' => (int) $updated->video_size_bytes,
            ],
        ]);
    }

    public function stop(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $validated = $request->validated();
        $ended = $this->lives->stopStream(
            $stream,
            $request->file('video'),
            $request->file('thumbnail'),
            array_key_exists('add_to_slider', $validated) ? (bool) $validated['add_to_slider'] : null,
            ! empty($validated['slider_id']) ? (int) $validated['slider_id'] : null,
            ! empty($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
        );

        return response()->json([
            'data' => $this->lives->formatStream($ended, true, true),
        ]);
    }

    public function toggleSlider(LiveAdminRequest $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        $validated = $request->validated();
        $enable = array_key_exists('enable', $validated)
            ? (bool) $validated['enable']
            : ! $stream->is_in_slider;

        $updated = $this->lives->syncToSlider(
            $stream,
            $enable,
            ! empty($validated['slider_id']) ? (int) $validated['slider_id'] : null
        );

        return response()->json([
            'data' => $this->lives->formatStream($updated, false, true),
        ]);
    }

    public function destroy(Request $request, string $workspace, string $live): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $liveId = (string) ($request->route('live') ?? $live);
        $stream = LiveStream::query()->where('workspace_id', $workspaceId)->findOrFail($liveId);

        if ($stream->is_in_slider) {
            $this->lives->syncToSlider($stream, false);
        }

        if (filled($stream->video_path) && Storage::disk('public')->exists($stream->video_path)) {
            Storage::disk('public')->delete($stream->video_path);
        }
        if (filled($stream->thumbnail_path) && Storage::disk('public')->exists($stream->thumbnail_path)) {
            Storage::disk('public')->delete($stream->thumbnail_path);
        }

        $stream->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
