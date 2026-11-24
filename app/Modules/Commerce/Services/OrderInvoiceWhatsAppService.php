<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\SendOrderTemplateFallback;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use RuntimeException;

class OrderInvoiceWhatsAppService
{
    public const SENT = 'sent';

    public const SKIPPED_DISABLED = 'whatsapp_disabled';

    public const SKIPPED_CONTACT = 'contact_not_reachable';

    public const SKIPPED_NO_CHANNEL = 'no_whatsapp_channel';

    public const SKIPPED_WINDOW = 'outside_24h_window';

    public const SKIPPED_ALREADY_SENT = 'already_sent';

    public const FAILED = 'failed';

    public function __construct(protected ChannelManager $channels, protected OrderWhatsAppTemplateFallbackService $fallback) {}

    /**
     * Sends the invoice PDF to the customer. When WhatsApp blocks it because the 24-hour window is
     * closed, OrderWhatsAppTemplateFallbackService resends the invoice link as the approved utility template.
     *
     * Automatic sends happen once per order; a manual send always goes out.
     *
     * @return array{status: string, message: string}
     *
     * @throws RuntimeException when WhatsApp rejects the message (so the queue can retry)
     */
    public function send(Order $order, bool $manual = false): array
    {
        $order->ensureInvoice();
        if (! $manual && $order->invoice_sent_at) {
            return $this->result(self::SKIPPED_ALREADY_SENT);
        }

        $settings = StoreOrderSetting::forWorkspace($order->workspace_id);
        $contact = $order->contact;
        if (! $manual && ! $settings->whatsapp_notifications) {
            return $this->result(self::SKIPPED_DISABLED);
        }
        if (! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return $this->result(self::SKIPPED_CONTACT);
        }
        $phone = $order->customer_snapshot['phone'] ?? $contact->phone;
        if ($phone !== $contact->phone) {
            return $this->result(self::SKIPPED_CONTACT);
        }

        $deliver = $this->deliver(
            workspaceId: $order->workspace_id,
            contact: $contact,
            phone: $phone,
            channelAccountId: $order->channel_account_id ?? $settings->whatsapp_channel_id,
            caption: 'Invoice '.$order->invoice_number.' for order '.$order->number,
            url: $order->invoiceUrl(),
            downloadUrl: $order->invoiceUrl(download: true),
            filename: $order->invoice_number.'.pdf',
            key: $manual ? 'order-invoice:'.$order->id.':'.now()->format('YmdHisu') : 'order-invoice:'.$order->id,
            reference: ['order_invoice_id' => $order->id],
        );
        if ($deliver['status'] === self::SENT) {
            $order->forceFill(['invoice_sent_at' => now()])->save();
        }
        if ($deliver['status'] === self::FAILED && ($deliver['attempt_status'] ?? null) === 'failed' && ! $manual) {
            throw new RuntimeException($deliver['detail'] !== '' ? $deliver['detail'] : 'Invoice message failed.');
        }

        return $this->result($deliver['status'], $deliver['detail']);
    }

    /**
     * Sends a custom invoice to the customer it was made for. Always a manual send.
     *
     * @return array{status: string, message: string}
     */
    public function sendCustom(CustomInvoice $invoice): array
    {
        $invoice->loadMissing('customer.contact');
        $contact = $invoice->customer?->contact;
        if (! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return $this->result(self::SKIPPED_CONTACT);
        }

        $settings = StoreOrderSetting::forWorkspace($invoice->workspace_id);
        $deliver = $this->deliver(
            workspaceId: $invoice->workspace_id,
            contact: $contact,
            phone: (string) $contact->phone,
            channelAccountId: $settings->whatsapp_channel_id,
            caption: 'Invoice '.$invoice->number,
            url: $invoice->publicUrl(),
            downloadUrl: $invoice->publicUrl(download: true),
            filename: $invoice->number.'.pdf',
            key: 'custom-invoice:'.$invoice->id.':'.now()->format('YmdHisu'),
            reference: ['custom_invoice_id' => $invoice->id],
        );
        if ($deliver['status'] === self::SENT) {
            $invoice->forceFill(['sent_at' => now()])->save();
        }

        return $this->result($deliver['status'], $deliver['detail']);
    }

