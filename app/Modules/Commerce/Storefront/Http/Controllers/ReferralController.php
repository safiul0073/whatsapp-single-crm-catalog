<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Http\Requests\ReferralRequest;
use App\Modules\Commerce\Storefront\Models\Referral;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use App\Modules\Commerce\Storefront\Models\RewardLedgerEntry;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $referrals, private RewardWalletService $wallet) {}

    public function program(Request $r): JsonResponse
    {
        $w = $r->attributes->get('store_workspace')->id;
        $s = $this->referrals->settings($w);

        return response()->json(['data' => [
            'enabled' => $s->enabled,
            'currency' => StoreOrderSetting::forWorkspace($w)->currency,
            'reward_type' => $s->reward_type,
            'reward_value' => $s->reward_value,
            'reward_cap' => $s->reward_cap,
            'reward_trigger' => $s->reward_trigger,
            'reward_scope' => $s->reward_scope,
            'min_order_amount' => $s->min_order_amount,
            'referee_discount_type' => $s->referee_discount_type,
            'referee_discount_value' => $s->referee_discount_value,
            'referee_min_order' => $s->referee_min_order,
            'max_redeem_percent' => $s->max_redeem_percent,
            'min_spend_balance' => $s->min_spend_balance,
            'terms' => $s->terms,
        ]]);
    }

    public function show(ReferralRequest $r): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $precision = StoreOrderSetting::forWorkspace($c->workspace_id)->precision();
        $referrals = Referral::query()->where('workspace_id', $c->workspace_id)->where('referrer_customer_id', $c->id)->with('referee.contact')->latest()->limit(100)->get();
        $transactions = ReferralTransaction::query()->where('workspace_id', $c->workspace_id)->whereIn('referral_id', $referrals->pluck('id'))->get();

        return response()->json(['data' => [
            'enabled' => $this->referrals->settings($c->workspace_id)->enabled,
            'code' => $this->referrals->codeFor($c)->code,
            'stats' => [
                'referrals' => $referrals->count(),
                'pending' => $transactions->where('status', 'pending')->count(),
                'credited' => $transactions->where('status', 'credited')->count(),
                'earned' => OrderMoney::decimal((int) $transactions->where('status', 'credited')->sum('reward_minor'), $precision),
            ],
            'referees' => $referrals->map(fn (Referral $ref): array => [
                'name' => Str::limit((string) $ref->referee?->contact?->name, 1, '***'),
                'phone' => $this->mask((string) $ref->referee?->contact?->phone),
                'joined_at' => $ref->created_at?->toIso8601String(),
                'status' => $transactions->where('referral_id', $ref->id)->sortByDesc('id')->first()?->status ?? 'joined',
            ])->values(),
        ]]);
    }

    public function rewards(ReferralRequest $r): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $store = StoreOrderSetting::forWorkspace($c->workspace_id);
        $s = $this->referrals->settings($c->workspace_id);
        $page = RewardLedgerEntry::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->latest('id')->paginate(20);

        return response()->json([
            'data' => [
                'currency' => $store->currency,
                'balance' => OrderMoney::decimal($this->wallet->balance($c->workspace_id, $c->id), $store->precision()),
                'min_spend_balance' => $s->min_spend_balance,
                'max_redeem_percent' => $s->max_redeem_percent,
                'entries' => collect($page->items())->map(fn (RewardLedgerEntry $e): array => ['id' => $e->id, 'type' => $e->type, 'amount' => OrderMoney::decimal(abs($e->amount_minor), $store->precision()), 'direction' => $e->amount_minor < 0 ? 'debit' : 'credit', 'balance_after' => OrderMoney::decimal(max(0, $e->balance_after_minor), $store->precision()), 'note' => $e->note, 'created_at' => $e->created_at?->toIso8601String()]),
            ],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    private function mask(string $phone): string
    {
        return strlen($phone) > 5 ? substr($phone, 0, 4).str_repeat('*', strlen($phone) - 6).substr($phone, -2) : '***';
    }
}
