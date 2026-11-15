<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Http\Requests\WithdrawalAdminRequest;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\WithdrawalScheduleService;
use App\Modules\Commerce\Storefront\Services\WithdrawalService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WithdrawalAdminController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'processing', 'paid', 'rejected', 'cancelled'];

    public function __construct(private WorkspaceResolver $workspaces, private ReferralService $referrals, private WithdrawalService $withdrawals, private WithdrawalScheduleService $schedule) {}

    private function workspace(Request $r): int
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::typeHas($w, 'referral'), 404);

        return $w;
    }

    public function index(Request $r): View
    {
        $w = $this->workspace($r);
        $store = StoreOrderSetting::forWorkspace($w);
        $settings = $this->referrals->settings($w);
        $report = CashinRequest::query()->where('workspace_id', $w)->selectRaw('status, method, COUNT(*) as requests, SUM(amount_minor) as amount_minor')->groupBy('status', 'method')->get();

        return view('commerce::user.withdrawals', [
            'settings' => $settings,
            'currency' => $store->currency,
            'precision' => $store->precision(),
            'timezone' => WithdrawalScheduleService::TIMEZONE,
            'methods' => CashinRequest::METHODS,
            'activeMethods' => array_keys($this->withdrawals->methods($settings)),
            'window' => $this->schedule->currentWindow($settings),
            'upcoming' => $this->schedule->upcoming($settings, 6),
            'statuses' => self::STATUSES,
            'transitions' => WithdrawalService::TRANSITIONS,
            'canManage' => $r->user()->can('commerce.referrals.manage'),
            'totals' => collect(self::STATUSES)->mapWithKeys(fn (string $status): array => [$status => ['requests' => (int) $report->where('status', $status)->sum('requests'), 'amount_minor' => (int) $report->where('status', $status)->sum('amount_minor')]]),
            'paidByMethod' => $report->where('status', 'paid')->mapWithKeys(fn ($row): array => [$row->method => (int) $row->amount_minor]),
            'requests' => $this->filtered($r, $w)->with('customer.contact')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function saveSchedule(WithdrawalAdminRequest $r): RedirectResponse
    {
        $settings = $this->referrals->settings($this->workspace($r));
        $settings->fill($r->validated())->save();

        return $this->back('schedule', __('Withdrawal schedule saved. Requests already submitted are not affected.'));
    }

    public function update(WithdrawalAdminRequest $r, int $id): RedirectResponse
    {
        $this->withdrawals->decide($this->workspace($r), $id, $r->validated('status'), $r->validated('admin_note'), $r->validated('payout_reference'), $r->user()->id);

        return $this->back('requests', __('Withdrawal request updated.'));
    }

    public function export(Request $r): StreamedResponse
    {
        $w = $this->workspace($r);
        $precision = StoreOrderSetting::forWorkspace($w)->precision();
        $rows = $this->filtered($r, $w)->with('customer.contact')->latest('id')->limit(10000)->get();

        return response()->streamDownload(function () use ($rows, $precision): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Customer', 'Phone', 'Amount', 'Currency', 'Method', 'Status', 'Payout reference', 'Note', 'Requested', 'Processed']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->customer?->contact?->name, $row->customer?->contact?->phone, OrderMoney::decimal($row->amount_minor, $precision), $row->currency, CashinRequest::METHODS[$row->method] ?? $row->method, $row->status, $row->payout_reference, $row->admin_note, $row->created_at?->timezone(WithdrawalScheduleService::TIMEZONE)->toDateTimeString(), $row->processed_at?->timezone(WithdrawalScheduleService::TIMEZONE)->toDateTimeString()]);
            }
            fclose($out);
        }, 'withdrawals-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $r, int $w): Builder
    {
        return CashinRequest::query()->where('workspace_id', $w)
            ->when(in_array($r->query('status'), self::STATUSES, true), fn (Builder $q) => $q->where('status', $r->query('status')))
            ->when(array_key_exists((string) $r->query('method'), CashinRequest::METHODS), fn (Builder $q) => $q->where('method', $r->query('method')))
            ->when($r->date('from'), fn (Builder $q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($r->date('to'), fn (Builder $q, $d) => $q->where('created_at', '<=', $d->endOfDay()));
    }

    private function back(string $tab, string $message): RedirectResponse
    {
        return redirect()->to(route('user.commerce.withdrawals.index').'?tab='.$tab)->with('success', $message);
    }
}
