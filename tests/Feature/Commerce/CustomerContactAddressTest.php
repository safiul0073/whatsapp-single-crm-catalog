<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('displays customer contact address on order detail page and allows updating it', function (): void {
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $user->givePermissionTo(['commerce.view', 'commerce.manage']);

    $contact = Contact::withoutEvents(fn () => Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Original Customer',
        'phone' => '+8801876150613',
        'email' => 'original@example.com',
        'source' => 'website',
        'opt_in_status' => 'unknown',
        'city' => 'Dhaka',
        'country' => 'BD',
        'custom_fields' => [
            'address' => [
                'line1' => 'Road 10, House 25',
                'line2' => 'Flat 4B',
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'postal_code' => '1212',
                'country' => 'BD',
            ],
        ],
    ]));

    $customer = Customer::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'phone' => $contact->phone,
        'verified_at' => now(),
    ]);

    CustomerAddress::query()->create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'label' => 'Home',
        'is_default' => true,
        'address' => [
            'name' => 'Original Customer',
            'phone' => '+8801876150613',
            'line1' => 'Road 10, House 25',
            'line2' => 'Flat 4B',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postal_code' => '1212',
            'country' => 'BD',
        ],
    ]);

    $order = Order::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'number' => 'ORD-00001',
        'provider_message_id' => fake()->uuid(),
        'status' => 'requested',
        'shipping_address' => [
            'name' => 'Original Customer',
            'phone' => '+8801876150613',
            'line1' => 'Delivery Address 1',
            'city' => 'Dhaka',
            'country' => 'BD',
        ],
    ]);

    // Check that customer contact details and address are rendered
    $this->actingAs($user)->get(route('user.commerce.orders.show', $order))
        ->assertOk()
        ->assertSee('Original Customer')
        ->assertSee('+8801876150613')
        ->assertSee('original@example.com')
        ->assertSee('Contact Address')
        ->assertSee('Road 10, House 25')
        ->assertSee('Flat 4B')
        ->assertSee('Edit Contact');

    // Update customer contact details
    $this->actingAs($user)->put(route('user.commerce.orders.customer.update', $order), [
        'name' => 'Updated Customer',
        'phone' => '+8801899999999',
        'email' => 'updated@example.com',
        'address_line1' => 'Banani Block D, Road 11',
        'address_line2' => 'Suite 500',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'postal_code' => '1213',
        'country' => 'BD',
    ])->assertRedirect();

    $contact->refresh();
    expect($contact->name)->toBe('Updated Customer')
        ->and($contact->phone)->toBe('+8801899999999')
        ->and($contact->email)->toBe('updated@example.com')
        ->and($contact->custom_fields['address']['line1'])->toBe('Banani Block D, Road 11')
        ->and($contact->custom_fields['address']['line2'])->toBe('Suite 500');

    $order->refresh();
    expect($order->customer_snapshot['name'])->toBe('Updated Customer')
        ->and($order->customer_snapshot['phone'])->toBe('+8801899999999')
        ->and($order->customer_snapshot['email'])->toBe('updated@example.com')
        ->and($order->customer_snapshot['address']['line1'])->toBe('Banani Block D, Road 11');

    $defaultAddress = CustomerAddress::query()->where('customer_id', $customer->id)->where('is_default', true)->first();
    expect($defaultAddress)->not->toBeNull()
        ->and($defaultAddress->address['line1'])->toBe('Banani Block D, Road 11');
});

it('allows storefront customers to get and update their primary contact address', function (): void {
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);

    $contact = Contact::withoutEvents(fn () => Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Storefront Customer',
        'phone' => '+8801712345678',
        'email' => 'customer@example.com',
        'source' => 'website',
        'opt_in_status' => 'unknown',
    ]));

    $customer = Customer::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'phone' => $contact->phone,
        'verified_at' => now(),
    ]);

    $service = app(CustomerService::class);
    $profile = $service->profile($customer);
    expect($profile['name'])->toBe('Storefront Customer')
        ->and($profile['email'])->toBe('customer@example.com')
        ->and($profile['address'])->toBeNull();

    // Customer updates profile with address
    $service->updateProfile($customer, [
        'name' => 'Storefront Customer Updated',
        'email' => 'newemail@example.com',
        'address' => [
            'line1' => 'Gulshan 2, Road 71',
            'line2' => 'House 14',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postal_code' => '1212',
            'country' => 'BD',
        ],
    ]);

    $contact->refresh();
    expect($contact->name)->toBe('Storefront Customer Updated')
        ->and($contact->email)->toBe('newemail@example.com')
        ->and($contact->custom_fields['address']['line1'])->toBe('Gulshan 2, Road 71')
        ->and($contact->city)->toBe('Dhaka')
        ->and($contact->country)->toBe('BD');

    $updatedProfile = $service->profile($customer);
    expect($updatedProfile['address']['line1'])->toBe('Gulshan 2, Road 71')
        ->and($updatedProfile['address']['city'])->toBe('Dhaka');

    $defaultAddress = CustomerAddress::query()->where('customer_id', $customer->id)->where('is_default', true)->first();
    expect($defaultAddress)->not->toBeNull()
        ->and($defaultAddress->address['line1'])->toBe('Gulshan 2, Road 71');
});
