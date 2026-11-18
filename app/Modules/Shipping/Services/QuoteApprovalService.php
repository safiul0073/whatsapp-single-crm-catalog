<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingQuote;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteApprovalService
{
    public function __construct(private ShippingAuditService $audit) {}

    /**
     * Confirms the provider for a shipment. An expired or unverified quotation is never
     * a confirmed price, so only the workspace owner may push one through, and that is logged.
     */
    public function approve(Shipment $shipment, ShippingQuote $quote, User $user, bool $override = false): Shipment
    {
        if ($quote->shipping_shipment_id !== $shipment->id) {
            abort(404);
        }
        if ($quote->status === 'rejected') {
            throw ValidationException::withMessages(['quote' => __('A rejected quotation cannot be approved.')]);
        }
        if (in_array($shipment->status, ['delivered', 'closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['quote' => __('This shipment can no longer be re-approved.')]);
        }

        $confirmed = $quote->isConfirmedPrice();
        if (! $confirmed) {
            $isOwner = Workspace::query()->find($shipment->workspace_id)?->isOwner($user) ?? false;
            if (! $override || ! $isOwner) {
                throw ValidationException::withMessages(['quote' => $quote->isExpired()
                    ? __('This quotation has expired. Request a fresh price, or the owner can approve it as an override.')
                    : __('This quotation is not verified yet. Verify it first, or the owner can approve it as an override.')]);
            }
        }

        return DB::transaction(function () use ($shipment, $quote, $user, $confirmed): Shipment {
            $old = ['approved_cost' => $shipment->approved_cost, 'approved_quote_id' => $shipment->approved_quote_id];
            $shipment->quotes()->where('status', 'approved')->whereKeyNot($quote->id)->update(['status' => 'verified']);
            $quote->update(['status' => 'approved']);

            $shipment->update([
                'approved_quote_id' => $quote->id,
                'approved_cost' => $quote->total_store,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'shipping_agent_id' => $quote->shipping_agent_id,
                'shipping_carrier_account_id' => $quote->shipping_carrier_account_id,
                'carrier' => $quote->carrier,
                'service' => $quote->service,
                'service_code' => $quote->service_code,
                'status' => in_array($shipment->status, ['pending', 'quoted', 'approved'], true) ? 'approved' : $shipment->status,
            ]);

            $this->audit->record(
                $shipment->workspace_id,
                $confirmed ? 'quote.approved' : 'quote.approved_override',
                ($confirmed ? 'Provider approved: ' : 'Owner override — unconfirmed quotation approved: ').$quote->providerLabel().' at '.$quote->total_store.' '.$shipment->currency,
                $shipment,
                $old,
                ['approved_cost' => $quote->total_store, 'approved_quote_id' => $quote->id],
                $user->id,
            );

            return $shipment->refresh();
        });
    }
}
