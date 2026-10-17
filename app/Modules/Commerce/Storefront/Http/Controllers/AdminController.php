<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Http\Requests\AdminRequest;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Commerce\Storefront\Jobs\VerifyStoreDomainJob;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontMenu;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSection;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Commerce\Storefront\Services\DomainResolver;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private WorkspaceResolver $workspaces, private CmsService $cms) {}

    private function workspace(Request $r): int
    {
        return $this->workspaces->current($r->user())->id;
    }

    public function index(Request $r): View
    {
        $w = $this->workspace($r);

        return view('commerce::user.storefront', ['settings' => StorefrontSetting::query()->where('workspace_id', $w)->first()?->config ?? $this->cms->settings($w), 'pages' => StorefrontPage::query()->where('workspace_id', $w)->get(), 'domains' => StoreDomain::query()->where('workspace_id', $w)->get(), 'sliders' => StorefrontSlider::query()->where('workspace_id', $w)->get(), 'menus' => StorefrontMenu::query()->where('workspace_id', $w)->get(), 'coupons' => Coupon::query()->where('workspace_id', $w)->get(), 'reviews' => ProductReview::query()->where('workspace_id', $w)->where('status', 'pending')->get(), 'contents' => StorefrontContent::query()->where('workspace_id', $w)->latest()->limit(100)->get(), 'types' => CmsService::TYPES]);
    }

    public function settings(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        StorefrontSetting::query()->updateOrCreate(['workspace_id' => $w], ['config' => $r->validated('config')]);
        $this->invalidate($w);

        return back()->with('success', 'Settings saved.');
    }

    public function editor(Request $r, int $id): View
    {
        $w = $this->workspace($r);

        return view('commerce::user.storefront-page', ['page' => StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id), 'sections' => StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $id)->orderBy('position')->get(), 'contents' => StorefrontContent::query()->where('workspace_id', $w)->latest()->limit(100)->get(), 'types' => CmsService::TYPES]);
    }

    public function savePage(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $sections = array_map(fn ($s) => $this->cms->validateSection($s), $r->validated('sections'));
        DB::transaction(function () use ($r, $id, $w, $sections): void {
            $page = $id ? StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id) : new StorefrontPage(['workspace_id' => $w]);
            abort_if(StorefrontPage::query()->where('workspace_id', $w)->where('slug', $r->input('slug'))->when($id, fn ($q) => $q->whereKeyNot($id))->exists(), 422, 'Page slug already exists.');
            $page->fill($r->safe()->only(['slug', 'title', 'seo']))->save();
            StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $page->id)->delete();
            foreach ($sections as $i => $s) {
                StorefrontSection::query()->create(['workspace_id' => $w, 'page_id' => $page->id, ...array_intersect_key($s, array_flip(['type', 'config', 'active', 'visibility', 'starts_at', 'ends_at'])), 'position' => $i]);
            }
        });

        return redirect()->route('user.commerce.storefront.index')->with('success', 'Draft saved. Publish to update the storefront.');
    }

    public function publish(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $p = StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id);
        $sections = StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $p->id)->orderBy('position')->get()->toArray();
        $p->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => $p->title, 'seo' => $p->seo, 'sections' => $sections]]);
        $this->invalidate($w);

        return back()->with('success', 'Page published.');
    }

    public function preview(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $p = StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id);
        $host = StoreDomain::query()->where('workspace_id', $w)->where('is_primary', true)->where('status', 'active')->value('host');
        abort_unless($host, 422, 'Configure an active primary domain.');
        $url = parse_url(config('storefront.url'));

        return redirect()->away(($url['scheme'] ?? 'http').'://'.$host.(isset($url['port']) ? ':'.$url['port'] : '').'/api/draft?token='.urlencode($this->cms->previewToken($w, $p->slug)).'&slug='.urlencode($p->slug));
    }

    public function slider(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $s = $id ? StorefrontSlider::query()->where('workspace_id', $w)->findOrFail($id) : new StorefrontSlider(['workspace_id' => $w]);
        $s->fill($r->validated())->save();
        $this->invalidate($w);

        return back()->with('success', 'Slider saved.');
    }

    public function menu(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        StorefrontMenu::query()->updateOrCreate(['workspace_id' => $w, 'slot' => $r->validated('slot')], ['items' => $r->validated('items')]);
        $this->invalidate($w);

        return back()->with('success', 'Menu saved.');
    }

    public function addDomain(AdminRequest $r): RedirectResponse
    {
        $host = DomainResolver::normalize($r->validated('host'));
        abort_if(StoreDomain::query()->where('host', $host)->exists(), 422, 'Domain already registered.');
        $d = StoreDomain::query()->create(['workspace_id' => $this->workspace($r), 'host' => $host, 'verification_token' => Str::random(48)]);
        VerifyStoreDomainJob::dispatch($d->id);

        return back()->with('success', 'Domain added. Configure DNS, then verify.');
    }

    public function verifyDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $d = StoreDomain::query()->where('workspace_id', $this->workspace($r))->findOrFail($id);
        VerifyStoreDomainJob::dispatch($d->id);

        return back()->with('success', 'Verification queued.');
    }

    public function primaryDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        DB::transaction(function () use ($w, $id): void {
            $this->workspacesLock($w);
            $d = StoreDomain::query()->where('workspace_id', $w)->where('status', 'active')->findOrFail($id);
            StoreDomain::query()->where('workspace_id', $w)->update(['is_primary' => false]);
            $d->update(['is_primary' => true]);
        });

        return back();
    }

    private function workspacesLock(int $w): void
    {
        Workspace::query()->lockForUpdate()->findOrFail($w);
    }

    public function deleteDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $d = StoreDomain::query()->where('workspace_id', $this->workspace($r))->findOrFail($id);
        abort_if($d->is_primary || $d->type === 'system', 422, 'Keep the system domain and choose another primary before removing this domain.');
        $d->delete();

        return back();
    }

    public function coupon(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $c = $id ? Coupon::query()->where('workspace_id', $w)->findOrFail($id) : new Coupon(['workspace_id' => $w]);
        $c->fill($r->validated())->save();

        return back()->with('success', 'Coupon saved.');
    }

    public function review(AdminRequest $r, int $id): RedirectResponse
    {
        ProductReview::query()->where('workspace_id', $this->workspace($r))->findOrFail($id)->update($r->validated());

        return back();
    }

    public function content(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $record = $id ? StorefrontContent::query()->where('workspace_id', $w)->whereIn('type', ['blog', 'faq', 'collection'])->findOrFail($id) : new StorefrontContent(['workspace_id' => $w]);
        $record->fill($r->safe()->only(['type', 'slug', 'data']));
        $record->published_at = $r->boolean('published') ? now() : null;
        $record->save();
        $this->invalidate($w);

        return back()->with('success', 'Content saved.');
    }

    public function renameDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $domain = StoreDomain::query()->where('workspace_id', $this->workspace($r))->where('type', 'system')->findOrFail($id);
        $host = $r->validated('handle').'.'.config('storefront.root_domain');
        abort_if(StoreDomain::query()->where('host', $host)->whereKeyNot($id)->exists(), 422, 'Handle is already in use.');
        $domain->update(['handle' => $r->validated('handle'), 'host' => $host]);

        return back()->with('success', 'Store handle updated.');
    }

    private function invalidate(int $w): void
    {
        RevalidateStorefront::dispatch($w, ['all']);
    }
}
