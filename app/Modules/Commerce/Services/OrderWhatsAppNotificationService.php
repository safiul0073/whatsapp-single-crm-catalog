<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\SendOrderTemplateFallback;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Support\Facades\Log;

class OrderWhatsAppNotificationService
{
    public function __construct(protected ChannelManager $channels, protected OrderWhatsAppTemplateFallbackService $fallback) {}

    /**
     * Sends the order update to the customer on WhatsApp. Opens the conversation when the customer has never
     * chatted. The free-form text always goes first; when WhatsApp blocks it because the 24-hour window is closed,
     * OrderWhatsAppTemplateFallbackService resends it as the approved utility template.
     *
     * @return bool true when sent or intentionally skipped; false when the store setup is missing and a later retry may succeed
     */
    public function send(Order $order, OrderEvent $event): bool
    {
        $settings = StoreOrderSetting::forWorkspace($order->workspace_id);
        if (! $settings->whatsapp_notifications) {
            return true;
        }
        $contact = $order->contact;
        if (! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return $this->skipped($order, 'customer cannot receive WhatsApp (no contact, blocked or opted out)', true);
        }
        $phone = $order->customer_snapshot['phone'] ?? $contact->phone;
        if ($phone !== $contact->phone) {
            return $this->skipped($order, 'order phone differs from the contact phone', true);
        }
        $account = ChannelAccount::query()->where('workspace_id', $order->workspace_id)->where('provider', 'whatsapp')->where('status', 'connected')->find($order->channel_account_id ?? $settings->whatsapp_channel_id);
        if (! $account) {
            return $this->skipped($order, 'no connected WhatsApp channel selected in order settings', false);
        }
        $conversation = Conversation::query()->where('workspace_id', $order->workspace_id)->where('contact_id', $contact->id)->where('channel_account_id', $account->id)->latest('id')->first();
        $body = $event->label.' · Order '.$order->number.' · '.$order->currency.' '.($order->total ?? $order->subtotal).' · Tracking '.$order->tracking_code;
        if ($order->shipping_quote_required) {
            $body .= ' · Shipping quote required';
        }
        $payload = ['type' => 'text', 'body' => $body];
        $conversation ??= Conversation::query()->firstOrCreate(
            ['workspace_id' => $order->workspace_id, 'provider' => $account->provider, 'contact_id' => $contact->id, 'channel_account_id' => $account->id],
            ['provider_conversation_id' => ltrim((string) $phone, '+'), 'status' => ConversationStatus::Open->value, 'labels' => []],
        );
        $attempt = CommerceMessageAttempt::query()->firstOrCreate(['idempotency_key' => hash('sha256', 'order-event:'.$event->id)], ['workspace_id' => $order->workspace_id, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'pending', 'request_payload' => $payload]);
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'])) {
            return true;
        }
        $message = $attempt->message ?? Message::query()->create(['workspace_id' => $order->workspace_id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'outbound', 'type' => $payload['type'], 'body' => $body, 'payload' => ['order_event_id' => $event->id, 'request' => $payload], 'status' => 'queued']);
        $attempt->update(['message_id' => $message->id, 'status' => 'processing']);
        $result = $this->channels->sendMessage($account, ['to' => $phone], $payload);
        $message->update(['provider_message_id' => $result['provider_message_id'] ?? null, 'whatsapp_message_id' => $result['provider_message_id'] ?? null, 'status' => ($result['ok'] ?? false) ? 'sent' : 'failed']);
        if (! ($result['ok'] ?? false) && $this->fallback->blockedByWindow($result)) {
            if ($this->fallback->templatePayload($order->workspace_id, $account, $body) === null) {
                $attempt->update(['status' => 'failed', 'last_error' => $result['error'] ?? null]);

                return $this->skipped($order, 'WhatsApp blocked the free message (24-hour window closed) and no approved utility template is selected', false);
            }
            $attempt->update(['status' => 'completed', 'last_error' => $result['error'] ?? null]);
            SendOrderTemplateFallback::dispatch($message->id);

            return true;
        }
        $attempt->update(['status' => ($result['ok'] ?? false) ? 'completed' : (($result['error_code'] ?? '') === 'connection_error' ? 'uncertain' : 'failed'), 'last_error' => $result['error'] ?? null]);
        if (! ($result['ok'] ?? false) && $attempt->status === 'failed') {
            throw new \RuntimeException($result['error'] ?? 'Order notification failed.');
        }

        return true;
    }

    protected function skipped(Order $order, string $reason, bool $isFinal): bool
    {
        Log::warning('Order WhatsApp update skipped: '.$reason.'.', ['order' => $order->number, 'workspace' => $order->workspace_id]);

        return $isFinal;
    }
}
