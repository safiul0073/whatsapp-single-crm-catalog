<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\SendMetaEvent;
use App\Modules\Commerce\Jobs\SendMetaPurchaseEvent;
use App\Modules\Commerce\Jobs\SendTikTokPurchaseEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Services\TrackingSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends server-side events to the Meta Conversions API. Each event reuses the browser pixel's
 * eventID (Purchase: eventId(), other events: the X-Meta-Event-Id header), so Meta counts it once.
 */
class MetaConversionsService
{
    public const SENT = 'sent';

    public const RETRY = 'retry';

    public const RATE_LIMITED = 'rate_limited';

    public const DROPPED = 'dropped';

    public const SKIPPED = 'skipped';

    /** Graph error codes that mean "slow down" rather than "bad request". */
    private const RATE_LIMIT_CODES = [4, 17, 32, 613, 80004];

    public function __construct(
        protected TrackingSettingsService $tracking,
        protected WhatsAppSettingsService $settings,
    ) {}

    public static function eventId(Order $order): string
    {
        return 'purchase-'.$order->number;
    }

    /**
     * Keeps the checkout's browser details until the owner confirms payment (no database column needed).
     *
     * @param  array<string, mixed>  $client
     */
    public static function rememberClient(Order $order, array $client): void
    {
        Cache::put('commerce:ad-client:'.$order->id, $client, now()->addDays(30));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rememberedClient(Order $order): array
    {
        return (array) Cache::get('commerce:ad-client:'.$order->id, []);
    }

    /**
     * Storefront orders report Purchase (Meta and TikTok) once payment is confirmed.
     */
    public static function queuePurchase(Order $order): void
    {
        if ($order->source !== 'storefront_checkout') {
            return;
        }

        DB::afterCommit(function () use ($order): void {
            rescue(fn () => SendMetaPurchaseEvent::dispatch($order->id), report: true);
            rescue(fn () => SendTikTokPurchaseEvent::dispatch($order->id), report: true);
        });
    }

    /**
     * Catalog item id: the variant's Meta retailer id, else its SKU, else the variant id.
     *
     * @param  array<string, mixed>|object  $line
     */
    public static function contentId(array|object $line): string
    {
        return (string) (data_get($line, 'retailer_id') ?: data_get($line, 'sku') ?: data_get($line, 'variant_id'));
    }

    /**
     * Browser details the storefront BFF forwards. They are trusted only on the platform-secret
     * prefix; mobile app requests carry no browser identifiers.
     *
     * @return array{ip: ?string, user_agent: ?string, fbp: ?string, fbc: ?string, ttp: ?string, host: ?string, event_id: ?string, source_url: ?string, consent: ?string, origin: string}
     */
    public function clientContext(Request $r): array
    {
        $host = $r->header('X-Store-Host') ?: null;

        if (! $this->fromWebStorefront($r)) {
            return ['ip' => null, 'user_agent' => null, 'fbp' => null, 'fbc' => null, 'ttp' => null, 'host' => $host, 'event_id' => null, 'source_url' => null, 'consent' => null, 'origin' => 'mobile'];
        }

        $ip = (string) $r->header('X-Client-Ip');
        $eventId = (string) $r->header('X-Meta-Event-Id');
        $consent = (string) $r->header('X-Tracking-Consent');

        return [
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            'user_agent' => Str::limit((string) $r->header('X-Client-User-Agent'), 500, '') ?: null,
            'fbp' => preg_match('/^fb\.\d\.\d+\.\d+$/', (string) $r->header('X-Meta-Fbp')) === 1 ? (string) $r->header('X-Meta-Fbp') : null,
            'fbc' => preg_match('/^fb\.\d\.\d+\.[\w-]+$/', (string) $r->header('X-Meta-Fbc')) === 1 ? (string) $r->header('X-Meta-Fbc') : null,
            'ttp' => preg_match('/^[\w.-]{10,200}$/', (string) $r->header('X-Tiktok-Ttp')) === 1 ? (string) $r->header('X-Tiktok-Ttp') : null,
            'host' => $host,
            'event_id' => preg_match('/^[A-Za-z0-9_-]{8,64}$/', $eventId) === 1 ? $eventId : null,
            'source_url' => $this->sourceUrl((string) $r->header('X-Event-Source-Url'), $host),
            'consent' => in_array($consent, ['granted', 'denied'], true) ? $consent : null,
            'origin' => 'web',
        ];
    }

    /**
     * Consent withdrawal or a missing answer blocks server events when the store asks for consent.
     *
     * @param  array<string, mixed>  $client
     */
    public function consented(int $workspace, array $client): bool
    {
        return ! $this->tracking->consentRequired($workspace) || ($client['consent'] ?? null) === 'granted';
    }

    /**
     * Queues a browser-paired event (PageView, ViewContent, AddToCart, InitiateCheckout). Silently does
     * nothing without CAPI credentials, consent, or a browser event id to deduplicate against.
     *
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $customData
     */
    public function queue(int $workspace, string $name, array $client, array $customData = [], ?Customer $customer = null): void
    {
        rescue(function () use ($workspace, $name, $client, $customData, $customer): void {
            if (($client['origin'] ?? null) !== 'web' || blank($client['event_id'] ?? null) || ! $this->consented($workspace, $client) || ! $this->tracking->metaConversions($workspace)) {
                return;
            }

            SendMetaEvent::dispatch($workspace, $this->event(
                $name,
                (string) $client['event_id'],
                now()->getTimestamp(),
                'website',
                $client['source_url'] ?? null,
                $this->userData($this->customerIdentity($customer), $client),
                $customData,
            ));
        }, report: true);
    }

    /**
     * AddToCart for cart lines that a successful request just added, priced from the cart summary.
     *
     * @param  array<string, mixed>  $summary  CartService::summary()
     * @param  list<array<string, mixed>>  $lines  Summary items, with `quantity` set to the added amount.
     */
    public function queueAddToCart(Request $r, array $summary, array $lines): void
    {
        $precision = (int) ($summary['precision'] ?? 2);
        $lines = array_map(fn (array $line): array => array_merge($line, [
            'line_total' => OrderMoney::decimal(OrderMoney::minor((string) ($line['price'] ?? 0), $precision) * (int) $line['quantity'], $precision),
        ]), $lines);
        $client = $this->clientContext($r);
        $workspace = (int) $r->attributes->get('store_workspace')->id;
        $customer = $r->attributes->get('store_customer');

        DB::afterCommit(fn () => $this->queue($workspace, 'AddToCart', $client, $this->cartData($lines, (string) $summary['currency']), $customer));
    }

    /**
     * Returns the delivery outcome; SKIPPED when Conversions API is not configured.
     *
     * @param  list<array<string, mixed>>  $events
     */
    public function send(int $workspace, array $events): string
    {
        $credentials = $this->tracking->metaConversions($workspace);

        if (! $credentials || $events === []) {
            return self::SKIPPED;
        }

        $body = ['data' => $events];

        if ($credentials['test_event_code'] !== '') {
            $body['test_event_code'] = $credentials['test_event_code'];
        }

        try {
            $response = Http::withToken($credentials['token'])
                ->connectTimeout(5)
                ->timeout(20)
                ->post('https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.$credentials['pixel_id'].'/events', $body);
        } catch (ConnectionException) {
            return self::RETRY;
        }

        return $this->outcome($workspace, $response, (string) ($events[0]['event_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $client
     */
    public function sendPurchase(Order $order, array $client = []): string
    {
        return $this->send((int) $order->workspace_id, [$this->purchaseEvent($order, $client)]);
    }

    /**
     * @param  array<string, mixed>  $client
     * @return array<string, mixed>
     */
    public function purchaseEvent(Order $order, array $client = []): array
    {
        $order->loadMissing('items');
        $web = ($client['origin'] ?? 'web') === 'web';
        $host = (string) ($client['host'] ?? '');
        $sourceUrl = $client['source_url'] ?? ($host !== '' ? 'https://'.$host.'/checkout' : null);
        $customer = (array) $order->customer_snapshot;
        $address = (array) $order->shipping_address;

        return $this->event(
            'Purchase',
            self::eventId($order),
            ($order->paid_at ?? $order->created_at ?? now())->getTimestamp(),
            $web ? 'website' : 'other',
            $web ? $sourceUrl : null,
            $this->userData([
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'name' => $customer['name'] ?? null,
                'city' => $address['city'] ?? null,
                'country' => $address['country'] ?? null,
                'contact_id' => $order->contact_id,
            ], $web ? $client : []),
            [
                'currency' => strtoupper((string) $order->currency),
                'value' => (float) $order->total,
                'order_id' => (string) $order->number,
                'content_type' => 'product',
                'content_ids' => $order->items->map(fn ($item): string => self::contentId($item))->values()->all(),
                'num_items' => (int) $order->items->sum('quantity'),
                'contents' => $order->items->map(fn ($item): array => [
                    'id' => self::contentId($item),
                    'quantity' => (int) $item->quantity,
                    'item_price' => (float) $item->unit_price,
                ])->values()->all(),
            ],
        );
    }

    /**
     * custom_data for cart lines (AddToCart, InitiateCheckout); value is the lines' total.
     *
     * @param  iterable<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public function cartData(iterable $lines, string $currency, ?string $value = null): array
    {
        $lines = collect($lines)->filter(fn (array $line): bool => (int) ($line['quantity'] ?? 0) > 0)->values();

        return [
            'currency' => strtoupper($currency),
            'value' => (float) ($value ?? $lines->sum(fn (array $line): float => (float) ($line['line_total'] ?? 0))),
            'content_type' => 'product',
            'content_ids' => $lines->map(fn (array $line): string => self::contentId($line))->unique()->values()->all(),
            'contents' => $lines->map(fn (array $line): array => [
                'id' => self::contentId($line),
                'quantity' => (int) $line['quantity'],
                'item_price' => (float) ($line['price'] ?? 0),
            ])->all(),
            'num_items' => (int) $lines->sum('quantity'),
        ];
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    public function event(string $name, string $eventId, int $time, string $actionSource, ?string $sourceUrl, array $userData, array $customData = []): array
    {
        return array_filter([
            'event_name' => $name,
            'event_time' => $time,
            'event_id' => $eventId,
            'action_source' => $actionSource,
            'event_source_url' => $sourceUrl,
            'user_data' => $userData,
            'custom_data' => $customData !== [] ? $customData : null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * Meta requires identifiers to be normalized, then SHA-256 hashed; IP, user agent, fbp and fbc are sent as is.
     *
     * @param  array{email?: ?string, phone?: ?string, name?: ?string, city?: ?string, country?: ?string, contact_id?: int|string|null}  $person
     * @param  array<string, mixed>  $client
     * @return array<string, mixed>
     */
    public function userData(array $person, array $client): array
    {
        $names = preg_split('/\s+/', trim((string) ($person['name'] ?? '')), 2) ?: [];

        return array_filter([
            'em' => $this->hashed(strtolower(trim((string) ($person['email'] ?? '')))),
            'ph' => $this->hashed(preg_replace('/\D+/', '', (string) ($person['phone'] ?? ''))),
            'fn' => $this->hashed(mb_strtolower($names[0] ?? '')),
            'ln' => $this->hashed(mb_strtolower($names[1] ?? '')),
            'ct' => $this->hashed(preg_replace('/[^a-z]/', '', mb_strtolower((string) ($person['city'] ?? '')))),
            'country' => $this->hashed(strtolower(substr(trim((string) ($person['country'] ?? '')), 0, 2))),
            'external_id' => filled($person['contact_id'] ?? null) ? $this->hashed((string) $person['contact_id']) : null,
            'client_ip_address' => $client['ip'] ?? null,
            'client_user_agent' => $client['user_agent'] ?? null,
            'fbp' => $client['fbp'] ?? null,
            'fbc' => $client['fbc'] ?? null,
        ], fn ($value): bool => filled($value));
    }

    /**
     * @return array{email?: ?string, phone?: ?string, name?: ?string, contact_id?: int|string|null}
     */
    protected function customerIdentity(?Customer $customer): array
    {
        if (! $customer) {
            return [];
        }

        $contact = $customer->contact;

        return [
            'email' => $contact?->email,
            'phone' => $customer->verified_phone ?: $contact?->phone,
            'name' => $contact?->name,
            'contact_id' => $customer->contact_id,
        ];
    }

    protected function outcome(int $workspace, Response $response, string $eventName): string
    {
        if ($response->successful()) {
            return self::SENT;
        }

        $code = (int) $response->json('error.code');
        $context = ['workspace_id' => $workspace, 'event' => $eventName, 'status' => $response->status(), 'code' => $code, 'error' => $response->json('error.message')];

        if ($response->status() === 429 || in_array($code, self::RATE_LIMIT_CODES, true)) {
            return self::RATE_LIMITED;
        }

        if ($response->serverError()) {
            return self::RETRY;
        }

        if ($code === 190) {
            if (Cache::add('meta-capi-token-invalid:'.$workspace, true, now()->addDay())) {
                logger()->warning('Meta Conversions API token is invalid or expired; update it in Storefront → Ads & Tracking.', $context);
            }

            return self::DROPPED;
        }

        logger()->warning('Meta Conversions API rejected an event.', $context);

        return self::DROPPED;
    }

    protected function fromWebStorefront(Request $r): bool
    {
        return str_starts_with((string) $r->route()?->getName(), 'storefront.');
    }

    protected function sourceUrl(string $url, ?string $host): ?string
    {
        if ($url === '' || strlen($url) > 1000 || ! $host || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        return strcasecmp((string) parse_url($url, PHP_URL_HOST), $host) === 0 ? $url : null;
    }

    /**
     * @return array<int, string>|null
     */
    protected function hashed(?string $value): ?array
    {
        return filled($value) ? [hash('sha256', $value)] : null;
    }
}
