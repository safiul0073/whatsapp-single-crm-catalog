<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderShipment;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Jobs\SyncShipmentTracking;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingAuditEntry;
use App\Modules\Shipping\Models\ShippingDocument;
use App\Modules\Shipping\Services\BookingService;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\CustomerShipmentPresenter;
use App\Modules\Shipping\Services\QuoteApprovalService;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\ShipmentService;
use App\Modules\Shipping\Services\ShippingDocumentService;
use App\Modules\Shipping\Services\TrackingService;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->actingAs($this->owner);

    $this->order = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'number' => 'ORD-'.fake()->unique()->numerify('######'),
        'provider_message_id' => fake()->uuid(),
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => '500.0000',
        'total' => '500.0000',
        'shipping_address' => ['name' => 'Jane Buyer', 'phone' => '+12025550147', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US'],
    ]);
    $this->shipment = app(ShipmentService::class)->create($this->order);
    app(ShipmentService::class)->update($this->shipment, ['address_type' => 'residential']);
    app(ShipmentService::class)->syncCartons($this->shipment, [['quantity' => 2, 'actual_weight_kg' => 10, 'length_cm' => 40, 'width_cm' => 30, 'height_cm' => 20]]);
    $this->shipment->refresh();
});

function liveAccount(int $workspaceId, string $carrier, array $attributes = []): CarrierAccount
{
    return CarrierAccount::query()->create(array_replace([
        'workspace_id' => $workspaceId,
        'name' => strtoupper($carrier).' direct',
        'carrier' => $carrier,
        'account_number' => 'ACC777',
        'environment' => 'sandbox',
        'credentials' => $carrier === 'aramex'
            ? ['username' => 'api@example.test', 'password' => 'secret-pass', 'account_pin' => '1234', 'account_entity' => 'DXB', 'account_country_code' => 'AE']
            : ['client_id' => 'id-'.$carrier, 'client_secret' => 'secret-'.$carrier],
        'origin_name' => 'Fashion Works',
        'origin_address_line1' => '1 Factory Rd',
        'origin_city' => 'Karachi',
        'origin_postal_code' => '74000',
        'origin_country_code' => 'PK',
        'origin_phone' => '+92 300 0000000',
        'booking_enabled' => true,
        'is_active' => true,
    ], $attributes));
}

function approveAccountRate(object $test, CarrierAccount $account, string $serviceCode): Shipment
{
    $quote = app(QuoteService::class)->store($test->shipment, [
        'source' => 'api', 'shipping_carrier_account_id' => $account->id, 'carrier' => $account->carrier,
        'service' => 'Express', 'service_code' => $serviceCode, 'base_rate' => '100', 'currency' => 'USD', 'status' => 'verified',
    ]);

    return app(QuoteApprovalService::class)->approve($test->shipment, $quote, $test->owner);
}

function teamMemberWithRole(object $test, string $roleName): User
{
    $member = User::factory()->create();
    $role = WorkspaceRole::query()->where('workspace_id', $test->workspace->id)->where('name', $roleName)->firstOrFail();
    $test->workspace->members()->attach($member->id, ['workspace_role_id' => $role->id, 'status' => WorkspaceMemberStatus::Active->value]);

    return $member;
}

function aramexXml(string $body): string
{
    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'.$body.'</s:Body></s:Envelope>';
}

