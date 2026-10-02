<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;

class OrderWhatsAppNotificationService
{
    public function __construct(protected ChannelManager $channels) {}

    public function send(Order $order, OrderEvent $event): void
    {
        $settings = StoreOrderSetting::forWorkspace($order->workspace_id);
        $contact = $order->contact;
        if (! $settings->whatsapp_notifications || ! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return;
        }
        $phone = $order->customer_snapshot['phone'] ?? $contact->phone;
        if ($phone !== $contact->phone) {
            return;
        }
        $account = ChannelAccount::query()->where('workspace_id', $order->workspace_id)->where('provider', 'whatsapp')->where('status', 'connected')->find($order->channel_account_id ?? $settings->whatsapp_channel_id);
        if (! $account) {
            return;
        }
        $conversation = Conversation::query()->where('workspace_id', $order->workspace_id)->where('contact_id', $contact->id)->where('channel_account_id', $account->id)->latest('id')->first();
        if (! $conversation) {
            return;
        }
        $body = $event->label.' · Order '.$order->number.' · '.$order->currency.' '.($order->total ?? $order->subtotal).' · Tracking '.$order->tracking_code;
        if ($order->shipping_quote_required) {
            $body .= ' · Shipping quote required';
        }
        $payload = ['type' => 'text', 'body' => $body];
        if (! $conversation->session_expires_at || $conversation->session_expires_at->isPast()) {
            if ($contact->opt_in_status !== ContactOptInStatus::Subscribed) {
                return;
            }
            $template = MessageTemplate::query()->where('workspace_id', $order->workspace_id)->where('provider', 'whatsapp')->where('category', 'utility')->find($settings->whatsapp_template_id);
            if (! $template || ! $template->approvedForWaba($account->provider_account_id)) {
                return;
            }
            $components = collect($template->components ?? []);
            $templateBody = $components->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BODY');
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $templateBody['text'] ?? '', $variables);
            if (array_values(array_unique($variables[1])) !== ['1'] || $components->contains(fn ($component) => in_array(strtoupper($component['type'] ?? ''), ['HEADER', 'BUTTONS']))) {
                return;
            }
            $payload = ['type' => 'template', 'template_name' => $template->name, 'language' => $template->language, 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $body]]]]];
        }
        $attempt = CommerceMessageAttempt::query()->firstOrCreate(['idempotency_key' => hash('sha256', 'order-event:'.$event->id)], ['workspace_id' => $order->workspace_id, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'pending', 'request_payload' => $payload]);
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'])) {
            return;
        }
        $message = $attempt->message ?? Message::query()->create(['workspace_id' => $order->workspace_id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'outbound', 'type' => $payload['type'], 'body' => $body, 'payload' => ['order_event_id' => $event->id, 'request' => $payload], 'status' => 'queued']);
        $attempt->update(['message_id' => $message->id, 'status' => 'processing']);
        $result = $this->channels->sendMessage($account, ['to' => $phone], $payload);
        $message->update(['provider_message_id' => $result['provider_message_id'] ?? null, 'whatsapp_message_id' => $result['provider_message_id'] ?? null, 'status' => ($result['ok'] ?? false) ? 'sent' : 'failed']);
        $attempt->update(['status' => ($result['ok'] ?? false) ? 'completed' : (($result['error_code'] ?? '') === 'connection_error' ? 'uncertain' : 'failed'), 'last_error' => $result['error'] ?? null]);
        if (! ($result['ok'] ?? false) && $attempt->status === 'failed') {
            throw new \RuntimeException($result['error'] ?? 'Order notification failed.');
        }
    }
}
