<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Http\Requests\LiveStreamRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\LiveStream;
use App\Modules\Commerce\Storefront\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveController extends Controller
{
    public function __construct(
        protected LiveStreamService $lives,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $settings = $this->lives->settings($workspaceId);

        if (! $settings['enabled']) {
            return response()->json([
                'data' => [
                    'enabled' => false,
                    'active_live' => null,
                    'live_streams' => [],
                    'scheduled_streams' => [],
                    'recorded_streams' => [],
                    'reverb' => $settings['reverb'],
                ],
            ]);
        }

        $allStreams = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['live', 'scheduled', 'ended'])
            ->with([
                'products' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
                'pinnedProduct' => fn ($q) => $q->with(['primaryMedia', 'category', 'brandRecord', 'variants', 'colors', 'options.values', 'tierPrices']),
            ])
            ->latest('id')
            ->limit(60)
            ->get();

        $liveStreams = $allStreams
            ->filter(fn (LiveStream $s): bool => $s->status === 'live' && $s->hasPlatform('storefront'))
            ->values()
            ->map(fn (LiveStream $s): array => $this->lives->formatStream($s, false, false))
            ->all();

        $scheduledStreams = $allStreams
            ->filter(fn (LiveStream $s): bool => $s->status === 'scheduled' && $s->hasPlatform('storefront'))
            ->values()
            ->map(fn (LiveStream $s): array => $this->lives->formatStream($s, false, false))
            ->all();

        $recordedStreams = $allStreams
            ->filter(fn (LiveStream $s): bool => $s->status === 'ended' && (filled($s->video_path) || $s->hasPlatform('storefront')))
            ->values()
            ->map(fn (LiveStream $s): array => $this->lives->formatStream($s, false, false))
            ->all();

        return response()->json([
            'data' => [
                'enabled' => true,
                'active_live' => $liveStreams[0] ?? null,
                'live_streams' => $liveStreams,
                'scheduled_streams' => $scheduledStreams,
                'recorded_streams' => $recordedStreams,
                'reverb' => $settings['reverb'],
            ],
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $settings = $this->lives->settings($workspaceId);

        if (! $settings['enabled']) {
            return response()->json([
                'data' => [
                    'enabled' => false,
                    'active_live' => null,
                    'reverb' => $settings['reverb'],
                ],
            ]);
        }

        $activeStream = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'live')
            ->latest('started_at')
            ->get()
            ->first(fn (LiveStream $s): bool => $s->hasPlatform('storefront'));

        return response()->json([
            'data' => [
                'enabled' => true,
                'active_live' => $activeStream ? $this->lives->formatStream($activeStream, false, false) : null,
                'reverb' => $settings['reverb'],
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $stream = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => $this->lives->formatStream($stream, true, false),
        ]);
    }

    public function signal(LiveStreamRequest $request, string $slug): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $stream = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->firstOrFail();

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

    public function comment(LiveStreamRequest $request, string $slug): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $stream = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless((bool) $stream->chat_enabled, 422, 'Live chat is disabled for this stream.');

        $settings = $this->lives->settings($workspaceId);
        /** @var Customer|null $customer */
        $customer = $request->attributes->get('store_customer');

        if (! $customer && ! $settings['allow_guest_chat']) {
            abort(401, 'Please sign in to post a comment in the live chat.');
        }

        $validated = $request->validated();
        $authorName = $customer?->contact?->name
            ?: (trim((string) ($validated['author_name'] ?? '')) ?: 'Guest Viewer');

        $comment = $this->lives->addComment($stream, [
            'customer_id' => $customer?->id,
            'author_name' => $authorName,
            'author_role' => $customer ? 'customer' : 'guest',
            'body' => (string) $validated['body'],
        ]);

        return response()->json([
            'data' => $this->lives->formatComment($comment),
        ], 201);
    }

    public function react(LiveStreamRequest $request, string $slug): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('store_workspace')->id;
        $stream = LiveStream::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->firstOrFail();

        $action = (string) $request->validated('action');
        $updated = $this->lives->recordEngagement($stream, $action);

        return response()->json([
            'data' => [
                'viewer_count' => (int) $updated->viewer_count + (int) $updated->facebook_viewer_count,
                'storefront_viewer_count' => (int) $updated->viewer_count,
                'facebook_viewer_count' => (int) $updated->facebook_viewer_count,
                'likes_count' => (int) $updated->likes_count,
                'total_views' => (int) $updated->total_views,
            ],
        ]);
    }
}
