<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\SendOrderTemplateFallback;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Order updates and invoices go out as free-form WhatsApp messages first (no charge). When WhatsApp blocks one
 * because the customer's 24-hour window is closed (error 131047), this resends it once as the store's approved
 * utility template.
 */
class OrderWhatsAppTemplateFallbackService
{
    /** Message payload keys that mark an order update or invoice message. */
    public const REFERENCES = ['order_event_id', 'order_invoice_id', 'custom_invoice_id', 'packing_slip_order_id'];

    public function __construct(protected ChannelManager $channels) {}

    /**
     * The store's selected utility template, filled with the message text, or null when none is approved for
     * this WhatsApp account or it does not have exactly one {{1}} body variable without header or buttons.
     *
     * @return array<string, mixed>|null
     */
    public function templatePayload(int $workspaceId, ChannelAccount $account, string $body): ?array
    {
        $settings = StoreOrderSetting::forWorkspace($workspaceId);
        $template = $settings->whatsapp_template_id ? MessageTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->where('category', 'utility')
            ->find($settings->whatsapp_template_id) : null;
        if (! $template || ! $template->approvedForWaba((string) $account->provider_account_id)) {
            return null;
        }
        $components = collect($template->components ?? []);
        $templateBody = $components->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $templateBody['text'] ?? '', $variables);
        if (array_values(array_unique($variables[1])) !== ['1'] || $components->contains(fn ($component) => in_array(strtoupper($component['type'] ?? ''), ['HEADER', 'BUTTONS'], true))) {
            return null;
        }

