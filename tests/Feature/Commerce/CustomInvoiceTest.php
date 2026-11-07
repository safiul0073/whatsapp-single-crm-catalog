<?php

use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\InvoiceRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\CustomInvoiceService;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'storefront.platform_secret' => str_repeat('p', 40), 'cache.default' => 'array', 'app.url' => 'https://app.example.test']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();

    $this->store = Workspace::query()->create(['name' => 'One', 'slug' => 'one', 'status' => 'active', 'settings' => ['commerce' => ['currency' => 'USD']]]);
    $this->other = Workspace::query()->create(['name' => 'Two', 'slug' => 'two', 'status' => 'active']);
    foreach ([$this->store, $this->other] as $w) {
        StoreDomain::query()->create(['workspace_id' => $w->id, 'host' => $w->slug.'.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    }
    $this->headers = ['X-Storefront-Secret' => str_repeat('p', 40), 'X-Store-Host' => 'one.localhost'];

    $makeCustomer = function (Workspace $workspace, string $phone) {
        $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Customer '.$phone, 'phone' => $phone, 'source' => 'website', 'opt_in_status' => 'unknown']));

        return app(CustomerService::class)->enroll($contact, true);
    };
    $this->customer = $makeCustomer($this->store, '+8801700000000');
    $this->otherCustomer = $makeCustomer($this->store, '+8801700000001');
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken];

    $this->product = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'shirt', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'shirt-m', 'meta_retailer_id' => 'shirt-m', 'size' => 'M', 'price' => '22.50', 'stock_quantity' => 5, 'status' => 'active']);
    $this->foreignProduct = Product::query()->create(['workspace_id' => $this->other->id, 'name' => 'Other shirt', 'slug' => 'other', 'sku' => 'other', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 5]);

    $this->service = app(CustomInvoiceService::class);
    $this->makeInvoice = fn (array $overrides = []): CustomInvoice => $this->service->save($this->store, array_merge([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
            ['name' => 'Custom embroidery', 'quantity' => 3, 'unit_price' => '1.10'],
        ],
    ], $overrides));
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('builds an invoice from product and free-text lines with exact totals', function (): void {
    $invoice = ($this->makeInvoice)([
        'items' => [
            ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 2],
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '18', 'name' => 'Shirt (sample price)'],
            ['name' => 'Custom embroidery', 'quantity' => 3, 'unit_price' => '0.10'],
        ],
        'discount_amount' => '5.30',
        'tax_amount' => '2.00',
        'shipping_amount' => '4',
    ]);

    expect($invoice->number)->toBe('CI-00001')
        ->and($invoice->status)->toBe(CustomInvoice::DRAFT)
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->bill_to_name)->toBe('Customer +8801700000000')
        ->and($invoice->subtotal)->toBe('63.3000')
        ->and($invoice->total)->toBe('64.0000')
        ->and($invoice->token)->toHaveLength(40);

    expect($invoice->items->pluck('name')->all())->toBe(['Shirt', 'Shirt (sample price)', 'Custom embroidery'])
        ->and($invoice->items[0]->unit_price)->toBe('22.5000')
        ->and($invoice->items[2]->line_total)->toBe('0.3000')
        ->and($invoice->items[2]->product_id)->toBeNull();

    expect(($this->makeInvoice)()->number)->toBe('CI-00002');
});

