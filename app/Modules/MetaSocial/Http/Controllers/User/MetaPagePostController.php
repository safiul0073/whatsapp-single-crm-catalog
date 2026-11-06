<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Http\Requests\StoreMetaPagePostRequest;
use App\Modules\MetaSocial\Models\MetaPagePost;
use App\Modules\MetaSocial\Services\MetaPagePostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MetaPagePostController extends Controller
{
    public function __construct(
        protected MetaPagePostService $posts,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->posts->context($request->user());
        $pageId = (string) ($request->query('page_id') ?: ($context['pages'][0]['id'] ?? ''));
        $instagramId = (string) ($request->query('instagram_account_id') ?: ($context['instagram_accounts'][0]['id'] ?? ''));

        return view('meta-social::user.posts', [
            'context' => $context,
            'pageId' => $pageId,
            'instagramId' => $instagramId,
            'recent' => $request->boolean('recent') && $pageId !== '' ? $this->posts->recent($request->user(), $pageId, $instagramId ?: null) : null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('meta-social::user.post-create', [
            'context' => $this->posts->context($request->user()),
            'selectedProductId' => $request->integer('product_id') ?: null,
        ]);
    }

    public function store(StoreMetaPagePostRequest $request): RedirectResponse
    {
        $post = $this->posts->create($request->user(), $request->validated());

        return redirect()->route('user.meta-social.posts.index')->with('status', $post->scheduled_at?->isFuture()
            ? __('Post scheduled for :time.', ['time' => $post->scheduled_at->toDayDateTimeString()])
            : __('Post sent. Check its status below.'));
    }

    public function retry(Request $request, string $post): RedirectResponse
    {
        $this->posts->retry($this->post($request, $post));

        return back()->with('status', __('Post sent again. Check its status below.'));
    }

    public function destroy(Request $request, string $post): RedirectResponse
    {
        $metaPost = $this->post($request, $post);

        try {
            $this->posts->delete($metaPost);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', __('Post deleted.'));
    }

    protected function post(Request $request, string $id): MetaPagePost
    {
        return MetaPagePost::query()
            ->where('workspace_id', $this->workspaces->current($request->user())->id)
            ->findOrFail($id);
    }
}
