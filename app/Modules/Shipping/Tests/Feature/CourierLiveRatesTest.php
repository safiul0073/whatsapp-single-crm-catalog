<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\Rates\CourierApiRateProvider;
use App\Modules\Shipping\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->actingAs($this->owner);

    $order = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'number' => 'ORD-'.fake()->unique()->numerify('######'),
        'provider_message_id' => fake()->uuid(),
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => '500.0000',
        'total' => '500.0000',
        'shipping_address' => ['name' => 'Jane Buyer', 'phone' => '+12025550147', 'line1' => '1 Main St', 'city' => 'Anchorage', 'state' => 'AK', 'postal_code' => '99501', 'country' => 'US'],
    ]);
    $this->shipment = app(ShipmentService::class)->create($order);
    app(ShipmentService::class)->syncCartons($this->shipment, [['quantity' => 2, 'actual_weight_kg' => 10, 'length_cm' => 40.4, 'width_cm' => 30, 'height_cm' => 20]]);
    $this->shipment->refresh();
});

function courierAccount(int $workspaceId, string $carrier, array $attributes = []): CarrierAccount
{
    $credentials = $carrier === 'aramex'
        ? ['username' => 'api@example.test', 'password' => 'secret-pass', 'account_pin' => '1234', 'account_entity' => 'DXB', 'account_country_code' => 'AE']
        : ['client_id' => 'client-'.$carrier, 'client_secret' => 'super-secret-'.$carrier];

    return CarrierAccount::query()->create(array_replace([
        'workspace_id' => $workspaceId,
        'name' => strtoupper($carrier).' main',
        'carrier' => $carrier,
        'account_number' => 'ACC123',
        'environment' => 'sandbox',
        'credentials' => $credentials,
        'origin_address_line1' => '1 Factory Rd',
        'origin_city' => 'Karachi',
        'origin_postal_code' => '74000',
        'origin_country_code' => 'PK',
        'is_active' => true,
    ], $attributes));
}

function upsShopResponse(array $charges): array
{
    return ['RateResponse' => ['RatedShipment' => [[
        'Service' => ['Code' => '65'],
        'TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '150.00'],
        'NegotiatedRateCharges' => ['TotalCharge' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '120.00'], 'ItemizedCharges' => $charges],
        'BillingWeight' => ['UnitOfMeasurement' => ['Code' => 'KGS'], 'Weight' => '24.0'],
        'TimeInTransit' => ['ServiceSummary' => ['EstimatedArrival' => ['BusinessDaysInTransit' => '3']]],
    ]]]];
}

it('turns a UPS shop response into itemised quotes and confirms the remote area', function () {
    $account = courierAccount($this->workspace->id, 'ups');
    Http::fake([
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token', 'expires_in' => 3600]),
        'wwwcie.ups.com/api/rating/*' => Http::response(upsShopResponse([
            ['Code' => '375', 'Description' => 'Fuel Surcharge', 'CurrencyCode' => 'USD', 'MonetaryValue' => '15.00'],
            ['Code' => '376', 'Description' => 'Remote Area Surcharge', 'CurrencyCode' => 'USD', 'MonetaryValue' => '25.00'],
        ])),
    ]);

    $quotes = app(CourierApiRateProvider::class)->quotes($this->shipment);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]['service'])->toBe('UPS Worldwide Saver')
        ->and($quotes[0]['source'])->toBe('api')
        ->and($quotes[0]['base_rate'])->toBe('80.00')
        ->and($quotes[0]['remote_status'])->toBe('remote')
        ->and($quotes[0]['status'])->toBe('verified')
        ->and($quotes[0]['transit_days'])->toBe('3 business day(s)')
        ->and($quotes[0]['billable_weight_kg'])->toBe(24.0)
        ->and(array_column($quotes[0]['charges'], 'reason'))->toBe(['fuel', 'remote_area']);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/api/rating/v2409/Shop')) {
            return false;
        }
        $packages = $request['RateRequest']['Shipment']['Package'];

        return $request->hasHeader('Authorization', 'Bearer ups-token') && count($packages) === 2 && $packages[0]['Dimensions']['Length'] === '41';
    });

    $remote = RemoteArea::query()->where('carrier', 'ups')->sole();
    expect($remote->status)->toBe('remote')
        ->and($remote->postal_from)->toBe('99501')
        ->and((float) $remote->fee)->toBe(25.0)
        ->and($account->fresh()->last_success_at)->not->toBeNull();
});