it('tracks a UPS shipment, stores each scan once and never moves it backwards', function () {
    $account = liveAccount($this->workspace->id, 'ups');
    approveAccountRate($this, $account, '65');
    app(ShipmentService::class)->recordBooking($this->shipment->refresh(), ['tracking_number' => '1Z999AA10123456784'], $this->owner);

    Http::fake([
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token', 'expires_in' => 3600]),
        'wwwcie.ups.com/api/track/*' => Http::response(['trackResponse' => ['shipment' => [['package' => [[
            'currentStatus' => ['description' => 'Out For Delivery Today', 'code' => 'OT'],
            'activity' => [
                ['location' => ['address' => ['city' => 'Austin', 'stateProvince' => 'TX', 'countryCode' => 'US']], 'status' => ['type' => 'I', 'description' => 'Out For Delivery Today', 'code' => 'OT'], 'date' => '20261115', 'time' => '080000'],
                ['location' => ['address' => ['city' => 'Louisville', 'stateProvince' => 'KY', 'countryCode' => 'US']], 'status' => ['type' => 'I', 'description' => 'Departed from Facility', 'code' => 'DP'], 'date' => '20261114', 'time' => '230000'],
                ['status' => ['type' => 'M', 'description' => 'Shipper created a label, UPS has not received the package yet.', 'code' => 'MP'], 'date' => '20261113', 'time' => '100000'],
            ],
        ]]]]]]),
    ]);

    $result = app(TrackingService::class)->sync($this->shipment->refresh());
    $shipment = $this->shipment->refresh();

    expect($result['new_events'])->toBe(3)
        ->and($shipment->status)->toBe('out_for_delivery')
        ->and($shipment->shipped_at)->not->toBeNull()
        ->and($shipment->tracking_status)->toBe('Out For Delivery Today')
        ->and($shipment->tracking_url)->toBe('https://www.ups.com/track?tracknum=1Z999AA10123456784')
        ->and($shipment->trackingEvents()->first()->location)->toBe('Austin, TX, US')
        ->and(CustomerShipmentPresenter::forOrder($this->order)[0]['status'])->toBe('in_transit');

    expect(app(TrackingService::class)->sync($shipment)['new_events'])->toBe(0)
        ->and($shipment->trackingEvents()->count())->toBe(3);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/track/v1/details/1Z999AA10123456784') && $request->hasHeader('Authorization', 'Bearer ups-token'));
    expect(app(ShipmentService::class)->applyTrackingStatus($shipment, 'in_transit'))->toBeFalse();
});

it('tracks an agent shipment with the store FedEx account and records delivery on the order', function () {
    liveAccount($this->workspace->id, 'fedex');
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent A', 'carrier' => 'fedex']);
    $quote = app(QuoteService::class)->store($this->shipment, ['source' => 'manual_whatsapp', 'shipping_agent_id' => $agent->id, 'carrier' => 'fedex', 'base_rate' => '80', 'currency' => 'USD', 'status' => 'verified']);
    app(QuoteApprovalService::class)->approve($this->shipment, $quote, $this->owner);
    app(ShipmentService::class)->recordBooking($this->shipment->refresh(), ['tracking_number' => '794612345678', 'status' => 'in_transit'], $this->owner);

    Http::fake([
        'apis-sandbox.fedex.com/oauth/token' => Http::response(['access_token' => 'fx-token', 'expires_in' => 3600]),
        'apis-sandbox.fedex.com/track/*' => Http::response(['output' => ['completeTrackResults' => [['trackingNumber' => '794612345678', 'trackResults' => [[
            'latestStatusDetail' => ['code' => 'DL', 'derivedCode' => 'DL', 'description' => 'Delivered'],
            'dateAndTimes' => [['type' => 'ACTUAL_DELIVERY', 'dateTime' => '2026-11-15T14:05:00-06:00']],
            'scanEvents' => [
                ['date' => '2026-11-15T14:05:00-06:00', 'eventType' => 'DL', 'eventDescription' => 'Delivered', 'derivedStatusCode' => 'DL', 'scanLocation' => ['city' => 'AUSTIN', 'stateOrProvinceCode' => 'TX', 'countryCode' => 'US']],
                ['date' => '2026-11-14T09:00:00-06:00', 'eventType' => 'AR', 'eventDescription' => 'Arrived at FedEx location', 'derivedStatusCode' => 'IT', 'scanLocation' => ['city' => 'MEMPHIS', 'stateOrProvinceCode' => 'TN', 'countryCode' => 'US']],
            ],
        ]]]]]]),
    ]);

    app(TrackingService::class)->sync($this->shipment->refresh());
    $shipment = $this->shipment->refresh();

    expect($shipment->status)->toBe('delivered')
        ->and($shipment->delivered_at->format('Y-m-d H:i'))->toBe('2026-11-15 20:05')
        ->and(OrderShipment::query()->where('order_id', $this->order->id)->value('delivered_at'))->not->toBeNull()
        ->and(ShippingAuditEntry::query()->where('action', 'tracking.status_changed')->where('shipping_shipment_id', $shipment->id)->exists())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/track/v1/trackingnumbers') && $request['trackingInfo'][0]['trackingNumberInfo']['trackingNumber'] === '794612345678');
});

