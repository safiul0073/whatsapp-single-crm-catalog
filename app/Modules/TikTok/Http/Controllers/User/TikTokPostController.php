<?php

namespace App\Modules\TikTok\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\TikTok\Http\Requests\StoreTikTokPostRequest;
use App\Modules\TikTok\Models\TikTokPost;
use App\Modules\TikTok\Services\TikTokPostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TikTokPostController extends Controller
{
    public function __construct(
        protected TikTokPostService $posts,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        TikTokPost::query()->with('channelAccount')->where('workspace_id', $workspaceId)->where('status', TikTokPost::STATUS_PROCESSING)
            ->get()->each(fn (TikTokPost $post) => $this->posts->checkStatus($post));

        return view('tiktok::user.posts', [
            'accounts' => $this->posts->accounts($request->user()),
            'posts' => TikTokPost::query()->with('channelAccount:id,name')->where('workspace_id', $workspaceId)->latest()->limit(50)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $accounts = $this->posts->accounts($request->user());
        $account = $accounts->firstWhere('id', $request->integer('account')) ?? $accounts->first();
        $creator = null;
        $creatorError = null;

        if ($account && $this->posts->canDirectPost($account)) {
            try {
                $creator = $this->posts->creatorInfo($account);
            } catch (RuntimeException $exception) {
                $creatorError = $exception->getMessage();
            }
        }

        return view('tiktok::user.post-create', [
            'accounts' => $accounts,
            'account' => $account,
            'canDirectPost' => $account ? $this->posts->canDirectPost($account) : false,
            'canSendDraft' => $account ? $this->posts->canSendDraft($account) : false,
            'creator' => $creator,
            'creatorError' => $creatorError,
            'products' => Product::query()->where('workspace_id', $this->workspaces->current($request->user())->id)->latest('id')->limit(200)->get(['id', 'name']),
            'selectedProductId' => $request->integer('product_id') ?: null,
        ]);
    }

    public function store(StoreTikTokPostRequest $request): RedirectResponse
    {
        $post = $this->posts->create($request->user(), $request->safe()->except('video'), $request->file('video'));

        return redirect()->route('user.tiktok.posts.index')->with('status', $post->mode === TikTokPost::MODE_DIRECT
            ? __('Video sent to TikTok. It can take a few minutes to process before it appears on your profile.')
            : __('Video sent to your TikTok inbox. Open the TikTok app notification to finish editing and post it.'));
    }

    public function refresh(Request $request, string $post): RedirectResponse
    {
        $this->posts->checkStatus($this->post($request, $post));

        return back();
    }

    public function destroy(Request $request, string $post): RedirectResponse
    {
        $this->posts->delete($this->post($request, $post));

        return back()->with('status', __('Removed from this list. Posts already on TikTok stay there; delete them in the TikTok app.'));
    }

    protected function post(Request $request, string $id): TikTokPost
    {
        return TikTokPost::query()
            ->where('workspace_id', $this->workspaces->current($request->user())->id)
            ->findOrFail($id);
    }
}
