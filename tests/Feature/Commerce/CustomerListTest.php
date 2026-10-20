<?php

use App\Models\User;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->staff);
    StoreOrderSetting::forWorkspace($this->workspace->id);
    $this->actingAs($this->staff);
});

it('displays only storefront and mobile app customers and excludes generic contacts', function () {
    // 1. Create a generic contact who has never registered on storefront or mobile app
    $leadContact = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Marketing Lead Person',
        'phone' => '+15550009999',
        'email' => 'lead@example.com',
    ]);

    // 2. Create a storefront customer
    $storefrontContact = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Alice Storefront User',
        'phone' => '+15551112222',
        'email' => 'alice@storefront.test',
    ]);
    $storefrontCustomer = Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $storefrontContact->id,
        'verified_phone' => '+15551112222',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'storefront',
    ]);

    // 3. Create a mobile app customer
    $mobileContact = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Bob Mobile User',
        'phone' => '+15553334444',
        'email' => 'bob@mobile.test',
    ]);
    $mobileCustomer = Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $mobileContact->id,
        'verified_phone' => '+15553334444',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'mobile_app',
    ]);

    $response = $this->get(route('user.commerce.customers.index'))->assertOk();

    // Verify storefront customer appears with Storefront badge
    $response->assertSee('Alice Storefront User');
    $response->assertSee('Storefront');

    // Verify mobile app customer appears with Mobile App badge
    $response->assertSee('Bob Mobile User');
    $response->assertSee('Mobile App');

    // Verify generic marketing lead does NOT appear
    $response->assertDontSee('Marketing Lead Person');
});

it('filters customers by search query', function () {
    $contact1 = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'David Beckham',
        'phone' => '+15557778888',
        'email' => 'david@football.test',
    ]);
    Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $contact1->id,
        'verified_phone' => '+15557778888',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'storefront',
    ]);

    $contact2 = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Lionel Messi',
        'phone' => '+15559990000',
        'email' => 'lionel@football.test',
    ]);
    Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $contact2->id,
        'verified_phone' => '+15559990000',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'mobile_app',
    ]);

    // Search for David
    $searchResponse = $this->get(route('user.commerce.customers.index', ['search' => 'David']))->assertOk();
    $searchResponse->assertSee('David Beckham');
    $searchResponse->assertDontSee('Lionel Messi');

    // Search by phone
    $phoneResponse = $this->get(route('user.commerce.customers.index', ['search' => '9990000']))->assertOk();
    $phoneResponse->assertSee('Lionel Messi');
    $phoneResponse->assertDontSee('David Beckham');
});

it('shows customer details and isolation across workspaces', function () {
    $contact = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Sophia Loren',
        'phone' => '+15554445555',
        'email' => 'sophia@cinema.test',
    ]);
    $customer = Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $contact->id,
        'verified_phone' => '+15554445555',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'storefront',
    ]);

    CustomerAddress::query()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $customer->id,
        'label' => 'Home Villa',
        'is_default' => true,
        'address' => [
            'line1' => 'Via Veneto 10',
            'city' => 'Rome',
            'country' => 'IT',
        ],
    ]);

    $response = $this->get(route('user.commerce.customers.show', $customer))->assertOk();
    $response->assertSee('Sophia Loren');
    $response->assertSee('Via Veneto 10');
    $response->assertSee('Rome');

    // JSON endpoint for drawer
    $jsonResponse = $this->getJson(route('user.commerce.customers.show', $customer))->assertOk();
    $jsonResponse->assertJsonPath('customer.id', $customer->id);

    // Another workspace cannot access this customer
    $otherStaff = User::factory()->create();
    app(WorkspaceResolver::class)->current($otherStaff);
    $this->actingAs($otherStaff);

    $this->get(route('user.commerce.customers.show', $customer))->assertNotFound();
});
