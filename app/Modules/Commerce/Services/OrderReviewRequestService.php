<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Mail\OrderReviewRequestMail;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Asks the customer of a completed order to rate the products they received,
 * by WhatsApp, app notification and email. The link opens the order page on the
 * storefront, where the existing "Rate your products" panel is shown.
 */
class OrderReviewRequestService
{
    public const SENT = 'sent';

    public const SKIPPED_DISABLED = 'disabled';

    public const SKIPPED_NOT_COMPLETED = 'not_completed';

    public const SKIPPED_ALREADY_SENT = 'already_sent';

    public const SKIPPED_NOTHING_TO_RATE = 'nothing_to_rate';

    public const SKIPPED_NO_CHANNEL = 'no_channel_reached';

    public function __construct(protected ChannelManager $channels, protected StorefrontUrl $urls) {}

    /**
     * @throws RuntimeException when WhatsApp rejects the message (so the queue can retry)
     */
    public function send(Order $order): string
    {
        $order->loadMissing(['contact', 'items.variant']);
        if ($order->review_request_sent_at) {
            return self::SKIPPED_ALREADY_SENT;
        }
        if ($order->status !== 'completed') {
            return self::SKIPPED_NOT_COMPLETED;
        }
        $settings = StoreOrderSetting::forWorkspace($order->workspace_id);
        if (! $settings->review_request_enabled) {
            return self::SKIPPED_DISABLED;
        }
        $names = $this->unratedProductNames($order);
        if ($names === []) {
            return self::SKIPPED_NOTHING_TO_RATE;
        }

        $link = $this->urls->path($order->workspace_id, '/account/orders/'.rawurlencode((string) $order->submission_reference));
        $name = $order->customer_snapshot['name'] ?? $order->contact?->name;
        $body = 'Hi '.($name ?: 'there').', your order '.$order->number.' has been delivered. How did you like '.$this->productSummary($names).'? Please rate it here: '.$link;

        $reached = $this->push($order, $names);
        $reached = $this->email($order, $link, $names) || $reached;
        $reached = $this->whatsapp($order, $settings, $body) || $reached;

        if (! $reached) {
            return self::SKIPPED_NO_CHANNEL;
        }
        $order->forceFill(['review_request_sent_at' => now()])->save();

        return self::SENT;
    }

