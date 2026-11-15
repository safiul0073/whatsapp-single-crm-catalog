<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Http\Requests\RefundAdminRequest;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\RefundRequest;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RefundRequestService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundAdminController extends Controller
{
    /** Queue tabs, each with the query that fills it. */
    private const TABS = ['pending_referrer', 'referrer_approved', 'referrer_rejected', 'awaiting_admin', 'processing', 'completed', 'admin_rejected'];

    public function __construct(private WorkspaceResolver $workspaces, private RefundRequestService $refunds, private ReferralService $referrals) {}

    private function workspace(Request $r): int
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::typeHas($w, 'referral'), 404);

        return $w;
    }

    public function index(Request $r): View
    {
        $w = $this->workspace($r);
        $tab = in_array($r->query('tab'), self::TABS, true) ? $r->query('tab') : 'awaiting_admin';
        $counts = [];
        foreach (self::TABS as $key) {
            $counts[$key] = $this->tab(RefundRequest::query()->where('workspace_id', $w), $key)->count();
        }
        $search = trim((string) $r->query('search'));

        return view('commerce::user.refunds', [
            'settings' => $this->referrals->settings($w),
            'precision' => StoreOrderSetting::forWorkspace($w)->precision(),
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'canManage' => $r->user()->can('commerce.refunds.manage'),
            'requests' => $this->tab(RefundRequest::query()->where('workspace_id', $w), $tab)
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('reference', 'like', '%'.$search.'%')->orWhereHas('order', fn (Builder $o) => $o->where('number', 'like', '%'.$search.'%'))))
                ->with(['order', 'customer.contact', 'referrer.contact'])->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Request $r, int $id): View
    {
        $w = $this->workspace($r);
        $request = RefundRequest::query()->where('workspace_id', $w)->with(['order.items', 'customer.contact', 'referrer.contact', 'events'])->findOrFail($id);

        return view('commerce::user.refund', [
            'refund' => $request,
            'precision' => $request->order->moneyPrecision(),
            'refundable' => $this->refunds->refundableMinor($request->order),
            'allowed' => RefundRequestService::ADMIN_TRANSITIONS[$request->status] ?? [],
            'channels' => CashinRequest::METHODS,
            'canManage' => $r->user()->can('commerce.refunds.manage'),
        ]);
    }

    public function saveSettings(RefundAdminRequest $r): RedirectResponse
    {
        $settings = $this->referrals->settings($this->workspace($r));
        $settings->fill($r->validated())->save();

        return redirect()->route('user.commerce.refunds.index')->with('success', __('Refund settings saved.'));
    }

    public function transition(RefundAdminRequest $r, int $id): RedirectResponse
    {
        $this->refunds->adminTransition($this->workspace($r), $id, $r->validated('status'), $r->safe()->except('status'), $r->user()->id);

        return redirect()->route('user.commerce.refunds.show', $id)->with('success', __('Refund request updated.'));
    }

    private function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'referrer_approved' => $query->where('referrer_decision', 'approved'),
            default => $query->where('status', $tab),
        };
    }
}