it('reuses a cached UPS token and refreshes it once after a 401', function () {
    courierAccount($this->workspace->id, 'ups');
    Http::fakeSequence('wwwcie.ups.com/security/*')
        ->push(['access_token' => 'first', 'expires_in' => 3600])
        ->push(['access_token' => 'second', 'expires_in' => 3600]);
    Http::fakeSequence('wwwcie.ups.com/api/rating/*')
        ->push(upsShopResponse([]))
        ->push(['response' => ['errors' => [['code' => '250002', 'message' => 'Invalid token']]]], 401)
        ->push(upsShopResponse([]));

    $provider = app(CourierApiRateProvider::class);
    $provider->quotes($this->shipment);
    $quotes = $provider->quotes($this->shipment);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]['remote_status'])->toBe('normal')
        ->and($provider->failures())->toBe([]);
    Http::assertSentCount(5);
});

it('reads FedEx account rates and sends customs details for international shipments', function () {
    courierAccount($this->workspace->id, 'fedex');
    Http::fake([
        'apis-sandbox.fedex.com/oauth/token' => Http::response(['access_token' => 'fx-token', 'expires_in' => 3600]),
        'apis-sandbox.fedex.com/rate/*' => Http::response(['output' => ['rateReplyDetails' => [[
            'serviceType' => 'INTERNATIONAL_PRIORITY',
            'serviceName' => 'FedEx International Priority',
            'commit' => ['daysInTransit' => 'THREE_DAYS'],
            'ratedShipmentDetails' => [
                ['rateType' => 'LIST', 'totalNetCharge' => 300, 'shipmentRateDetail' => ['currency' => 'USD']],
                ['rateType' => 'ACCOUNT', 'totalNetCharge' => 210.5, 'shipmentRateDetail' => [
                    'currency' => 'USD',
                    'totalBillingWeight' => ['units' => 'LB', 'value' => 50],
                    'surCharges' => [
                        ['type' => 'FUEL', 'description' => 'Fuel', 'amount' => 30.5],
                        ['type' => 'OUT_OF_DELIVERY_AREA', 'description' => 'Out of delivery area', 'amount' => 20],
                    ],
                ]],
            ],
        ]]]]),
    ]);

    $quotes = app(CourierApiRateProvider::class)->quotes($this->shipment);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]['service'])->toBe('FedEx International Priority')
        ->and($quotes[0]['base_rate'])->toBe('160.00')
        ->and($quotes[0]['remote_status'])->toBe('remote')
        ->and(array_column($quotes[0]['charges'], 'reason'))->toBe(['fuel', 'remote_area'])
        ->and($quotes[0]['billable_weight_kg'])->toEqualWithDelta(22.68, 0.01);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/rate/v1/rates/quotes')
        && $request['accountNumber']['value'] === 'ACC123'
        && isset($request['requestedShipment']['customsClearanceDetail']['commodities'][0])
        && is_int($request['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['length']));
});

it('parses an Aramex SOAP rate and leaves the remote status unknown', function () {
    courierAccount($this->workspace->id, 'aramex', ['exchange_rate' => '0.27229']);
    Http::fake(['ws.aramex.net/*' => Http::response(
        '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><RateCalculatorResponse xmlns="http://ws.aramex.net/ShippingAPI/v1/">'
        .'<Transaction/><Notifications/><HasErrors>false</HasErrors>'
        .'<TotalAmount><CurrencyCode>AED</CurrencyCode><Value>450.75</Value></TotalAmount>'
        .'</RateCalculatorResponse></s:Body></s:Envelope>',
        200,
        ['Content-Type' => 'text/xml'],
    )]);

    $quotes = app(CourierApiRateProvider::class)->quotes($this->shipment);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]['currency'])->toBe('AED')
        ->and($quotes[0]['exchange_rate'])->toBe('0.27229000')
        ->and($quotes[0]['status'])->toBe('verified')
        ->and($quotes[0]['remote_status'])->toBe('unknown');
    expect(RemoteArea::query()->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('SOAPAction', '"http://ws.aramex.net/ShippingAPI/v1/Service_1_0/CalculateRate"')
        && str_contains($request->body(), '<v1:AccountPin>1234</v1:AccountPin>'));
});

