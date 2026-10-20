<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Storefront\Http\Requests\AdminRequest;
use App\Modules\Commerce\Storefront\Http\Requests\ChatChannelsRequest;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Commerce\Storefront\Jobs\VerifyStoreDomainJob;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\CouponUsage;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $credentials = $this->cms->credentials($w, true);
        $settings = StorefrontSetting::query()->where('workspace_id', $w)->first()?->config ?? $this->cms->settings($w);

        return view('commerce::user.storefront', [
            'settings' => $settings,
            'chatWidgets' => ChatbotWidget::query()->where('workspace_id', $w)->where('is_active', true)->orderBy('name')->get(['name', 'public_token']),
            'credentials' => $credentials,
            'pages' => StorefrontPage::query()->where('workspace_id', $w)->get(),
            'domains' => StoreDomain::query()->where('workspace_id', $w)->get(),
            'sliders' => StorefrontSlider::query()->where('workspace_id', $w)->get(),
            'menus' => StorefrontMenu::query()->where('workspace_id', $w)->get(),
            'defaultMenus' => [
                'header' => $this->cms->defaultMenuItems('header'),
                'footer' => $this->cms->defaultMenuItems('footer'),
                'mobile' => $this->cms->defaultMenuItems('mobile'),
            ],
            'categories' => Category::query()->where('workspace_id', $w)->orderBy('name')->get(),
            'brands' => Brand::query()->where('workspace_id', $w)->orderBy('name')->get(),
            'coupons' => Coupon::query()->where('workspace_id', $w)->get(),
            'reviews' => ProductReview::query()->where('workspace_id', $w)->where('status', 'pending')->get(),
            'contents' => StorefrontContent::query()->where('workspace_id', $w)->latest()->get(),
            'inquiries' => StorefrontContent::query()->where('workspace_id', $w)->where('type', 'contact')->latest()->get(),
            'blogs' => StorefrontContent::query()->where('workspace_id', $w)->where('type', 'blog')->latest()->get(),
            'faqs' => StorefrontContent::query()->where('workspace_id', $w)->where('type', 'faq')->latest()->get(),
            'collections' => StorefrontContent::query()->where('workspace_id', $w)->where('type', 'collection')->latest()->get(),
            'types' => CmsService::TYPES,
        ]);
    }

    private function redirectTab(Request $request, string $defaultTab, ?string $message = null, string $level = 'success'): RedirectResponse
    {
        $tab = (string) ($request->input('_tab') ?: $defaultTab);
        session()->flash('storefront_tab', $tab);
        $url = route('user.commerce.storefront.index').'?tab='.$tab.'#'.$tab;

        $response = redirect()->to($url);
        if ($message) {
            $response->with($level, $message);
        }

        return $response;
    }

    public function settings(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $existing = (array) (StorefrontSetting::query()->where('workspace_id', $w)->first()?->config ?? []);
        $newConfig = $r->validated('config');
        unset($newConfig['chat_channels']);
        if (isset($existing['chat_channels'])) {
            $newConfig['chat_channels'] = $existing['chat_channels'];
        }
        if (isset($existing['deployment'])) {
            $newConfig['deployment'] = $existing['deployment'];
        }

        if ($r->hasFile('android_app_file')) {
            $file = $r->file('android_app_file');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'app-android-'.time().'.'.$ext;
            $path = $file->storeAs('storefront/apps', $filename, 'public');
            $newConfig['app_android_url'] = Storage::disk('public')->url($path);
        }

        if ($r->hasFile('ios_app_file')) {
            $file = $r->file('ios_app_file');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'app-ios-'.time().'.'.$ext;
            $path = $file->storeAs('storefront/apps', $filename, 'public');
            $newConfig['app_ios_url'] = Storage::disk('public')->url($path);
        }

        StorefrontSetting::query()->updateOrCreate(['workspace_id' => $w], ['config' => $newConfig]);
        $this->invalidate($w);

        return $this->redirectTab($r, 'settings', 'Settings saved.');
    }

    public function uploadApp(AdminRequest $r): JsonResponse
    {
        $this->workspace($r);
        $file = $r->file('file');
        $platform = $r->input('platform', 'android') === 'ios' ? 'ios' : 'android';
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = 'app-'.$platform.'-'.time().'.'.$ext;
        $path = $file->storeAs('storefront/apps', $filename, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
            'platform' => $platform,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    public function chatChannels(ChatChannelsRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $existing = (array) (StorefrontSetting::query()->where('workspace_id', $w)->first()?->config ?? []);
        $channels = array_replace_recursive(CmsService::CHAT_CHANNEL_DEFAULTS, $r->validated('chat_channels'));
        $channels['enabled'] = (bool) $channels['enabled'];
        foreach (['whatsapp', 'messenger', 'telegram', 'custom_chatbot'] as $channel) {
            $channels[$channel]['enabled'] = (bool) $channels[$channel]['enabled'];
        }
        $existing['chat_channels'] = $channels;
        StorefrontSetting::query()->updateOrCreate(['workspace_id' => $w], ['config' => $existing]);
        $this->invalidate($w);

        return $this->redirectTab($r, 'chat-channels', 'Contact channels saved.');
    }

    public function generateCredentials(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $target = (string) ($r->validated('target') ?? 'all');
        $this->cms->rotateCredentials($w, $target);

        return $this->redirectTab($r, 'deployment', $target === 'all' ? 'All storefront API secrets regenerated.' : 'Storefront secret key rotated.');
    }

    public function updateCredentials(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $this->cms->saveDeploymentEndpoints($w, $r->validated());

        return $this->redirectTab($r, 'deployment', 'Storefront deployment endpoints saved.');
    }

    public function editor(Request $r, int $id): View
    {
        $w = $this->workspace($r);

        return view('commerce::user.storefront-page', [
            'page' => StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id),
            'sections' => StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $id)->orderBy('position')->get(),
            'contents' => StorefrontContent::query()->where('workspace_id', $w)->latest()->limit(100)->get(),
            'sliders' => StorefrontSlider::query()->where('workspace_id', $w)->get(),
            'categories' => Category::query()->where('workspace_id', $w)->orderBy('name')->get(),
            'brands' => Brand::query()->where('workspace_id', $w)->orderBy('name')->get(),
            'products' => Product::query()->where('workspace_id', $w)->where('status', 'active')->select(['id', 'name', 'slug', 'primary_media_id'])->with('primaryMedia')->latest()->limit(100)->get(),
            'types' => CmsService::TYPES,
        ]);
    }

    public function savePage(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $sections = array_map(fn ($s) => $this->cms->validateSection($s), $r->validated('sections'));
        $page = null;
        DB::transaction(function () use ($r, $id, $w, $sections, &$page): void {
            $page = $id ? StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id) : new StorefrontPage(['workspace_id' => $w]);
            abort_if(StorefrontPage::query()->where('workspace_id', $w)->where('slug', $r->input('slug'))->when($id, fn ($q) => $q->whereKeyNot($id))->exists(), 422, 'Page slug already exists.');
            $page->fill($r->safe()->only(['slug', 'title', 'seo']))->save();
            StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $page->id)->delete();
            foreach ($sections as $i => $s) {
                StorefrontSection::query()->create(['workspace_id' => $w, 'page_id' => $page->id, ...array_intersect_key($s, array_flip(['type', 'config', 'active', 'visibility', 'starts_at', 'ends_at'])), 'position' => $i]);
            }
        });

        if ($r->boolean('publish_now') && $page) {
            $sectionRecords = StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $page->id)->orderBy('position')->get()->toArray();
            $page->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => $page->title, 'seo' => $page->seo, 'sections' => $sectionRecords]]);
            $this->invalidate($w);

            return redirect()->route('user.commerce.storefront.pages.edit', $page->id)->with('success', 'Page saved and published live to your storefront.');
        }

        if ($r->input('save_action') === 'stay' || $id) {
            return redirect()->route('user.commerce.storefront.pages.edit', $page->id)->with('success', 'Page draft saved successfully.');
        }

        return redirect()->route('user.commerce.storefront.pages.edit', $page->id)->with('success', 'New page created! Start adding sections below.');
    }

    public function publish(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $p = StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id);
        $sections = StorefrontSection::query()->where('workspace_id', $w)->where('page_id', $p->id)->orderBy('position')->get()->toArray();
        $p->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => $p->title, 'seo' => $p->seo, 'sections' => $sections]]);
        $this->invalidate($w);

        if ($r->input('_tab') === 'pages' || str_contains($r->headers->get('referer', ''), 'commerce/storefront')) {
            return $this->redirectTab($r, 'pages', 'Page published.');
        }

        return back()->with('success', 'Page published.');
    }

    public function deletePage(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $p = StorefrontPage::query()->where('workspace_id', $w)->findOrFail($id);
        abort_if($p->slug === 'home', 422, 'The home page cannot be deleted.');
        $p->delete();
        $this->invalidate($w);

        return $this->redirectTab($r, 'pages', 'Page deleted.');
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

        session()->flash('storefront_slider', (string) $s->id);

        return $this->redirectTab($r, 'sliders', 'Slider saved.');
    }

    public function menu(AdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $slot = $r->validated('slot');
        StorefrontMenu::query()->updateOrCreate(['workspace_id' => $w, 'slot' => $slot], ['items' => $r->validated('items')]);
        $this->invalidate($w);

        session()->flash('storefront_tab', 'menus');

        return redirect()
            ->to(route('user.commerce.storefront.index').'?tab=menus&slot='.$slot.'#menus')
            ->with('success', 'Menu saved.');
    }

    public function addDomain(AdminRequest $r): RedirectResponse
    {
        $host = DomainResolver::normalize($r->validated('host'));
        abort_if(StoreDomain::query()->where('host', $host)->exists(), 422, 'Domain already registered.');
        $d = StoreDomain::query()->create(['workspace_id' => $this->workspace($r), 'host' => $host, 'verification_token' => Str::random(48)]);
        VerifyStoreDomainJob::dispatch($d->id);

        return $this->redirectTab($r, 'domains', 'Domain added. Configure DNS, then verify.');
    }

    public function verifyDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $d = StoreDomain::query()->where('workspace_id', $this->workspace($r))->findOrFail($id);
        VerifyStoreDomainJob::dispatch($d->id);

        return $this->redirectTab($r, 'domains', 'Verification queued.');
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

        return $this->redirectTab($r, 'domains', 'Primary domain updated.');
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

        return $this->redirectTab($r, 'domains', 'Domain deleted.');
    }

    public function coupon(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $c = $id ? Coupon::query()->where('workspace_id', $w)->findOrFail($id) : new Coupon(['workspace_id' => $w]);
        $c->fill($r->validated())->save();

        return $this->redirectTab($r, 'coupons', 'Coupon saved.');
    }

    public function toggleCoupon(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $c = Coupon::query()->where('workspace_id', $w)->findOrFail($id);
        if ($r->has('active')) {
            $c->active = (bool) $r->input('active');
        } else {
            $c->active = ! $c->active;
        }
        $c->save();

        return $this->redirectTab($r, 'coupons', 'Coupon status updated.');
    }

    public function deleteCoupon(AdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $c = Coupon::query()->where('workspace_id', $w)->findOrFail($id);
        DB::transaction(function () use ($c): void {
            CouponUsage::query()->where('coupon_id', $c->id)->delete();
            $c->delete();
        });

        return $this->redirectTab($r, 'coupons', 'Coupon deleted successfully.');
    }

    public function review(AdminRequest $r, int $id): RedirectResponse
    {
        ProductReview::query()->where('workspace_id', $this->workspace($r))->findOrFail($id)->update($r->validated());

        return $this->redirectTab($r, 'reviews', 'Review updated.');
    }

    public function content(AdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $record = $id ? StorefrontContent::query()->where('workspace_id', $w)->whereIn('type', ['blog', 'faq', 'collection'])->findOrFail($id) : new StorefrontContent(['workspace_id' => $w]);
        $record->fill($r->safe()->only(['type', 'slug', 'data']));
        $record->published_at = $r->boolean('published') ? now() : null;
        $record->save();
        $this->invalidate($w);

        return $this->redirectTab($r, 'content', 'Content saved.');
    }

    public function deleteContent(Request $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $record = StorefrontContent::query()->where('workspace_id', $w)->findOrFail($id);
        $tab = $record->type === 'contact' ? 'inquiries' : 'content';
        $record->delete();
        $this->invalidate($w);

        return $this->redirectTab($r, $tab, 'Item deleted.');
    }

    public function renameDomain(AdminRequest $r, int $id): RedirectResponse
    {
        $domain = StoreDomain::query()->where('workspace_id', $this->workspace($r))->where('type', 'system')->findOrFail($id);
        $host = $r->validated('handle').'.'.config('storefront.root_domain');
        abort_if(StoreDomain::query()->where('host', $host)->whereKeyNot($id)->exists(), 422, 'Handle is already in use.');
        $domain->update(['handle' => $r->validated('handle'), 'host' => $host]);

        return $this->redirectTab($r, 'domains', 'Store handle updated.');
    }

    private function invalidate(int $w): void
    {
        RevalidateStorefront::dispatch($w, ['all']);
    }
}
