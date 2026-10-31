<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use RuntimeException;

class OrderInvoiceWhatsAppService
{
    public const SENT = 'sent';

    public const SKIPPED_DISABLED = 'whatsapp_disabled';

    public const SKIPPED_CONTACT = 'contact_not_reachable';

    public const SKIPPED_NO_CHANNEL = 'no_whatsapp_channel';

    public const SKIPPED_NO_CONVERSATION = 'no_conversation';

    public const SKIPPED_WINDOW = 'outside_24h_window';

    public const SKIPPED_ALREADY_SENT = 'already_sent';

    public const FAILED = 'failed';

    public function __construct(protected ChannelManager $channels) {}

    /**
     * Sends the invoice PDF (or, outside WhatsApp's 24-hour window, an approved
     * template carrying the invoice link) to the customer.
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

        $account = ChannelAccount::query()
            ->where('workspace_id', $order->workspace_id)
            ->where('provider', 'whatsapp')
            ->where('status', 'connected')
            ->find($order->channel_account_id ?? $settings->whatsapp_channel_id);
        if (! $account) {
            return $this->result(self::SKIPPED_NO_CHANNEL);
        }

        $conversation = Conversation::query()
            ->where('workspace_id', $order->workspace_id)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $account->id)
            ->latest('id')
            ->first();
        if (! $conversation) {
            return $this->result(self::SKIPPED_NO_CONVERSATION);
        }

        $url = $order->invoiceUrl();
        $caption = 'Invoice '.$order->invoice_number.' for order '.$order->number.'. View or download: '.$url;
        $payload = [
            'type' => 'document',
            'url' => $order->invoiceUrl(download: true),
            'filename' => $order->invoice_number.'.pdf',
            'caption' => 'Invoice '.$order->invoice_number.' for order '.$order->number,
        ];

        if (! $conversation->session_expires_at || $conversation->session_expires_at->isPast()) {
            $payload = $this->templatePayload($order, $settings, $account, $contact->opt_in_status, $caption);
            if ($payload === null) {
                return $this->result(self::SKIPPED_WINDOW);
            }
        }

        $key = $manual
            ? 'order-invoice:'.$order->id.':'.now()->format('YmdHisu')
            : 'order-invoice:'.$order->id;
        $attempt = CommerceMessageAttempt::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', $key)],
            ['workspace_id' => $order->workspace_id, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'pending', 'request_payload' => $payload],
        );
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'], true)) {
            return $this->result(self::SKIPPED_ALREADY_SENT);
        }

        $message = $attempt->message ?? Message::query()->create([
            'workspace_id' => $order->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => 'whatsapp',
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $payload['type'],
            'body' => $caption,
            'payload' => ['order_invoice_id' => $order->id, 'request' => $payload],
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
        $attempt->update([
            'status' => $ok ? 'completed' : (($result['error_code'] ?? '') === 'connection_error' ? 'uncertain' : 'failed'),
            'last_error' => $result['error'] ?? null,
        ]);

        if ($ok) {
            $order->forceFill(['invoice_sent_at' => now()])->save();

            return $this->result(self::SENT);
        }
        if ($attempt->status === 'failed' && ! $manual) {
            throw new RuntimeException($result['error'] ?? 'Invoice message failed.');
        }

        return $this->result(self::FAILED, (string) ($result['error'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function templatePayload(Order $order, StoreOrderSetting $settings, ChannelAccount $account, mixed $optIn, string $body): ?array
    {
        if ($optIn !== ContactOptInStatus::Subscribed) {
            return null;
        }
        $template = MessageTemplate::query()
            ->where('workspace_id', $order->workspace_id)
            ->where('provider', 'whatsapp')
            ->where('category', 'utility')
            ->find($settings->whatsapp_template_id);
        if (! $template || ! $template->approvedForWaba($account->provider_account_id)) {
            return null;
        }
        $components = collect($template->components ?? []);
        $templateBody = $components->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $templateBody['text'] ?? '', $variables);
        if (array_values(array_unique($variables[1])) !== ['1'] || $components->contains(fn ($component) => in_array(strtoupper($component['type'] ?? ''), ['HEADER', 'BUTTONS']))) {
            return null;
        }

        return [
            'type' => 'template',
            'template_name' => $template->name,
            'language' => $template->language,
            'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $body]]]],
        ];
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
            self::SKIPPED_NO_CONVERSATION => 'There is no WhatsApp conversation with this customer yet.',
            self::SKIPPED_WINDOW => 'The customer has not messaged in the last 24 hours and no approved template is set up, so WhatsApp does not allow sending now. Copy the invoice link and share it instead.',
            self::SKIPPED_ALREADY_SENT => 'The invoice was already sent.',
            self::FAILED => 'WhatsApp could not send the invoice.'.($detail !== '' ? ' '.$detail : ''),
        ];

        return ['status' => $status, 'message' => $messages[$status] ?? $status];
    }
}
