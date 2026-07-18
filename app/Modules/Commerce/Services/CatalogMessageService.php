<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogMessageService
{
    public function __construct(protected ChannelManager $channels) {}

    public function send(Conversation $conversation): Message
    {
        $catalog = $this->catalogFor($conversation);

        return $this->persistAndSend($conversation, ['type' => 'catalog_message'], 'Product catalog', ['catalog_id' => $catalog->meta_catalog_id]);
    }

    public function sendProduct(Conversation $conversation, int $variantId, ?string $body = null): Message
    {
        $catalog = $this->catalogFor($conversation);
        $variant = ProductVariant::query()->with('product')->where('workspace_id', $conversation->workspace_id)->whereKey($variantId)->whereIn('status', ['active', 'out_of_stock'])->whereHas('product', fn ($query) => $query->where('status', 'active'))->firstOrFail();

        return $this->persistAndSend($conversation, ['type' => 'product', 'catalog_id' => $catalog->meta_catalog_id, 'product_retailer_id' => $variant->meta_retailer_id, 'body' => $body ?: $variant->product->name], 'Product: '.$variant->product->name, ['catalog_id' => $catalog->meta_catalog_id, 'variant_id' => $variant->id]);
    }

    public function sendProductList(Conversation $conversation, array $variantIds, ?string $header = null, ?string $body = null): Message
    {
        $catalog = $this->catalogFor($conversation);
        $variants = ProductVariant::query()->with('product')->where('workspace_id', $conversation->workspace_id)->whereIn('id', $variantIds)->whereIn('status', ['active', 'out_of_stock'])->whereHas('product', fn ($query) => $query->where('status', 'active'))->get();
        if ($variants->count() !== count(array_unique($variantIds))) {
            throw ValidationException::withMessages(['variant_ids' => 'One or more selected products are unavailable.']);
        }
        $sections = $variants->groupBy(fn (ProductVariant $variant): string => $variant->product->category?->name ?? 'Products')->map(fn ($items, string $title): array => ['title' => Str::limit($title, 24, ''), 'product_items' => $items->map(fn (ProductVariant $variant): array => ['product_retailer_id' => $variant->meta_retailer_id])->values()->all()])->values()->all();

        return $this->persistAndSend($conversation, ['type' => 'product_list', 'catalog_id' => $catalog->meta_catalog_id, 'header' => $header ?: 'Selected products', 'body' => $body ?: 'Choose products to add to your cart.', 'sections' => $sections], 'Product selection', ['catalog_id' => $catalog->meta_catalog_id, 'variant_ids' => $variants->pluck('id')->all()]);
    }

    public function sendVideo(Conversation $conversation, int $productMediaId, ?string $caption = null): Message
    {
        $this->assertConversation($conversation);
        $gallery = ProductMedia::query()->with(['media', 'product'])->where('workspace_id', $conversation->workspace_id)->whereKey($productMediaId)->where('media_type', 'video')->firstOrFail();

        return $this->persistAndSend($conversation, ['type' => 'video', 'url' => $gallery->media->url, 'caption' => $caption ?: $gallery->product->name], 'Product video: '.$gallery->product->name, ['product_media_id' => $gallery->id]);
    }

    protected function catalogFor(Conversation $conversation): Catalog
    {
        $this->assertConversation($conversation);
        $catalog = Catalog::query()->where('workspace_id', $conversation->workspace_id)->where('channel_account_id', $conversation->channel_account_id)->where('is_active', true)->first();
        if (! $catalog || blank($catalog->meta_catalog_id)) {
            throw ValidationException::withMessages(['catalog' => 'Connect a Meta catalog to this WhatsApp channel first.']);
        }

        return $catalog;
    }

    protected function assertConversation(Conversation $conversation): void
    {
        if ($conversation->provider !== 'whatsapp' || ! $conversation->channelAccount || ! $conversation->contact?->phone) {
            throw ValidationException::withMessages(['catalog' => 'A connected WhatsApp conversation is required.']);
        }
        if (! $conversation->session_expires_at || $conversation->session_expires_at->isPast()) {
            throw ValidationException::withMessages(['catalog' => 'The 24-hour WhatsApp service window has expired. Send an approved template, then wait for the buyer to reply before sending interactive products.']);
        }
    }

    protected function persistAndSend(Conversation $conversation, array $payload, string $body, array $metadata = []): Message
    {
        $idempotencyKey = hash('sha256', $conversation->id.'|'.json_encode($payload, JSON_THROW_ON_ERROR).'|'.now()->format('Y-m-d-H-i'));
        $attempt = CommerceMessageAttempt::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            ['workspace_id' => $conversation->workspace_id, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'processing', 'request_payload' => $payload]
        );
        if (! $attempt->wasRecentlyCreated && $attempt->message_id) {
            return $attempt->message;
        }

        $message = Message::query()->create([
            'workspace_id' => $conversation->workspace_id,
            'channel_account_id' => $conversation->channel_account_id,
            'provider' => 'whatsapp',
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'body' => $body,
            'payload' => $metadata + ['request' => $payload, 'idempotency_key' => $idempotencyKey],
            'status' => MessageStatus::Queued->value,
        ]);
        $attempt->update(['message_id' => $message->id]);

        $result = $this->channels->sendMessage($conversation->channelAccount, ['to' => $conversation->contact->phone], $payload);
        $message->update([
            'payload' => ($message->payload ?? []) + ['response' => $result['response'] ?? null],
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $result['provider_message_id'] ?? null,
        ]);
        if (! ($result['ok'] ?? false)) {
            $attempt->update(['status' => 'failed', 'last_error' => $result['error'] ?? 'WhatsApp commerce message failed to send.']);
            throw ValidationException::withMessages(['catalog' => $result['error'] ?? 'WhatsApp commerce message failed to send.']);
        }

        $attempt->update(['status' => 'completed', 'last_error' => null]);

        return $message->fresh();
    }
}
