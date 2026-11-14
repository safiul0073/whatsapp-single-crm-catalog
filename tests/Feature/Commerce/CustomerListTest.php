<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

it('formats customer order lifetime spend with dollar sign instead of usd', function () {
    $contact = Contact::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Md Safiullah',
        'phone' => '+8801876150613',
        'email' => 'mdsafiul0073@gmail.com',
    ]);
    Customer::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $contact->id,
        'verified_phone' => '+8801876150613',
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'storefront',
    ]);

    Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $contact->id,
        'number' => 'ORD-1001',
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 1662.44,
        'total' => 1662.44,
    ]);

    $response = $this->get(route('user.commerce.customers.index'))->assertOk();

    $response->assertSee('$1,662.44');
    $response->assertDontSee('USD 1,662.44');
});

function storeCustomerFixture(int $workspaceId, string $name, string $phone, ?string $email = null): Customer
{
    $contact = Contact::query()->create([
        'workspace_id' => $workspaceId,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
    ]);

    return Customer::query()->create([
        'workspace_id' => $workspaceId,
        'contact_id' => $contact->id,
        'verified_phone' => $phone,
        'verified_at' => now(),
        'registered_at' => now(),
        'source' => 'storefront',
    ]);
}

it('returns only matching rows for instant search requests', function () {
    storeCustomerFixture($this->workspace->id, 'Grace Hopper', '+15550001111', 'grace@navy.test');
    storeCustomerFixture($this->workspace->id, 'Alan Turing', '+15550002222', 'alan@bletchley.test');

    $response = $this->getJson(route('user.commerce.customers.index', ['search' => 'bletchley']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('total', 1);

    expect($response->json('html'))->toContain('Alan Turing')->not->toContain('Grace Hopper')
        ->and($response->json('html'))->not->toContain('<html');

    $this->getJson(route('user.commerce.customers.index', ['search' => '%']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('adds a customer with an optional password and default address', function () {
    $this->post(route('user.commerce.customers.store'), [
        'name' => 'Ada Lovelace',
        'phone' => '+15550003333',
        'email' => 'Ada@Example.test',
        'address' => ['line1' => '12 St James Square', 'city' => 'London', 'country' => 'gb'],
    ])->assertRedirect(route('user.commerce.customers.index'))->assertSessionHasNoErrors();

    $customer = Customer::query()->with(['contact', 'addresses'])->where('workspace_id', $this->workspace->id)->sole();
    expect($customer->source)->toBe('admin')
        ->and($customer->password)->toBeNull()
        ->and($customer->verified_at)->toBeNull()
        ->and($customer->contact->name)->toBe('Ada Lovelace')
        ->and($customer->contact->email)->toBe('ada@example.test')
        ->and($customer->addresses)->toHaveCount(1)
        ->and($customer->addresses->first()->is_default)->toBeTrue()
        ->and($customer->addresses->first()->address['country'])->toBe('GB');

    $this->post(route('user.commerce.customers.store'), [
        'name' => 'Charles Babbage',
        'phone' => '+15550004444',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ])->assertSessionHasNoErrors();

    $withPassword = Customer::query()->whereHas('contact', fn ($query) => $query->where('phone', '+15550004444'))->sole();
    expect(Hash::check('secret-pass-1', $withPassword->password))->toBeTrue()
        ->and($withPassword->addresses()->count())->toBe(0);
});

it('links an existing contact but rejects a phone that already belongs to a customer', function () {
    $lead = Contact::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Existing Lead', 'phone' => '+15550005555']);

    $this->post(route('user.commerce.customers.store'), ['name' => 'Existing Lead', 'phone' => '+15550005555'])->assertSessionHasNoErrors();
    expect(Customer::query()->where('contact_id', $lead->id)->exists())->toBeTrue()
        ->and(Contact::query()->where('phone', '+15550005555')->count())->toBe(1);

    $this->post(route('user.commerce.customers.store'), ['name' => 'Duplicate', 'phone' => '+15550005555'])
        ->assertSessionHasErrorsIn('customerForm', 'phone');
    expect(Customer::query()->where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

it('updates a customer, keeps the password when blank and clears verification on phone change', function () {
    $customer = storeCustomerFixture($this->workspace->id, 'Old Name', '+15550006666', 'old@example.test');
    $customer->update(['password' => 'original-pass']);
    $hash = $customer->fresh()->password;

    $this->put(route('user.commerce.customers.update', $customer), [
        'name' => 'New Name',
        'phone' => '+15550006666',
        'email' => 'new@example.test',
        'address' => ['line1' => '1 Main St', 'city' => 'Austin', 'country' => 'US'],
    ])->assertSessionHasNoErrors();

    $customer->refresh()->load(['contact', 'addresses']);
    expect($customer->contact->name)->toBe('New Name')
        ->and($customer->contact->email)->toBe('new@example.test')
        ->and($customer->password)->toBe($hash)
        ->and($customer->verified_at)->not->toBeNull()
        ->and($customer->addresses->sole()->address['line1'])->toBe('1 Main St');

    $this->put(route('user.commerce.customers.update', $customer), [
        'name' => 'New Name',
        'phone' => '+15550007777',
        'password' => 'changed-pass-1',
        'password_confirmation' => 'changed-pass-1',
    ])->assertSessionHasNoErrors();

    $customer->refresh();
    expect($customer->contact->phone)->toBe('+15550007777')
        ->and($customer->verified_phone)->toBeNull()
        ->and($customer->verified_at)->toBeNull()
        ->and(Hash::check('changed-pass-1', $customer->password))->toBeTrue();
});

it('deletes only the customer account and keeps the contact and orders', function () {
    $customer = storeCustomerFixture($this->workspace->id, 'Leaving Customer', '+15550008888');
    CustomerAddress::query()->create(['workspace_id' => $this->workspace->id, 'customer_id' => $customer->id, 'label' => 'Home', 'is_default' => true, 'address' => ['line1' => 'x', 'city' => 'y']]);
    $order = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $customer->contact_id,
        'number' => 'ORD-2001',
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 10,
        'total' => 10,
    ]);

    $this->delete(route('user.commerce.customers.destroy', $customer))->assertRedirect(route('user.commerce.customers.index'));

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeFalse()
        ->and(CustomerAddress::query()->where('customer_id', $customer->id)->exists())->toBeFalse()
        ->and(Contact::query()->whereKey($customer->contact_id)->exists())->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('protects customer management by workspace and permission', function () {
    $customer = storeCustomerFixture($this->workspace->id, 'Protected Customer', '+15550009999');

    $otherStaff = User::factory()->create();
    app(WorkspaceResolver::class)->current($otherStaff);
    $this->actingAs($otherStaff);
    $this->put(route('user.commerce.customers.update', $customer), ['name' => 'Hijack', 'phone' => '+15550001234'])->assertNotFound();
    $this->delete(route('user.commerce.customers.destroy', $customer))->assertNotFound();

    $member = User::factory()->create();
    $this->workspace->members()->attach($member, ['status' => 'active']);
    $this->actingAs($member)->withSession(['active_workspace_id' => $this->workspace->id]);
    $this->post(route('user.commerce.customers.store'), ['name' => 'Nope', 'phone' => '+15550004321'])->assertForbidden();
    $this->delete(route('user.commerce.customers.destroy', $customer))->assertForbidden();

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeTrue();
});