it('rejects products from another store, missing prices and oversized discounts', function (array $overrides, string $field): void {
    try {
        ($this->makeInvoice)($overrides);
        $this->fail('Expected validation to fail.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'foreign product' => [fn () => ['items' => [['product_id' => test()->foreignProduct->id, 'quantity' => 1]]], 'items.0.product_id'],
    'free text without price' => [['items' => [['name' => 'Consulting', 'quantity' => 1]]], 'items.0.unit_price'],
    'no lines' => [['items' => []], 'items'],
    'discount too large' => [['items' => [['name' => 'Fee', 'quantity' => 1, 'unit_price' => '10']], 'discount_amount' => '11'], 'discount_amount'],
]);

it('only allows edits on drafts and enforces status transitions', function (): void {
    $invoice = ($this->makeInvoice)(['issue' => true]);
    expect($invoice->status)->toBe(CustomInvoice::SENT)->and($invoice->issued_at)->not->toBeNull();

    expect(fn () => $this->service->save($this->store, ['items' => [['name' => 'x', 'quantity' => 1, 'unit_price' => '1']], 'bill_to_name' => 'X'], $invoice))
        ->toThrow(ValidationException::class);

    $this->service->transition($invoice, CustomInvoice::PAID);
    expect($invoice->fresh()->paid_at)->not->toBeNull();

    $this->service->transition($invoice, CustomInvoice::SENT);
    expect($invoice->fresh()->paid_at)->toBeNull();

    $this->service->transition($invoice, CustomInvoice::CANCELLED);
    expect(fn () => $this->service->transition($invoice, CustomInvoice::SENT))->toThrow(ValidationException::class);
});

it('renders a custom invoice pdf and serves it on the private link only once issued', function (): void {
    $invoice = ($this->makeInvoice)();

    expect(app(InvoiceService::class)->customPdf($invoice))->toStartWith('%PDF-1.4')->toContain('CI-00001');
    $this->get('/custom-invoices/'.$invoice->token)->assertNotFound();

    $this->service->transition($invoice, CustomInvoice::SENT);
    $this->get('/custom-invoices/'.$invoice->token)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get('/custom-invoices/'.$invoice->token.'?download=1')->assertHeader('Content-Disposition', 'attachment; filename="CI-00001.pdf"');

    $old = $invoice->token;
    $this->service->regenerateToken($invoice);
    $this->get('/custom-invoices/'.$old)->assertNotFound();
});

it('shows customers only their own issued invoices', function (): void {
    $draft = ($this->makeInvoice)();
    $issued = ($this->makeInvoice)(['issue' => true]);
    $someoneElse = ($this->makeInvoice)(['customer_id' => $this->otherCustomer->id, 'issue' => true]);

    $this->getJson('/api/storefront/v1/me/invoices', $this->auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', $issued->number)
        ->assertJsonPath('data.0.total', '43.30');

    $this->getJson('/api/storefront/v1/me/invoices/'.$issued->number, $this->auth)
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.1.name', 'Custom embroidery');
    $this->getJson('/api/storefront/v1/me/invoices/'.$draft->number, $this->auth)->assertNotFound();
    $this->getJson('/api/storefront/v1/me/invoices/'.$someoneElse->number, $this->auth)->assertNotFound();

    $this->get('/api/storefront/v1/me/invoices/'.$issued->number.'/pdf', $this->auth)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->getJson('/api/storefront/v1/me/invoices', $this->headers)->assertUnauthorized();
});

it('lets a customer request an invoice that the owner turns into one', function (): void {
    $response = $this->postJson('/api/storefront/v1/me/invoice-requests', [
        'message' => 'Please invoice 10 shirts with our logo for the office.',
        'items' => [['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 10, 'note' => 'Logo on chest']],
    ], $this->auth)->assertCreated()->assertJsonPath('data.status', 'pending');

    $this->postJson('/api/storefront/v1/me/invoice-requests', ['message' => 'Bad product please', 'items' => [['product_id' => $this->foreignProduct->id, 'quantity' => 1]]], $this->auth)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');
    $this->postJson('/api/storefront/v1/me/invoice-requests', ['message' => ''], $this->auth)->assertUnprocessable()->assertJsonValidationErrors('message');

    $request = InvoiceRequest::query()->findOrFail($response->json('data.id'));
    $draft = $this->service->draftFromRequest($request);
    expect($draft['customer_id'])->toBe($this->customer->id)
        ->and($draft['items'][0])->toMatchArray(['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 10, 'unit_price' => '22.50', 'description' => 'Logo on chest']);

    $invoice = $this->service->save($this->store, $draft + ['issue' => true]);
    expect($request->fresh()->status)->toBe(InvoiceRequest::INVOICED)->and($invoice->total)->toBe('225.0000');

    $this->getJson('/api/storefront/v1/me/invoice-requests', $this->auth)
        ->assertOk()
        ->assertJsonPath('data.0.status', 'invoiced')
        ->assertJsonPath('data.0.invoice_number', $invoice->number);
});

it('lets the owner decline a request once and caps open requests', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->service->createRequest($this->customer, ['message' => 'Request number '.$i]);
    }
    $this->postJson('/api/storefront/v1/me/invoice-requests', ['message' => 'One request too many'], $this->auth)->assertUnprocessable();

    $request = InvoiceRequest::query()->firstOrFail();
    $this->service->rejectRequest($request, 'We do not offer this.');
    expect($request->fresh()->status)->toBe(InvoiceRequest::REJECTED)->and($request->fresh()->owner_note)->toBe('We do not offer this.');
    expect(fn () => $this->service->rejectRequest($request->fresh(), null))->toThrow(ValidationException::class);
});