it('flags an Aramex delivery exception and alerts the owner and the Shipping Manager only', function () {
    $account = liveAccount($this->workspace->id, 'aramex');
    approveAccountRate($this, $account, 'PPX');
    app(ShipmentService::class)->recordBooking($this->shipment->refresh(), ['tracking_number' => '44001122', 'status' => 'in_transit'], $this->owner);
    $manager = teamMemberWithRole($this, 'Shipping Manager');
    $sales = teamMemberWithRole($this, 'Sales Team');

    Http::fake(['ws.aramex.net/*' => Http::response(aramexXml(
        '<ShipmentTrackingResponse xmlns="http://ws.aramex.net/ShippingAPI/v1/"><Notifications/><HasErrors>false</HasErrors>'
        .'<TrackingResults xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays"><a:KeyValueOfstringArrayOfTrackingResultmFAkxlpY><a:Key>44001122</a:Key><a:Value>'
        .'<TrackingResult><WaybillNumber>44001122</WaybillNumber><UpdateCode>SH033</UpdateCode><UpdateDescription>Attempted delivery - consignee not available</UpdateDescription><UpdateDateTime>2026-11-15T10:00:00</UpdateDateTime><UpdateLocation>Dubai</UpdateLocation><Comments></Comments><ProblemCode>A07</ProblemCode></TrackingResult>'
        .'<TrackingResult><WaybillNumber>44001122</WaybillNumber><UpdateCode>SH014</UpdateCode><UpdateDescription>Record created</UpdateDescription><UpdateDateTime>2026-11-13T08:00:00</UpdateDateTime><UpdateLocation>Karachi</UpdateLocation><Comments></Comments><ProblemCode></ProblemCode></TrackingResult>'
        .'</a:Value></a:KeyValueOfstringArrayOfTrackingResultmFAkxlpY></TrackingResults></ShipmentTrackingResponse>'
    ))]);

    app(TrackingService::class)->sync($this->shipment->refresh());
    $shipment = $this->shipment->refresh();
    $alerts = fn (User $user): int => SystemNotification::query()->where('notifiable_id', $user->id)->where('type', 'shipping')->count();

    expect($shipment->status)->toBe('exception')
        ->and($shipment->exception_reason)->toContain('Attempted delivery')
        ->and($alerts($this->owner))->toBe(1)
        ->and($alerts($manager))->toBe(1)
        ->and($alerts($sales))->toBe(0)
        ->and(CustomerShipmentPresenter::forOrder($this->order)[0])->toHaveKeys(['carrier', 'tracking_number', 'status'])
        ->and(CustomerShipmentPresenter::forOrder($this->order)[0])->not->toHaveKey('exception_reason');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/Tracking/Service_1_0.svc') && str_contains($request->body(), '<arr:string>44001122</arr:string>'));
});

it('queues tracking only for open courier shipments that are due and records missing accounts', function () {
    Queue::fake();
    $service = app(ShipmentService::class);
    $make = function (array $attributes) use ($service): Shipment {
        $shipment = $service->create($this->order);
        $shipment->forceFill($attributes)->save();

        return $shipment;
    };
    $due = $make(['status' => 'in_transit', 'carrier' => 'ups', 'tracking_number' => '1ZDUE']);
    $make(['status' => 'in_transit', 'carrier' => 'ups', 'tracking_number' => '1ZRECENT', 'tracking_synced_at' => now()->subMinutes(5)]);
    $make(['status' => 'delivered', 'carrier' => 'ups', 'tracking_number' => '1ZDONE']);
    $make(['status' => 'booked', 'carrier' => null, 'tracking_number' => 'LOCAL-1']);

    $this->artisan('shipping:sync-tracking')->assertSuccessful();

    Queue::assertPushed(SyncShipmentTracking::class, 1);
    Queue::assertPushed(SyncShipmentTracking::class, fn (SyncShipmentTracking $job): bool => $job->shipmentId === $due->id);

    expect(fn () => app(TrackingService::class)->sync($due))->toThrow(CarrierApiException::class)
        ->and($due->refresh()->tracking_error)->toContain('Connect an active UPS account');
});

