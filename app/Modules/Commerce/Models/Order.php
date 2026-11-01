<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $table = 'commerce_orders';

    protected $fillable = ['fulfillment_type', 'shipping_method_id', 'customer_snapshot', 'source', 'submission_reference', 'payload_hash', 'customer_reference', 'tracking_code', 'payment_state', 'payment_instructions', 'payment_evidence', 'discount_amount', 'adjustments', 'shipping_quote_required', 'packed_at', 'delivered_at', 'workspace_id', 'contact_id', 'conversation_id', 'channel_account_id', 'catalog_id', 'number', 'provider_message_id', 'provider_catalog_id', 'status', 'currency', 'subtotal', 'shipping_amount', 'total', 'shipping_address', 'delivery_method', 'delivery_notes', 'duties_disclosure', 'payment_url', 'tracking_number', 'tracking_url', 'inventory_adjusted_at', 'inventory_restored_at', 'paid_at', 'shipped_at', 'issues', 'provider_payload', 'invoice_number', 'invoice_token', 'invoice_sent_at'];

    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'packed_at' => 'datetime', 'delivered_at' => 'datetime', 'payment_evidence' => 'array', 'adjustments' => 'array', 'shipping_quote_required' => 'boolean', 'discount_amount' => 'decimal:4', 'shipping_address' => 'array', 'issues' => 'array', 'provider_payload' => 'array', 'subtotal' => 'decimal:4', 'shipping_amount' => 'decimal:4', 'total' => 'decimal:4', 'inventory_adjusted_at' => 'datetime', 'inventory_restored_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'invoice_sent_at' => 'datetime'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('recorded_at')->orderBy('id');
    }

    /**
     * Gives the order a customer-facing invoice number and a private link token
     * the first time an invoice is needed.
     */
    public function ensureInvoice(): static
    {
        if ($this->invoice_number && $this->invoice_token) {
            return $this;
        }

        $this->forceFill([
            'invoice_number' => $this->invoice_number ?: 'INV-'.$this->number,
            'invoice_token' => $this->invoice_token ?: Str::random(40),
        ])->save();

        return $this;
    }

    public function regenerateInvoiceToken(): static
    {
        $this->forceFill(['invoice_token' => Str::random(40)])->save();

        return $this;
    }

    public function invoiceUrl(bool $download = false): string
    {
        $this->ensureInvoice();
        $url = rtrim((string) config('app.url'), '/').'/invoices/'.$this->invoice_token;

        return $download ? $url.'?download=1' : $url;
    }

    public function moneyPrecision(): int
    {
        return (new StoreOrderSetting(['currency' => $this->currency]))->precision();
    }

    public function paidAmount(): string
    {
        $minor = $this->payments->sum(fn (OrderPayment $payment): int => OrderMoney::minor($payment->amount, $this->moneyPrecision()));

        return OrderMoney::decimal($minor, $this->moneyPrecision());
    }

    public function balanceDue(): ?string
    {
        if ($this->total === null) {
            return null;
        }

        return OrderMoney::decimal(max(0, OrderMoney::minor($this->total, $this->moneyPrecision()) - OrderMoney::minor($this->paidAmount(), $this->moneyPrecision())), $this->moneyPrecision());
    }

    /**
     * Customer-facing progress steps. The shipped step carries courier details when available.
     *
     * @return list<array{key: string, label: string, date: ?string, state: string, shipment?: array{carrier: ?string, tracking_number: ?string, tracking_url: ?string, proof_image_url: ?string}}>
     */
    public function trackingTimeline(): array
    {
        if ($this->events()->exists()) {
            $steps = $this->events->map(fn ($event): array => ['key' => (string) $event->key, 'label' => $event->label, 'date' => $event->occurred_at->toIso8601String(), 'state' => 'complete'])->all();
        } else {
            $current = match ($this->status) {
                'paid' => 1,
                'processing' => 2,
                'shipped' => 3,
                'completed' => 4,
                default => 0,
            };

            $steps = [
                ['key' => 'placed', 'label' => 'Order placed', 'date' => $this->created_at?->toIso8601String()],
                ['key' => 'paid', 'label' => 'Payment confirmed', 'date' => $this->paid_at?->toIso8601String()],
                ['key' => 'processing', 'label' => 'Preparing your order', 'date' => null],
                ['key' => 'shipped', 'label' => 'Shipped / in transit', 'date' => $this->shipped_at?->toIso8601String()],
                ['key' => 'completed', 'label' => 'Delivered', 'date' => null],
            ];

            foreach ($steps as $index => &$step) {
                if ($this->status === 'cancelled') {
                    $step['state'] = $index === 0 || filled($step['date']) ? 'complete' : 'stopped';
                } else {
                    $step['state'] = $index < $current || $this->status === 'completed'
                        ? 'complete' : ($index === $current ? 'current' : 'upcoming');
                }
            }
            unset($step);
        }

        $shipment = $this->courierDetails();
        if ($shipment !== null) {
            $shippedIndex = null;
            foreach ($steps as $index => $step) {
                if ($step['key'] === 'shipped' && $step['state'] !== 'upcoming' && $step['state'] !== 'stopped') {
                    $shippedIndex = $index;
                }
            }
            if ($shippedIndex !== null) {
                $steps[$shippedIndex]['shipment'] = $shipment;
            }
        }

        return $steps;
    }

    /**
     * @return array{carrier: ?string, tracking_number: ?string, tracking_url: ?string, proof_image_url: ?string}|null
     */
    public function courierDetails(): ?array
    {
        $shipment = $this->shipment;
        $details = [
            'carrier' => $shipment?->carrier && $shipment->carrier !== 'Carrier' ? $shipment->carrier : null,
            'tracking_number' => $shipment?->tracking_number ?: $this->tracking_number,
            'tracking_url' => $shipment?->tracking_url ?: $this->tracking_url,
            'proof_image_url' => $shipment?->proofImageUrl(),
        ];

        return array_filter($details) === [] ? null : $details;
    }

    public function groups(): HasMany
    {
        return $this->hasMany(OrderGroup::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(OrderBox::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(OrderReservation::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(OrderShipment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function hasCompleteShippingAddress(): bool
    {
        return collect(['name', 'phone', 'line1', 'city', 'country'])
            ->every(fn (string $field): bool => filled($this->shipping_address[$field] ?? null));
    }
}
