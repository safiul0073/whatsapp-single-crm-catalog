<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'commerce_orders';

    protected $fillable = ['workspace_id', 'contact_id', 'conversation_id', 'channel_account_id', 'catalog_id', 'number', 'provider_message_id', 'provider_catalog_id', 'status', 'currency', 'subtotal', 'shipping_amount', 'total', 'shipping_address', 'delivery_method', 'delivery_notes', 'duties_disclosure', 'payment_url', 'tracking_number', 'tracking_url', 'inventory_adjusted_at', 'inventory_restored_at', 'paid_at', 'shipped_at', 'issues', 'provider_payload'];

    protected function casts(): array
    {
        return ['shipping_address' => 'array', 'issues' => 'array', 'provider_payload' => 'array', 'subtotal' => 'decimal:2', 'shipping_amount' => 'decimal:2', 'total' => 'decimal:2', 'inventory_adjusted_at' => 'datetime', 'inventory_restored_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime'];
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
}
