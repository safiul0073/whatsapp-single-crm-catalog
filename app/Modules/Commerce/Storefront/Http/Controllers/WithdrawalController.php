<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Http\Requests\WithdrawalRequest;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
use App\Modules\Commerce\Storefront\Services\WithdrawalScheduleService;
use App\Modules\Commerce\Storefront\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $withdrawals, private WithdrawalScheduleService $schedule, private ReferralService $referrals, private RewardWalletService $wallet) {}

    /**
     * The customer's withdrawals plus the schedule: whether the window is open and when it next opens.
     */
    public function index(WithdrawalRequest $r): JsonResponse
    {
        $c = $this->customer($r);
        $store = StoreOrderSetting::forWorkspace($c->workspace_id);
        $precision = $store->precision();
        $settings = $this->referrals->settings($c->workspace_id);
        $window = $this->schedule->currentWindow($settings);
        $next = $this->schedule->nextWindow($settings);
        $page = CashinRequest::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->latest('id')->paginate(20);

        return response()->json([
            'data' => [
                'currency' => $store->currency,
                'balance' => OrderMoney::decimal($this->wallet->balance($c->workspace_id, $c->id), $precision),
                'reserved' => OrderMoney::decimal((int) CashinRequest::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->whereIn('status', ['pending', 'approved', 'processing'])->sum('amount_minor'), $precision),
                'schedule' => [
                    'timezone' => WithdrawalScheduleService::TIMEZONE,
                    'frequency' => $settings->withdrawal_frequency ?: 'monthly',
                    'open' => $window !== null,
                    'closes_at' => $window ? $window['ends_at']->toIso8601String() : null,
                    'next_opens_at' => $next ? $next['starts_at']->toIso8601String() : null,
                    'next_closes_at' => $next ? $next['ends_at']->toIso8601String() : null,
                ],
                'min_amount' => OrderMoney::decimal(OrderMoney::minor($settings->min_cashin_balance ?? 0, $precision), $precision),
                'max_amount' => $settings->max_cashin_amount === null ? null : OrderMoney::decimal(OrderMoney::minor($settings->max_cashin_amount, $precision), $precision),
                'methods' => collect($this->withdrawals->methods($settings))->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label])->values(),
                'requests' => collect($page->items())->map(fn (CashinRequest $request): array => $this->payload($request, $precision))->values(),
            ],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(WithdrawalRequest $r): JsonResponse
    {
        $key = (string) $r->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'Idempotency-Key must be a UUID.');
        $c = $this->customer($r);
        $request = $this->withdrawals->request($c, $r->validated(), $key);

        return response()->json(['data' => $this->payload($request, StoreOrderSetting::forWorkspace($c->workspace_id)->precision())], $request->wasRecentlyCreated ? 201 : 200);
    }

    public function cancel(WithdrawalRequest $r, int $id): JsonResponse
    {
        $c = $this->customer($r);

        return response()->json(['data' => $this->payload($this->withdrawals->cancel($c, $id), StoreOrderSetting::forWorkspace($c->workspace_id)->precision())]);
    }

    private function customer(WithdrawalRequest $r): Customer
    {
        $c = $r->attributes->get('store_customer');
        abort_unless($this->withdrawals->enabled($c->workspace_id), 404);

        return $c;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CashinRequest $request, int $precision): array
    {
        return [
            'id' => $request->id,
            'amount' => OrderMoney::decimal($request->amount_minor, $precision),
            'currency' => $request->currency,
            'method' => $request->method,
            'method_label' => CashinRequest::METHODS[$request->method] ?? $request->method,
            'status' => $request->status,
            'note' => $request->admin_note,
            'payout_reference' => $request->status === 'paid' ? $request->payout_reference : null,
            'can_cancel' => $request->status === 'pending',
            'created_at' => $request->created_at?->toIso8601String(),
            'processed_at' => $request->processed_at?->toIso8601String(),
        ];
    }
}