        return [
            'type' => 'template',
            'template_name' => $template->name,
            'language' => $template->language,
            'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $body]]]],
        ];
    }

    public const DRAFT_TEMPLATE_NAME = 'order_update';

    public const DRAFT_TEMPLATE_BODY = 'Here is an update on your order: {{1}}. Reply to this message if you need any help.';

    /**
     * Gives the store the ready-made order update utility template as a draft (once), so the owner can review it
     * and submit it to WhatsApp from Templates, and selects it in order settings when none is chosen yet.
     */
    public function ensureDraftTemplate(int $workspaceId): MessageTemplate
    {
        $template = MessageTemplate::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'provider' => 'whatsapp', 'name' => self::DRAFT_TEMPLATE_NAME, 'language' => 'en_US'],
            [
                'template_kind' => 'standard',
                'category' => 'utility',
                'status' => 'draft',
                'body' => self::DRAFT_TEMPLATE_BODY,
                'components' => [['type' => 'BODY', 'text' => self::DRAFT_TEMPLATE_BODY, 'example' => ['body_text' => [['Order shipped · Order ORD-1001 · BDT 1250 · Tracking TRK-AB12CD']]]]],
                'buttons' => [],
                'variables' => ['body' => ['1']],
            ],
        );
        $settings = StoreOrderSetting::forWorkspace($workspaceId);
        if (! $settings->whatsapp_template_id) {
            $settings->update(['whatsapp_template_id' => $template->id]);
        }

        return $template;
    }

    /**
     * Whether a send result or a status-webhook error says the 24-hour window is closed.
     *
     * @param  array<string, mixed>  $error  a send result (error_code / error_detail) or a webhook errors[] entry (code)
     */
    public function blockedByWindow(array $error): bool
    {
        $code = (string) ($error['error_code'] ?? $error['code'] ?? '');

        return $code === '131047'
            || (bool) data_get($error, 'error_detail.window')
            || ($code !== '' && app(MetaErrorTranslator::class)->translate(['code' => $code])['window']);
    }

    /**
     * Called for every WhatsApp status webhook: a free-form order message that failed because the window is
     * closed gets queued for the template resend.
     *
     * @param  array<string, mixed>  $status
     */
    public function handleStatus(ChannelAccount $account, array $status): void
    {
        $error = $status['errors'][0] ?? null;
        if (($status['status'] ?? null) !== 'failed' || ! is_array($error) || blank($status['id'] ?? null) || ! $this->blockedByWindow($error)) {
            return;
        }
        $message = Message::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('direction', 'outbound')
            ->where(fn ($query) => $query->where('provider_message_id', $status['id'])->orWhere('whatsapp_message_id', $status['id']))
            ->first();
        if ($message && $this->needsFallback($message)) {
            SendOrderTemplateFallback::dispatch($message->id);
        }
    }

    /**
     * Sends the blocked free-form message again as the approved utility template (once per message).
     *
     * @throws RuntimeException when WhatsApp rejects the template, so the queue retries
     */
    public function resend(int $messageId): void
    {
        $original = Message::query()->find($messageId);
        if (! $original || ! $this->needsFallback($original)) {
            return;
        }
        $reference = Arr::only($original->payload ?? [], self::REFERENCES);
        $context = ['message' => $original->id, 'workspace' => $original->workspace_id] + $reference;
        $account = ChannelAccount::query()->where('workspace_id', $original->workspace_id)->where('provider', 'whatsapp')->where('status', 'connected')->find($original->channel_account_id);
        $contact = Contact::query()->where('workspace_id', $original->workspace_id)->find($original->contact_id);
        if (! $account || ! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            Log::warning('Order WhatsApp fallback skipped: channel disconnected or customer cannot receive WhatsApp.', $context);

            return;
        }
        $payload = $this->templatePayload($original->workspace_id, $account, (string) $original->body);
        if ($payload === null) {
            Log::warning('Order WhatsApp fallback skipped: the customer\'s 24-hour window is closed and no approved utility template is selected.', $context);
            $this->notifyOwnerAboutInvoice($original, $reference);

            return;
        }

        $attempt = CommerceMessageAttempt::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', 'template-fallback:'.$original->id)],
            ['workspace_id' => $original->workspace_id, 'conversation_id' => $original->conversation_id, 'message_type' => 'template', 'status' => 'pending', 'request_payload' => $payload],
        );
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'], true)) {
            return;
        }
        $message = $attempt->message ?? Message::query()->create([
            'workspace_id' => $original->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => 'whatsapp',
            'conversation_id' => $original->conversation_id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => 'template',
            'body' => $original->body,
            'payload' => $reference + ['fallback_for' => $original->id, 'request' => $payload],
            'status' => 'queued',
        ]);
        $attempt->update(['message_id' => $message->id, 'status' => 'processing']);

        $result = $this->channels->sendMessage($account, ['to' => $contact->phone], $payload);
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
        if ($attempt->status === 'failed') {
            throw new RuntimeException($result['error'] ?? 'Order template fallback failed.');
        }
        $original->update(['payload' => ($original->payload ?? []) + ['fallback_message_id' => $message->id]]);
    }

    protected function needsFallback(Message $message): bool
    {
        $payload = $message->payload ?? [];

        return Arr::only($payload, self::REFERENCES) !== []
            && data_get($payload, 'request.type') !== 'template'
            && ! isset($payload['fallback_message_id'])
            && ! isset($payload['fallback_for']);
    }

    /**
     * @param  array<string, int>  $reference
     */
    protected function notifyOwnerAboutInvoice(Message $original, array $reference): void
    {
        $url = match (true) {
            isset($reference['order_invoice_id']) => route('user.commerce.orders.show', $reference['order_invoice_id']),
            isset($reference['custom_invoice_id']) => route('user.commerce.custom-invoices.edit', $reference['custom_invoice_id']),
            default => null,
        };
        $owner = $url ? Workspace::query()->find($original->workspace_id)?->owner : null;
        if ($owner) {
            app(SystemNotificationService::class)->send($owner, [
                'title' => 'Invoice not delivered on WhatsApp',
                'body' => 'The customer has not messaged in the last 24 hours, so WhatsApp blocked the invoice. Select an approved utility template in order settings so it can be sent as a template next time, or share the invoice link.',
                'url' => $url,
            ], 'commerce');
        }
    }
}