    /**
     * Sends the order's packing slip PDF to one person (a packer, courier or the owner). It goes out free-form first;
     * when the recipient's 24-hour window is closed it is resent as the store's approved utility template.
     *
     * @return array{status: string, message: string}
     */
    public function sendPackingSlip(Order $order, Contact $contact): array
    {
        if ($contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return ['status' => self::SKIPPED_CONTACT, 'message' => 'This number cannot receive WhatsApp messages (blocked or opted out).'];
        }

        $settings = StoreOrderSetting::forWorkspace($order->workspace_id);
        $deliver = $this->deliver(
            workspaceId: $order->workspace_id,
            contact: $contact,
            phone: (string) $contact->phone,
            channelAccountId: $settings->whatsapp_channel_id ?? $order->channel_account_id,
            caption: 'Packing slip for order '.$order->number,
            url: $order->packingSlipUrl(),
            downloadUrl: $order->packingSlipUrl(download: true),
            filename: $order->number.'-packing-slip.pdf',
            key: 'packing-slip:'.$order->id.':'.$contact->id.':'.now()->format('YmdHisu'),
            reference: ['packing_slip_order_id' => $order->id],
        );

        $messages = [
            self::SENT => 'Packing slip sent on WhatsApp.',
            self::SKIPPED_NO_CHANNEL => 'No connected WhatsApp channel for this store.',
            self::SKIPPED_WINDOW => 'WhatsApp blocked it because this number has not messaged in the last 24 hours and no approved utility template is selected in Order settings.',
            self::SKIPPED_ALREADY_SENT => 'The packing slip was already sent.',
            self::FAILED => 'WhatsApp could not send the packing slip.'.($deliver['detail'] !== '' ? ' '.$deliver['detail'] : ''),
        ];

        return ['status' => $deliver['status'], 'message' => $messages[$deliver['status']] ?? $deliver['status']];
    }

    /**
     * Finds the WhatsApp channel (opening the conversation if the customer never chatted), then sends the PDF free-form first.
     *
     * @param  array<string, int>  $reference
     * @return array{status: string, detail: string, attempt_status?: string}
     */
    private function deliver(int $workspaceId, Contact $contact, string $phone, ?int $channelAccountId, string $caption, string $url, string $downloadUrl, string $filename, string $key, array $reference): array
    {
        $account = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->where('status', 'connected')
            ->find($channelAccountId);
        if (! $account) {
            return ['status' => self::SKIPPED_NO_CHANNEL, 'detail' => ''];
        }

        $conversation = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $account->id)
            ->latest('id')
            ->first();

        $body = $caption.'. View or download: '.$url;
        $payload = ['type' => 'document', 'url' => $downloadUrl, 'filename' => $filename, 'caption' => $caption];

        $conversation ??= Conversation::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'provider' => $account->provider, 'contact_id' => $contact->id, 'channel_account_id' => $account->id],
            ['provider_conversation_id' => ltrim($phone, '+'), 'status' => ConversationStatus::Open->value, 'labels' => []],
        );

        $attempt = CommerceMessageAttempt::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', $key)],
            ['workspace_id' => $workspaceId, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'pending', 'request_payload' => $payload],
        );
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'], true)) {
            return ['status' => self::SKIPPED_ALREADY_SENT, 'detail' => ''];
        }

        $message = $attempt->message ?? Message::query()->create([
            'workspace_id' => $workspaceId,
            'channel_account_id' => $account->id,
            'provider' => 'whatsapp',
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $payload['type'],
            'body' => $body,
            'payload' => $reference + ['request' => $payload],
            'status' => 'queued',
        ]);
        $attempt->update(['message_id' => $message->id, 'status' => 'processing']);

        $result = $this->channels->sendMessage($account, ['to' => $phone], $payload);
        $ok = (bool) ($result['ok'] ?? false);
        $message->update([
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $result['provider_message_id'] ?? null,
            'status' => $ok ? 'sent' : 'failed',
        ]);
        if (! $ok && $this->fallback->blockedByWindow($result)) {
            if ($this->fallback->templatePayload($workspaceId, $account, $body) === null) {
                $attempt->update(['status' => 'failed', 'last_error' => $result['error'] ?? null]);

                return ['status' => self::SKIPPED_WINDOW, 'detail' => ''];
            }
            $attempt->update(['status' => 'completed', 'last_error' => $result['error'] ?? null]);
            SendOrderTemplateFallback::dispatch($message->id);

            return ['status' => self::SENT, 'detail' => ''];
        }
        $attempt->update([
            'status' => $ok ? 'completed' : (($result['error_code'] ?? '') === 'connection_error' ? 'uncertain' : 'failed'),
            'last_error' => $result['error'] ?? null,
        ]);

        return $ok
            ? ['status' => self::SENT, 'detail' => '']
            : ['status' => self::FAILED, 'detail' => (string) ($result['error'] ?? ''), 'attempt_status' => $attempt->status];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function result(string $status, string $detail = ''): array
    {
        $messages = [
            self::SENT => 'Invoice sent on WhatsApp.',
            self::SKIPPED_DISABLED => 'WhatsApp order notifications are turned off for this store.',
            self::SKIPPED_CONTACT => 'This customer cannot receive WhatsApp messages (blocked, opted out, or phone mismatch).',
            self::SKIPPED_NO_CHANNEL => 'No connected WhatsApp channel for this store.',
            self::SKIPPED_WINDOW => 'WhatsApp blocked the invoice because the customer has not messaged in the last 24 hours. Click "Create ready-made template" in Order settings so blocked invoices are resent as an approved template, or copy the invoice link and share it instead.',
            self::SKIPPED_ALREADY_SENT => 'The invoice was already sent.',
            self::FAILED => 'WhatsApp could not send the invoice.'.($detail !== '' ? ' '.$detail : ''),
        ];

        return ['status' => $status, 'message' => $messages[$status] ?? $status];
    }
}