    /**
     * Names of the ordered products this customer has not rated yet.
     *
     * @return array<int, string>
     */
    protected function unratedProductNames(Order $order): array
    {
        $products = $order->items->filter(fn ($item) => $item->variant?->product_id)
            ->unique(fn ($item) => $item->variant->product_id)
            ->mapWithKeys(fn ($item) => [(int) $item->variant->product_id => $item->product_name]);
        if ($products->isEmpty()) {
            return [];
        }
        $customerId = Customer::query()->where('workspace_id', $order->workspace_id)->where('contact_id', $order->contact_id)->value('id');
        $rated = $customerId === null ? [] : ProductReview::query()
            ->where('workspace_id', $order->workspace_id)->where('customer_id', $customerId)
            ->whereIn('product_id', $products->keys())->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        return $products->except($rated)->values()->all();
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function productSummary(array $names): string
    {
        $more = count($names) - 1;

        return $more > 0 ? $names[0].' and '.$more.' other '.($more === 1 ? 'item' : 'items') : $names[0];
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function push(Order $order, array $names): bool
    {
        if (! filled($order->customer_reference)) {
            return false;
        }
        $devices = MobileCustomerDevice::query()->where('workspace_id', $order->workspace_id)->where('customer_reference', $order->customer_reference)->get();
        if ($devices->isEmpty()) {
            return false;
        }
        if (Cache::add('mobile-review-request:'.$order->id, true, now()->addDays(7))) {
            foreach ($devices as $device) {
                $device->notify(new MobileAlert('How was your order?', 'Rate '.$this->productSummary($names), ['type' => 'order', 'reference' => $order->submission_reference], $order->workspace_id));
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function email(Order $order, string $link, array $names): bool
    {
        $email = $order->customer_snapshot['email'] ?? $order->contact?->email;
        if (! filled($email)) {
            return false;
        }
        if (Cache::add('email-review-request:'.$order->id, true, now()->addDays(7))) {
            Mail::to($email)->send(new OrderReviewRequestMail($order, $link, $names));
        }

        return true;
    }

    protected function whatsapp(Order $order, StoreOrderSetting $settings, string $body): bool
    {
        $contact = $order->contact;
        if (! $settings->whatsapp_notifications || ! $contact || $contact->blocked_at || $contact->opt_out_at || $contact->opt_in_status === ContactOptInStatus::Unsubscribed) {
            return false;
        }
        $phone = $order->customer_snapshot['phone'] ?? $contact->phone;
        if ($phone !== $contact->phone) {
            return false;
        }
        $account = ChannelAccount::query()->where('workspace_id', $order->workspace_id)->where('provider', 'whatsapp')->where('status', 'connected')->find($order->channel_account_id ?? $settings->whatsapp_channel_id);
        if (! $account) {
            return false;
        }
        $conversation = Conversation::query()->where('workspace_id', $order->workspace_id)->where('contact_id', $contact->id)->where('channel_account_id', $account->id)->latest('id')->first();
        if (! $conversation) {
            return false;
        }
        $payload = ['type' => 'text', 'body' => $body];
        if (! $conversation->session_expires_at || $conversation->session_expires_at->isPast()) {
            $payload = $this->templatePayload($order, $settings, $account, $contact->opt_in_status, $body);
            if ($payload === null) {
                return false;
            }
        }
        $attempt = CommerceMessageAttempt::query()->firstOrCreate(['idempotency_key' => hash('sha256', 'review-request:'.$order->id)], ['workspace_id' => $order->workspace_id, 'conversation_id' => $conversation->id, 'message_type' => $payload['type'], 'status' => 'pending', 'request_payload' => $payload]);
        if (in_array($attempt->status, ['completed', 'uncertain', 'processing'])) {
            return true;
        }
        $message = $attempt->message ?? Message::query()->create(['workspace_id' => $order->workspace_id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'outbound', 'type' => $payload['type'], 'body' => $body, 'payload' => ['review_request_order_id' => $order->id, 'request' => $payload], 'status' => 'queued']);
        $attempt->update(['message_id' => $message->id, 'status' => 'processing']);
        $result = $this->channels->sendMessage($account, ['to' => $phone], $payload);
        $ok = (bool) ($result['ok'] ?? false);
        $message->update(['provider_message_id' => $result['provider_message_id'] ?? null, 'whatsapp_message_id' => $result['provider_message_id'] ?? null, 'status' => $ok ? 'sent' : 'failed']);
        $attempt->update(['status' => $ok ? 'completed' : (($result['error_code'] ?? '') === 'connection_error' ? 'uncertain' : 'failed'), 'last_error' => $result['error'] ?? null]);
        if (! $ok && $attempt->status === 'failed') {
            throw new RuntimeException($result['error'] ?? 'Review request failed.');
        }

        return $ok || $attempt->status === 'uncertain';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function templatePayload(Order $order, StoreOrderSetting $settings, ChannelAccount $account, mixed $optIn, string $body): ?array
    {
        if ($optIn !== ContactOptInStatus::Subscribed) {
            return null;
        }
        $template = MessageTemplate::query()->where('workspace_id', $order->workspace_id)->where('provider', 'whatsapp')->where('category', 'utility')->find($settings->whatsapp_template_id);
        if (! $template || ! $template->approvedForWaba($account->provider_account_id)) {
            return null;
        }
        $components = collect($template->components ?? []);
        $templateBody = $components->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $templateBody['text'] ?? '', $variables);
        if (array_values(array_unique($variables[1])) !== ['1'] || $components->contains(fn ($component) => in_array(strtoupper($component['type'] ?? ''), ['HEADER', 'BUTTONS']))) {
            return null;
        }

        return ['type' => 'template', 'template_name' => $template->name, 'language' => $template->language, 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $body]]]]];
    }
}
