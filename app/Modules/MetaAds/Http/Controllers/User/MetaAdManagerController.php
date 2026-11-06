<?php

namespace App\Modules\MetaAds\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaAds\Http\Requests\MetaAdCreatorPermissionRequest;
use App\Modules\MetaAds\Http\Requests\MetaAdLookupRequest;
use App\Modules\MetaAds\Http\Requests\StoreMetaAdRequest;
use App\Modules\MetaAds\Models\MetaAd;
use App\Modules\MetaAds\Services\MetaAdBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MetaAdManagerController extends Controller
{
    public function __construct(
        protected MetaAdBuilderService $builder,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->builder->context($request->user());

        return view('meta-ads::user.ads', [
            'context' => $context,
            'results' => $context['ads']->mapWithKeys(fn (MetaAd $ad): array => [$ad->id => $this->builder->results($ad)])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('meta-ads::user.create-ad', [
            'context' => $this->builder->context($request->user()),
            'selectedProductId' => $request->integer('product_id') ?: null,
        ]);
    }

    public function store(StoreMetaAdRequest $request): RedirectResponse
    {
        $ad = $this->builder->create($request->user(), $request->validated());

        return redirect()->route('user.meta-ads.ads.index')->with('status', __('":name" is being created in Meta. It stays paused until you press Start.', ['name' => $ad->name]));
    }

    public function start(Request $request, string $ad): RedirectResponse
    {
        $metaAd = $this->ad($request, $ad);

        return $this->act(fn () => $this->builder->start($metaAd), __('Ad started. Meta will begin spending your daily budget.'));
    }

    public function pause(Request $request, string $ad): RedirectResponse
    {
        $metaAd = $this->ad($request, $ad);

        return $this->act(fn () => $this->builder->pause($metaAd), __('Ad paused.'));
    }

    public function destroy(Request $request, string $ad): RedirectResponse
    {
        $metaAd = $this->ad($request, $ad);

        return $this->act(fn () => $this->builder->remove($metaAd), __('Ad deleted from Meta.'));
    }

    public function pagePosts(MetaAdLookupRequest $request): JsonResponse
    {
        return $this->json(fn (): array => ['posts' => $this->builder->pagePosts($request->user(), (string) $request->validated('page_id'))]);
    }

    public function partnershipContent(MetaAdLookupRequest $request): JsonResponse
    {
        return $this->json(fn (): array => ['content' => $this->builder->partnershipContent(
            $request->user(),
            (string) $request->validated('page_id'),
            $request->validated('instagram_account_id'),
            $request->validated('search'),
        )]);
    }

    public function creatorPermission(MetaAdCreatorPermissionRequest $request): JsonResponse
    {
        return $this->json(function () use ($request): array {
            $this->builder->requestCreatorPermission($request->user(), (string) $request->validated('instagram_account_id'), (string) $request->validated('creator_username'));

            return ['message' => __('Request sent. The creator approves it in their Instagram app.')];
        });
    }

    protected function ad(Request $request, string $id): MetaAd
    {
        return MetaAd::query()
            ->where('workspace_id', $this->workspaces->current($request->user())->id)
            ->where('status', '!=', MetaAd::STATUS_DELETED)
            ->findOrFail($id);
    }

    protected function act(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $success);
    }

    protected function json(callable $load): JsonResponse
    {
        try {
            return response()->json($load());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