it('books a UPS shipment only after confirmation and stores every label', function () {
    $account = liveAccount($this->workspace->id, 'ups');
    approveAccountRate($this, $account, '65');

    Http::fake([
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token', 'expires_in' => 3600]),
        'wwwcie.ups.com/api/shipments/*' => Http::response(['ShipmentResponse' => ['ShipmentResults' => [
            'ShipmentCharges' => ['TotalCharges' => ['CurrencyCode' => 'USD', 'MonetaryValue' => '118.40']],
            'ShipmentIdentificationNumber' => '1ZSHIP001',
            'PackageResults' => [
                ['TrackingNumber' => '1ZPKG001', 'ShippingLabel' => ['ImageFormat' => ['Code' => 'GIF'], 'GraphicImage' => base64_encode('GIF89a-label-1')]],
                ['TrackingNumber' => '1ZPKG002', 'ShippingLabel' => ['ImageFormat' => ['Code' => 'GIF'], 'GraphicImage' => base64_encode('GIF89a-label-2')]],
            ],
        ]]]),
    ]);

    $this->post(route('user.shipping.bookings.store', $this->shipment))->assertSessionHasErrors('confirm');
    Http::assertNothingSent();

    $this->post(route('user.shipping.bookings.store', $this->shipment), ['confirm' => '1'])->assertSessionHasNoErrors();
    $shipment = $this->shipment->refresh();

    expect($shipment->status)->toBe('booked')
        ->and($shipment->tracking_number)->toBe('1ZPKG001')
        ->and($shipment->booking_reference)->toBe('1ZSHIP001')
        ->and($shipment->package_tracking_numbers)->toBe(['1ZPKG001', '1ZPKG002'])
        ->and($shipment->booked_by)->toBe($this->owner->id)
        ->and($shipment->documents()->where('type', 'label')->count())->toBe(2)
        ->and(ShippingAuditEntry::query()->where('action', 'booking.created')->exists())->toBeTrue();
    Storage::disk('local')->assertExists($shipment->documents()->first()->file_path);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/api/shipments/v2409/ship')) {
            return false;
        }
        $shipment = $request['ShipmentRequest']['Shipment'];

        return $shipment['Service']['Code'] === '65' && count($shipment['Package']) === 2 && $shipment['Shipper']['Phone']['Number'] === '+92 300 0000000' && $shipment['ShipTo']['Address']['ResidentialAddressIndicator'] === '';
    });

    $this->post(route('user.shipping.bookings.store', $shipment), ['confirm' => '1'])->assertSessionHasErrors('booking');
    $this->put(route('user.shipping.shipments.booking', $shipment), ['tracking_number' => 'OTHER123'])->assertSessionHasErrors('tracking_number');
    Http::assertSentCount(2);

    $document = $shipment->documents()->first();
    $this->get(route('user.shipping.documents.download', $document))->assertOk();
});

it('refuses to book when the account has API booking switched off', function () {
    $account = liveAccount($this->workspace->id, 'ups', ['booking_enabled' => false]);
    approveAccountRate($this, $account, '65');
    Http::fake();

    expect(fn () => app(BookingService::class)->book($this->shipment->refresh(), $this->owner, true))
        ->toThrow(ValidationException::class, 'API booking switched on');
    Http::assertNothingSent();
});

it('keeps the shipment unbooked and logs the reason when the courier refuses', function () {
    $account = liveAccount($this->workspace->id, 'ups');
    approveAccountRate($this, $account, '65');
    Http::fake([
        'wwwcie.ups.com/security/*' => Http::response(['access_token' => 'ups-token', 'expires_in' => 3600]),
        'wwwcie.ups.com/api/shipments/*' => Http::response(['response' => ['errors' => [['code' => '120100', 'message' => 'Missing or invalid shipper number']]]], 400),
    ]);

    $this->post(route('user.shipping.bookings.store', $this->shipment), ['confirm' => '1'])->assertSessionHasErrors('booking');

    expect($this->shipment->refresh()->status)->toBe('approved')
        ->and($this->shipment->tracking_number)->toBeNull()
        ->and(ShippingAuditEntry::query()->where('action', 'booking.failed')->value('summary'))->toContain('Missing or invalid shipper number');
});

