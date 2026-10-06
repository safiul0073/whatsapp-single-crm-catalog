<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $table = 'commerce_orders';

    protected $fillable = ['shipping_method_id', 'customer_snapshot', 'source', 'submission_reference', 'payload_hash', 'customer_reference', 'tracking_code', 'payment_state', 'payment_instructions', 'payment_evidence', 'discount_amount', 'adjustments', 'shipping_quote_required', 'packed_at', 'delivered_at', 'workspace_id', 'contact_id', 'conversation_id', 'channel_account_id', 'catalog_id', 'number', 'provider_message_id', 'provider_catalog_id', 'status', 'currency', 'subtotal', 'shipping_amount', 'total', 'shipping_address', 'delivery_method', 'delivery_notes', 'duties_disclosure', 'payment_url', 'tracking_number', 'tracking_url', 'inventory_adjusted_at', 'inventory_restored_at', 'paid_at', 'shipped_at', 'issues', 'provider_payload'];

    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'packed_at' => 'datetime', 'delivered_at' => 'datetime', 'payment_evidence' => 'array', 'adjustments' => 'array', 'shipping_quote_required' => 'boolean', 'discount_amount' => 'decimal:4', 'shipping_address' => 'array', 'issues' => 'array', 'provider_payload' => 'array', 'subtotal' => 'decimal:4', 'shipping_amount' => 'decimal:4', 'total' => 'decimal:4', 'inventory_adjusted_at' => 'datetime', 'inventory_restored_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime'];
    }

    public function trackingTimeline(): array
    {
        if ($this->events()->exists()) {
            return $this->events->map(fn ($event) => ['label' => $event->label, 'date' => $event->occurred_at->toIso8601String(), 'state' => 'complete'])->all();
        }

        $current = match ($this->status) {
            'paid' => 1,
            'processing' => 2,
            'shipped' => 3,
            'completed' => 4,
            default => 0,
        };

        $steps = [
            ['label' => 'Order placed', 'date' => $this->created_at?->toIso8601String()],
            ['label' => 'Payment confirmed', 'date' => $this->paid_at?->toIso8601String()],
            ['label' => 'Preparing your order', 'date' => null],
            ['label' => 'Shipped / in transit', 'date' => $this->shipped_at?->toIso8601String()],
            ['label' => 'Delivered', 'date' => null],
        ];

        foreach ($steps as $index => &$step) {
            if ($this->status === 'cancelled') {
                $step['state'] = $index === 0 || filled($step['date']) ? 'complete' : 'stopped';
            } else {
                $step['state'] = $index < $current || $this->status === 'completed'
                    ? 'complete' : ($index === $current ? 'current' : 'upcoming');
            }
        }

        return $steps;
    }

    public function groups(): HasMany
    {
        return $this->hasMany(OrderGroup::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(OrderBox::class);
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
