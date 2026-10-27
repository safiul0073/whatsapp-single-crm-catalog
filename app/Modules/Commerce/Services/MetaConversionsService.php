<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Services\TrackingSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends server-side Purchase events to the Meta Conversions API. The event id matches the
 * browser pixel's eventID (see eventId()), so Meta counts each order once.
 */
class MetaConversionsService
{
    public function __construct(
        protected TrackingSettingsService $tracking,
        protected WhatsAppSettingsService $settings,
    ) {}

    public static function eventId(Order $order): string
    {
        return 'purchase-'.$order->number;
    }

    /**
     * Returns null when Conversions API is not configured for the order's workspace.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, fbp?: ?string, fbc?: ?string, host?: ?string}  $client
     */
    public function sendPurchase(Order $order, array $client = []): ?Response
    {
        $credentials = $this->tracking->metaConversions((int) $order->workspace_id);

        if (! $credentials) {
            return null;
        }

        $body = ['data' => [$this->purchaseEvent($order, $client)]];

        if ($credentials['test_event_code'] !== '') {
            $body['test_event_code'] = $credentials['test_event_code'];
        }

        return Http::withToken($credentials['token'])
            ->connectTimeout(5)
            ->timeout(20)
            ->post('https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.$credentials['pixel_id'].'/events', $body);
    }

    /**
     * @param  array{ip?: ?string, user_agent?: ?string, fbp?: ?string, fbc?: ?string, host?: ?string}  $client
     * @return array<string, mixed>
     */
    public function purchaseEvent(Order $order, array $client = []): array
    {
        $order->loadMissing('items');
        $host = (string) ($client['host'] ?? '');

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => ($order->created_at ?? now())->getTimestamp(),
            'event_id' => self::eventId($order),
            'action_source' => 'website',
            'event_source_url' => $host !== '' ? 'https://'.$host.'/checkout' : null,
            'user_data' => $this->userData($order, $client),
            'custom_data' => [
                'currency' => strtoupper((string) $order->currency),
                'value' => (float) $order->total,
                'order_id' => (string) $order->number,
                'content_type' => 'product',
                'num_items' => (int) $order->items->sum('quantity'),
                'contents' => $order->items->map(fn ($item): array => [
                    'id' => (string) ($item->retailer_id ?: $item->sku),
                    'quantity' => (int) $item->quantity,
                    'item_price' => (float) $item->unit_price,
                ])->values()->all(),
            ],
        ], fn ($value): bool => $value !== null);
    }

    /**
     * Meta requires identifiers to be normalized, then SHA-256 hashed; IP, user agent, fbp and fbc are sent as is.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, fbp?: ?string, fbc?: ?string, host?: ?string}  $client
     * @return array<string, mixed>
     */
    protected function userData(Order $order, array $client): array
    {
        $customer = (array) $order->customer_snapshot;
        $address = (array) $order->shipping_address;
        $names = preg_split('/\s+/', trim((string) ($customer['name'] ?? '')), 2) ?: [];

        return array_filter([
            'em' => $this->hashed(strtolower(trim((string) ($customer['email'] ?? '')))),
            'ph' => $this->hashed(preg_replace('/\D+/', '', (string) ($customer['phone'] ?? ''))),
            'fn' => $this->hashed(mb_strtolower($names[0] ?? '')),
            'ln' => $this->hashed(mb_strtolower($names[1] ?? '')),
            'ct' => $this->hashed(preg_replace('/[^a-z]/', '', mb_strtolower((string) ($address['city'] ?? '')))),
            'country' => $this->hashed(strtolower(substr(trim((string) ($address['country'] ?? '')), 0, 2))),
            'external_id' => $order->contact_id ? $this->hashed((string) $order->contact_id) : null,
            'client_ip_address' => $client['ip'] ?? null,
            'client_user_agent' => $client['user_agent'] ?? null,
            'fbp' => $client['fbp'] ?? null,
            'fbc' => $client['fbc'] ?? null,
        ], fn ($value): bool => filled($value));
    }

    /**
     * @return array<int, string>|null
     */
    protected function hashed(?string $value): ?array
    {
        return filled($value) ? [hash('sha256', $value)] : null;
    }
}
