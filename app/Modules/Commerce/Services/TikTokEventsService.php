<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Services\TrackingSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends server-side purchase events to the TikTok Events API. The event id is the one the browser pixel
 * uses (see MetaConversionsService::eventId()), so TikTok counts each order once.
 */
class TikTokEventsService
{
    public const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function __construct(protected TrackingSettingsService $tracking) {}

    /**
     * Returns null when the Events API is not configured for the order's workspace.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, ttp?: ?string, host?: ?string}  $client
     */
    public function sendPurchase(Order $order, array $client = []): ?Response
    {
        $credentials = $this->tracking->tiktokEvents((int) $order->workspace_id);

        if (! $credentials) {
            return null;
        }

        $body = [
            'event_source' => 'web',
            'event_source_id' => $credentials['pixel_code'],
            'data' => [$this->purchaseEvent($order, $client)],
        ];

        if ($credentials['test_event_code'] !== '') {
            $body['test_event_code'] = $credentials['test_event_code'];
        }

        return Http::withHeaders(['Access-Token' => $credentials['token']])
            ->connectTimeout(5)
            ->timeout(20)
            ->post(self::ENDPOINT, $body);
    }

    /**
     * @param  array{ip?: ?string, user_agent?: ?string, ttp?: ?string, host?: ?string}  $client
     * @return array<string, mixed>
     */
    public function purchaseEvent(Order $order, array $client = []): array
    {
        $order->loadMissing('items');
        $host = (string) ($client['host'] ?? '');
        $customer = (array) $order->customer_snapshot;
        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($customer['phone'] ?? ''));

        return array_filter([
            'event' => 'CompletePayment',
            'event_time' => ($order->created_at ?? now())->getTimestamp(),
            'event_id' => MetaConversionsService::eventId($order),
            'user' => array_filter([
                'email' => $email !== '' ? hash('sha256', $email) : null,
                'phone' => $phone !== '' ? hash('sha256', '+'.$phone) : null,
                'external_id' => $order->contact_id ? hash('sha256', (string) $order->contact_id) : null,
                'ip' => $client['ip'] ?? null,
                'user_agent' => $client['user_agent'] ?? null,
                'ttp' => $client['ttp'] ?? null,
            ], fn ($value): bool => filled($value)),
            'properties' => [
                'currency' => strtoupper((string) $order->currency),
                'value' => (float) $order->total,
                'order_id' => (string) $order->number,
                'content_type' => 'product',
                'contents' => $order->items->map(fn ($item): array => [
                    'content_id' => (string) ($item->retailer_id ?: $item->sku),
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->unit_price,
                ])->values()->all(),
            ],
            'page' => $host !== '' ? ['url' => 'https://'.$host.'/checkout'] : null,
        ], fn ($value): bool => $value !== null);
    }
}
