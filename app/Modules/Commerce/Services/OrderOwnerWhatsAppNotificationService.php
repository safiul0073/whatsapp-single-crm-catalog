<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Support\Facades\Log;

class OrderOwnerWhatsAppNotificationService
{
    public function __construct(protected ChannelManager $channels) {}

    /**
     * Texts the store owner when a new order is placed. WhatsApp only allows free text inside the 24-hour
     * window opened by the owner's own message, so otherwise the approved utility template is used.
     *
     * @return bool true when the alert was sent or intentionally skipped (nothing left to retry)
     */
    public function send(Order $order, OrderEvent $event): bool
    {
        if ($event->key !== 'placed') {
            return true;
        }

        return $this->sendToOwner($order->workspace_id, $this->summary($order), ['order' => $order->number]);
    }

    /**
     * Sends a text to the owner's WhatsApp number from the store's connected channel: free text inside the
     * 24-hour window, otherwise the approved utility template.
     *
     * @param  array<string, mixed>  $context  extra log context
     * @return bool true when the alert was sent or intentionally skipped (nothing left to retry)
     */
    public function sendToOwner(int $workspaceId, string $text, array $context = []): bool
    {
        $settings = StoreOrderSetting::forWorkspace($workspaceId);
        $ownerPhone = preg_replace('/[^\d+]/', '', (string) $settings->owner_whatsapp_number);

        if (blank($ownerPhone)) {
            return true;
        }

        $account = ChannelAccount::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('status', 'connected')
            ->when($settings->whatsapp_channel_id, fn ($query, $id) => $query->whereKey($id))->oldest('id')->first();

        if (! $account) {
            Log::warning('Owner WhatsApp alert skipped: no connected WhatsApp channel.', $context);

            return true;
        }

        $payload = $this->payloadFor($workspaceId, $text, $settings, $account, $ownerPhone);

        if ($payload === null) {
            Log::warning('Owner WhatsApp alert skipped: no open service window and no approved template.', $context);

            return true;
        }

        $result = $this->channels->sendMessage($account, ['to' => $ownerPhone, 'phone' => $ownerPhone], $payload);

        if (! ($result['ok'] ?? false)) {
            throw new \RuntimeException($result['error'] ?? 'Owner WhatsApp alert failed.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function payloadFor(int $workspaceId, string $text, StoreOrderSetting $settings, ChannelAccount $account, string $ownerPhone): ?array
    {
        if ($this->hasOpenServiceWindow($workspaceId, $account, $ownerPhone)) {
            return ['type' => 'text', 'body' => $text];
        }

        $template = MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('category', 'utility')->find($settings->whatsapp_template_id);

        if (! $template || ! $template->approvedForWaba((string) $account->provider_account_id)) {
            return null;
        }

        return [
            'type' => 'template',
            'template_name' => $template->name,
            'language' => $template->language,
            'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $text]]]],
        ];
    }

    protected function hasOpenServiceWindow(int $workspaceId, ChannelAccount $account, string $ownerPhone): bool
    {
        $contact = Contact::query()->where('workspace_id', $workspaceId)->where('phone', $ownerPhone)->first();

        return $contact !== null && Conversation::query()->where('workspace_id', $workspaceId)->where('contact_id', $contact->id)
            ->where('channel_account_id', $account->id)->where('session_expires_at', '>', now())->exists();
    }

    protected function summary(Order $order): string
    {
        $customer = $order->customer_snapshot['name'] ?? $order->contact?->name ?? 'Customer';
        $pieces = (int) $order->items()->sum('quantity');
        $total = $order->total ?? $order->subtotal;

        return "New order {$order->number} · {$customer} · {$pieces} pcs · {$order->currency} {$total}".($order->shipping_quote_required ? ' · shipping quote needed' : '')
            .' · '.route('user.commerce.orders.show', $order);
    }
}
