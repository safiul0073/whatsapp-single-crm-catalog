<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Http\Requests\ReferralAdminRequest;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ReferralCustomerRule;
use App\Modules\Commerce\Storefront\Models\ReferralTargetSlab;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use App\Modules\Commerce\Storefront\Models\RewardLedgerEntry;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralAdminController extends Controller
{
    public function __construct(private WorkspaceResolver $workspaces, private ReferralService $referrals, private RewardWalletService $wallet) {}

    private function workspace(Request $r): int
    {
        return $this->workspaces->current($r->user())->id;
    }

    public function index(Request $r): View
    {
        $w = $this->workspace($r);
        $store = StoreOrderSetting::forWorkspace($w);
        $transactions = $this->transactions($r, $w);
        $all = ReferralTransaction::query()->where('workspace_id', $w);
        $balances = RewardLedgerEntry::query()->where('workspace_id', $w)->selectRaw('customer_id, SUM(amount_minor) as balance_minor, MAX(created_at) as last_activity')->groupBy('customer_id')->orderByDesc('balance_minor')->with('customer.contact')->paginate(25, ['*'], 'wallets_page');

        return view('commerce::user.referrals', [
            'settings' => $this->referrals->settings($w),
            'currency' => $store->currency,
            'precision' => $store->precision(),
            'stats' => [
                'total' => (clone $all)->count(),
                'pending' => (clone $all)->where('status', 'pending')->count(),
                'credited' => (clone $all)->where('status', 'credited')->count(),
                'rewarded' => OrderMoney::decimal((int) RewardLedgerEntry::query()->where('workspace_id', $w)->whereIn('type', ['referral_reward', 'target_bonus'])->sum('amount_minor'), $store->precision()),
                'cashin_pending' => CashinRequest::query()->where('workspace_id', $w)->where('status', 'pending')->count(),
                'cashin_pending_amount' => OrderMoney::decimal((int) CashinRequest::query()->where('workspace_id', $w)->where('status', 'pending')->sum('amount_minor'), $store->precision()),
            ],
            'transactions' => $transactions->with(['referral.referrer.contact', 'referral.referee.contact', 'order'])->latest('id')->paginate(25, ['*'], 'tracking_page')->withQueryString(),
            'slabs' => ReferralTargetSlab::query()->where('workspace_id', $w)->orderBy('min_referrals')->get(),
            'rules' => ReferralCustomerRule::query()->where('workspace_id', $w)->with('customer.contact')->latest('id')->get(),
            'wallets' => $balances,
            'cashins' => CashinRequest::query()->where('workspace_id', $w)->when($r->filled('cashin_status'), fn (Builder $q) => $q->where('status', $r->input('cashin_status')))->with('customer.contact')->latest('id')->paginate(25, ['*'], 'cashins_page')->withQueryString(),
        ]);
    }

    /**
     * Search registered customers for the rule and wallet pickers (10 results).
     */
    public function customers(Request $r): JsonResponse
    {
        $term = trim((string) $r->input('q'));
        $customers = Customer::query()->where('workspace_id', $this->workspace($r))->whereNotNull('registered_at')
            ->when($term !== '', fn (Builder $q) => $q->whereHas('contact', fn (Builder $c) => $c->where('name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->with('contact')->latest('id')->limit(10)->get();

        return response()->json($customers->map(fn (Customer $c): array => ['id' => $c->id, 'name' => $c->contact?->name, 'phone' => $c->contact?->phone])->values());
    }

    public function export(Request $r): StreamedResponse
    {
        $w = $this->workspace($r);
        $precision = StoreOrderSetting::forWorkspace($w)->precision();
        $rows = $this->transactions($r, $w)->with(['referral.referrer.contact', 'referral.referee.contact', 'order'])->latest('id')->limit(10000)->get();

        return response()->streamDownload(function () use ($rows, $precision): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Referrer', 'Referrer phone', 'Referred', 'Referred phone', 'Order', 'Order amount', 'Reward', 'Currency', 'Status', 'Date']);
            foreach ($rows as $t) {
                fputcsv($out, [$t->referral?->referrer?->contact?->name, $t->referral?->referrer?->contact?->phone, $t->referral?->referee?->contact?->name, $t->referral?->referee?->contact?->phone, $t->order?->number, OrderMoney::decimal($t->order_amount_minor, $precision), OrderMoney::decimal($t->reward_minor, $precision), $t->currency, $t->status, $t->created_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'referrals-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function saveSettings(ReferralAdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $settings = $this->referrals->settings($w);
        $settings->fill($r->validated())->save();

        return $this->back('settings', __('Referral settings saved.'));
    }

    public function saveSlab(ReferralAdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $slab = $id ? ReferralTargetSlab::query()->where('workspace_id', $w)->findOrFail($id) : new ReferralTargetSlab(['workspace_id' => $w]);
        $slab->fill($r->validated())->save();

        return $this->back('slabs', __('Target slab saved.'));
    }

    public function deleteSlab(Request $r, int $id): RedirectResponse
    {
        ReferralTargetSlab::query()->where('workspace_id', $this->workspace($r))->findOrFail($id)->delete();

        return $this->back('slabs', __('Target slab deleted.'));
    }

    public function saveRule(ReferralAdminRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspace($r);
        $data = $r->validated();
        Customer::query()->where('workspace_id', $w)->findOrFail($data['customer_id']);
        $rule = $id ? ReferralCustomerRule::query()->where('workspace_id', $w)->findOrFail($id) : ReferralCustomerRule::query()->firstOrNew(['workspace_id' => $w, 'customer_id' => $data['customer_id']]);
        $rule->fill($data)->save();

        return $this->back('rules', __('Customer rule saved.'));
    }

    public function deleteRule(Request $r, int $id): RedirectResponse
    {
        ReferralCustomerRule::query()->where('workspace_id', $this->workspace($r))->findOrFail($id)->delete();

        return $this->back('rules', __('Customer rule deleted.'));
    }

    public function adjustWallet(ReferralAdminRequest $r): RedirectResponse
    {
        $w = $this->workspace($r);
        $data = $r->validated();
        Customer::query()->where('workspace_id', $w)->findOrFail($data['customer_id']);
        $amount = OrderMoney::minor($data['amount'], StoreOrderSetting::forWorkspace($w)->precision());
        $this->wallet->post($w, (int) $data['customer_id'], $data['direction'] === 'debit' ? -$amount : $amount, 'adjustment', null, null, $data['note'], $r->user()->id);

        return $this->back('wallets', __('Wallet adjusted.'));
    }

    public function updateCashin(ReferralAdminRequest $r, int $id): RedirectResponse
    {
        $w = $this->workspace($r);
        $status = $r->validated('status');
        DB::transaction(function () use ($w, $id, $status, $r): void {
            $cashin = CashinRequest::query()->where('workspace_id', $w)->lockForUpdate()->findOrFail($id);
            $allowed = ['pending' => ['approved', 'rejected', 'paid'], 'approved' => ['paid', 'rejected']][$cashin->status] ?? [];
            abort_unless(in_array($status, $allowed, true), 422, __('This cash-in request can no longer be changed to :status.', ['status' => $status]));
            if ($status === 'rejected') {
                $this->wallet->post($w, $cashin->customer_id, $cashin->amount_minor, 'cashin_refund', 'cashin-refund:'.$cashin->id, $cashin, 'Cash-in rejected');
            }
            $cashin->update(['status' => $status, 'admin_note' => $r->validated('admin_note'), 'processed_by' => $r->user()->id, 'processed_at' => now()]);
        });

        return $this->back('cashins', __('Cash-in request updated.'));
    }

    private function transactions(Request $r, int $w): Builder
    {
        return ReferralTransaction::query()->where('workspace_id', $w)
            ->when(in_array($r->input('status'), ['pending', 'credited', 'cancelled'], true), fn (Builder $q) => $q->where('status', $r->input('status')))
            ->when($r->date('from'), fn (Builder $q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($r->date('to'), fn (Builder $q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->when($r->filled('search'), fn (Builder $q) => $q->whereHas('referral.referrer.contact', fn (Builder $c) => $c->where('name', 'like', '%'.$r->input('search').'%')->orWhere('phone', 'like', '%'.$r->input('search').'%')));
    }

    private function back(string $tab, string $message): RedirectResponse
    {
        return redirect()->to(route('user.commerce.referrals.index').'?tab='.$tab)->with('success', $message);
    }
}