it('marks a quote in a foreign currency unverified until an exchange rate is set', function () {
    courierAccount($this->workspace->id, 'aramex');
    Http::fake(['ws.aramex.net/*' => Http::response(
        '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><RateCalculatorResponse xmlns="http://ws.aramex.net/ShippingAPI/v1/">'
        .'<HasErrors>false</HasErrors><TotalAmount><CurrencyCode>AED</CurrencyCode><Value>100</Value></TotalAmount>'
        .'</RateCalculatorResponse></s:Body></s:Envelope>',
    )]);

    $quotes = app(CourierApiRateProvider::class)->quotes($this->shipment);

    expect($quotes[0]['status'])->toBe('unverified');
});

it('keeps other couriers working when one fails and records the error', function () {
    $aramex = courierAccount($this->workspace->id, 'aramex');
    courierAccount($this->workspace->id, 'ups');
    courierAccount($this->workspace->id, 'fedex', ['credentials' => null]);
    Http::fake([
        'ws.aramex.net/*' => Http::response(
            '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><RateCalculatorResponse xmlns="http://ws.aramex.net/ShippingAPI/v1/">'
            .'<HasErrors>true</HasErrors><Notifications><Notification><Code>ERR01</Code><Message>Invalid account pin</Message></Notification></Notifications>'
            .'</RateCalculatorResponse></s:Body></s:Envelope>',
        ),
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token']),
        'wwwcie.ups.com/api/rating/*' => Http::response(upsShopResponse([])),
    ]);

    $provider = app(CourierApiRateProvider::class);
    $quotes = $provider->quotes($this->shipment);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]['carrier'])->toBe('ups')
        ->and($provider->failures())->toHaveCount(1)
        ->and($provider->failures()[0]['message'])->toContain('Invalid account pin')
        ->and($aramex->fresh()->last_error)->toContain('Invalid account pin');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'fedex'));
});

it('stores live quotes and warns about failed couriers from the shipment page', function () {
    courierAccount($this->workspace->id, 'ups');
    courierAccount($this->workspace->id, 'fedex');
    Http::fake([
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token']),
        'wwwcie.ups.com/api/rating/*' => Http::response(upsShopResponse([])),
        'apis-sandbox.fedex.com/*' => Http::response(['errors' => [['code' => 'TOO.MANY.REQUESTS']]], 429),
    ]);

    $this->post(route('user.shipping.quotes.request', $this->shipment))
        ->assertRedirect()
        ->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'FedEx is limiting requests'));

    expect($this->shipment->quotes()->where('source', 'api')->count())->toBe(1)
        ->and(Shipment::query()->find($this->shipment->id)->status)->toBe('quoted');
});

it('tests a courier connection and never shows stored secrets', function () {
    $account = courierAccount($this->workspace->id, 'fedex');
    Http::fake(['apis-sandbox.fedex.com/oauth/token' => Http::response(['access_token' => 'fx-token'])]);

    $this->post(route('user.shipping.carriers.test', $account))
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'FedEx accepted'));

    $this->get(route('user.shipping.carriers.edit', $account))
        ->assertOk()
        ->assertDontSee('super-secret-fedex');
    expect($account->toArray())->not->toHaveKey('credentials');
});

it('reports a rejected courier login on the connection test', function () {
    $account = courierAccount($this->workspace->id, 'ups');
    Http::fake(['wwwcie.ups.com/security/*' => Http::response(['response' => ['errors' => [['code' => '10401', 'message' => 'ClientId is Invalid']]]], 401)]);

    $this->post(route('user.shipping.carriers.test', $account))->assertSessionHas('error');

    expect($account->fresh()->last_error)->toContain('ClientId is Invalid');
});

it('does not call couriers when the account is missing its ship-from address', function () {
    courierAccount($this->workspace->id, 'ups', ['origin_country_code' => null]);
    Http::fake();

    expect(app(QuoteService::class)->requestRates($this->shipment))->toBeEmpty();
    Http::assertNothingSent();
});