it('books with FedEx and cancels only after the owner approves the request', function () {
    $account = liveAccount($this->workspace->id, 'fedex');
    approveAccountRate($this, $account, 'INTERNATIONAL_PRIORITY');
    $manager = teamMemberWithRole($this, 'Shipping Manager');

    Http::fake([
        'apis-sandbox.fedex.com/oauth/token' => Http::response(['access_token' => 'fx-token', 'expires_in' => 3600]),
        'apis-sandbox.fedex.com/ship/v1/shipments/cancel' => Http::response(['output' => ['cancelledShipment' => true]]),
        'apis-sandbox.fedex.com/ship/v1/shipments' => Http::response(['output' => ['transactionShipments' => [[
            'masterTrackingNumber' => '794600000001',
            'pieceResponses' => [
                ['trackingNumber' => '794600000001', 'packageDocuments' => [['contentType' => 'LABEL', 'docType' => 'PDF', 'encodedLabel' => base64_encode('%PDF-1 label1')]]],
                ['trackingNumber' => '794600000002', 'packageDocuments' => [['contentType' => 'LABEL', 'docType' => 'PDF', 'encodedLabel' => base64_encode('%PDF-1 label2')]]],
            ],
            'shipmentDocuments' => [['contentType' => 'COMMERCIAL_INVOICE', 'docType' => 'PDF', 'encodedLabel' => base64_encode('%PDF-1 invoice')]],
            'completedShipmentDetail' => ['shipmentRating' => ['shipmentRateDetails' => [['totalNetCharge' => 101.5, 'currency' => 'USD']]]],
        ]]]]),
    ]);

    $this->actingAs($manager)->post(route('user.shipping.bookings.store', $this->shipment), ['confirm' => '1'])->assertSessionHasNoErrors();
    $shipment = $this->shipment->refresh();

    expect($shipment->tracking_number)->toBe('794600000001')
        ->and($shipment->documents()->count())->toBe(3)
        ->and($shipment->documents()->where('type', 'commercial_invoice')->exists())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/ship/v1/shipments')
        && $request['requestedShipment']['serviceType'] === 'INTERNATIONAL_PRIORITY'
        && $request['requestedShipment']['customsClearanceDetail']['commodities'][0]['customsValue']['amount'] == 500);

    $this->actingAs($manager)->post(route('user.shipping.bookings.void-request', $shipment), ['reason' => 'Customer changed the address'])->assertSessionHasNoErrors();
    $this->actingAs($manager)->post(route('user.shipping.bookings.void', $shipment))->assertForbidden();
    expect($shipment->refresh()->status)->toBe('booked')
        ->and(SystemNotification::query()->where('notifiable_id', $this->owner->id)->where('type', 'shipping')->count())->toBe(1);

    $this->actingAs($this->owner)->post(route('user.shipping.bookings.void', $shipment))->assertSessionHasNoErrors();

    expect($shipment->refresh()->status)->toBe('cancelled')
        ->and($shipment->voided_by)->toBe($this->owner->id);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/ship/v1/shipments/cancel') && $request['trackingNumber'] === '794600000001');
});

it('books an Aramex waybill and cancels it only when the owner confirms it was cancelled with Aramex', function () {
    $account = liveAccount($this->workspace->id, 'aramex');
    approveAccountRate($this, $account, 'PPX');
    Http::fake(['ws.aramex.net/*' => Http::response(aramexXml(
        '<ShipmentCreationResponse xmlns="http://ws.aramex.net/ShippingAPI/v1/"><Notifications/><HasErrors>false</HasErrors><Shipments><ProcessedShipment>'
        .'<ID>44009988</ID><Reference1>'.$this->shipment->reference.'</Reference1><HasErrors>false</HasErrors><Notifications/>'
        .'<ShipmentLabel><LabelURL/><LabelFileContents>'.base64_encode('%PDF-1 waybill').'</LabelFileContents></ShipmentLabel>'
        .'</ProcessedShipment></Shipments></ShipmentCreationResponse>'
    ))]);

    $shipment = app(BookingService::class)->book($this->shipment->refresh(), $this->owner, true);

    expect($shipment->tracking_number)->toBe('44009988')
        ->and($shipment->documents()->value('mime'))->toBe('application/pdf');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/Shipping/Service_1_0.svc')
        && str_contains($request->body(), '<v1:ProductType>PPX</v1:ProductType>')
        && str_contains($request->body(), '<v1:PhoneNumber1>+92 300 0000000</v1:PhoneNumber1>'));

    app(BookingService::class)->requestVoid($shipment, $this->owner, 'Duplicate booking');
    expect(fn () => app(BookingService::class)->approveVoid($shipment->refresh(), $this->owner))
        ->toThrow(ValidationException::class, 'Aramex has no API call');

    app(BookingService::class)->approveVoid($shipment->refresh(), $this->owner, true);
    expect($shipment->refresh()->status)->toBe('cancelled');
    Http::assertSentCount(1);
});

it('keeps booking, tracking refresh and labels inside the workspace', function () {
    $other = User::factory()->create();
    app(WorkspaceResolver::class)->current($other);
    $document = app(ShippingDocumentService::class)->storeContent($this->shipment, 'label', 'label.pdf', '%PDF', 'application/pdf');

    $this->actingAs($other);
    $this->post(route('user.shipping.bookings.store', $this->shipment), ['confirm' => '1'])->assertNotFound();
    $this->post(route('user.shipping.tracking.refresh', $this->shipment))->assertNotFound();
    $this->get(route('user.shipping.documents.download', $document))->assertNotFound();
    expect(ShippingDocument::query()->count())->toBe(1);
});
