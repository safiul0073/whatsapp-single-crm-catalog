<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\InvoiceLine;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingInvoice;
use App\Modules\Shipping\Services\InvoiceService;
use App\Modules\Shipping\Services\PaymentService;
use App\Modules\Shipping\Services\QuoteApprovalService;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\ShipmentService;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->actingAs($this->owner);
    $this->agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent A', 'carrier' => 'fedex', 'currency' => 'USD', 'exchange_rate' => '1']);

    $order = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'number' => 'ORD-'.fake()->unique()->numerify('######'),
        'provider_message_id' => fake()->uuid(),
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => '500.0000',
        'total' => '500.0000',
        'shipping_address' => ['name' => 'Jane Buyer', 'phone' => '+12025550147', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US'],
    ]);

    $ship = function (array $quote, string $tracking) use ($order): Shipment {
        $shipment = app(ShipmentService::class)->create($order);
        $approved = app(QuoteService::class)->store($shipment, $quote + ['currency' => 'USD', 'status' => 'verified']);
        app(QuoteApprovalService::class)->approve($shipment, $approved, $this->owner);

        return app(ShipmentService::class)->recordBooking($shipment->refresh(), ['tracking_number' => $tracking], $this->owner);
    };
    $this->direct = $ship(['source' => 'manual_direct', 'carrier' => 'ups', 'base_rate' => '100'], '1ZAAA');
    $this->viaAgent = $ship(['source' => 'manual_whatsapp', 'shipping_agent_id' => $this->agent->id, 'carrier' => 'fedex', 'base_rate' => '80'], '7946BBB');
});

function invoiceCsv(string ...$rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('invoice.csv', implode("\n", array_merge(['AWB,Date,Weight,Freight,Fuel,Remote Area,Total'], $rows)));
}

function uploadAgentInvoice(object $test, string $number, UploadedFile $file): ShippingInvoice
{
    $test->post(route('user.shipping.invoices.store'), [
        'provider' => 'agent:'.$test->agent->id,
        'invoice_number' => $number,
        'invoice_date' => '2026-11-12',
        'due_date' => now()->addDays(10)->toDateString(),
        'currency' => 'USD',
        'file' => $file,
    ])->assertSessionHasNoErrors();

    return ShippingInvoice::query()->where('invoice_number', $number)->firstOrFail();
}

it('imports an agent invoice, reconciles it by tracking number and flags the overcharge', function () {
    $invoice = uploadAgentInvoice($this, 'INV-1', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,8.00,15.00,93.00', 'UNKNOWN999,2026-11-10,3,20.00,,,20.00'));
    $viaAgent = $this->viaAgent->refresh();
    $lines = $invoice->lines()->get();

    expect($invoice->status)->toBe('needs_review')
        ->and($invoice->lines_total)->toBe('113.0000')
        ->and($lines[0]->match_status)->toBe('matched')
        ->and($lines[0]->shipping_shipment_id)->toBe($viaAgent->id)
        ->and($lines[0]->ship_date->toDateString())->toBe('2026-11-10')
        ->and($lines[1]->match_status)->toBe('unmatched')
        ->and($viaAgent->final_cost)->toBe('93.0000')
        ->and($viaAgent->needs_review)->toBeTrue()
        ->and($viaAgent->charges->pluck('amount', 'reason')->map(fn ($amount) => (float) $amount)->all())->toEqual(['fuel' => 8.0, 'remote_area' => 15.0])
        ->and(SystemNotification::query()->where('notifiable_id', $this->owner->id)->where('type', 'shipping')->count())->toBe(1);

    $this->put(route('user.shipping.invoices.lines.resolve', $lines[1]), ['action' => 'link', 'shipment_id' => $this->direct->id])->assertSessionHasNoErrors();
    expect($this->direct->refresh()->final_cost)->toBe('20.0000')
        ->and($this->direct->needs_review)->toBeFalse()
        ->and($lines[1]->refresh()->manually_linked)->toBeTrue();

    app(ShipmentService::class)->resolveReview($viaAgent, $this->owner);
    $this->post(route('user.shipping.invoices.reconcile', $invoice))->assertSessionHasNoErrors();

    expect($invoice->refresh()->status)->toBe('reconciled')
        ->and($viaAgent->refresh()->needs_review)->toBeFalse()
        ->and(SystemNotification::query()->where('notifiable_id', $this->owner->id)->where('type', 'shipping')->count())->toBe(1);
});

it('catches a repeated charge on a later invoice and a repeated invoice number', function () {
    uploadAgentInvoice($this, 'INV-1', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00'));
    $second = uploadAgentInvoice($this, 'INV-2', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00', '7946BBB,2026-11-11,,,,,12.50'));

    $lines = $second->lines()->get();
    expect($lines[0]->match_status)->toBe('duplicate')
        ->and($lines[1]->match_status)->toBe('matched')
        ->and($second->status)->toBe('needs_review')
        ->and($this->viaAgent->refresh()->final_cost)->toBe('82.5000');

    $this->post(route('user.shipping.invoices.store'), ['provider' => 'agent:'.$this->agent->id, 'invoice_number' => 'INV-1', 'currency' => 'USD'])
        ->assertSessionHasErrors('invoice_number');
});

it('records payments against invoices and keeps shipment, invoice and agent balances in step', function () {
    $invoice = uploadAgentInvoice($this, 'INV-1', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00', '1ZAAA,2026-11-10,3,30.00,,,30.00'));
    $payments = app(PaymentService::class);
    $pay = fn (string $amount) => $this->post(route('user.shipping.payments.store'), [
        'provider' => 'agent:'.$this->agent->id, 'shipping_invoice_id' => $invoice->id, 'amount' => $amount, 'currency' => 'USD',
        'paid_on' => today()->toDateString(), 'method' => 'bank', 'reference' => 'TRX-'.$amount,
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
    ]);

    $pay('60')->assertSessionHasNoErrors();
    expect($invoice->refresh()->payment_status)->toBe('partial')
        ->and($this->viaAgent->refresh()->payment_status)->toBe('partial')
        ->and($this->agent->refresh()->payment_status)->toBe('due')
        ->and($payments->balance($this->agent))->toBe('40.00');

    $pay('50')->assertSessionHasErrors('amount');
    $pay('40')->assertSessionHasNoErrors();

    expect($invoice->refresh()->payment_status)->toBe('paid')
        ->and($this->viaAgent->refresh()->payment_status)->toBe('paid')
        ->and($this->direct->refresh()->payment_status)->toBe('paid')
        ->and($this->agent->refresh()->payment_status)->toBe('clear');

    $statement = $payments->statement($this->agent, now()->subMonth(), now()->addDay());
    expect($statement['entries'])->toHaveCount(3)
        ->and($statement['billed'])->toBe('100.00')
        ->and($statement['paid'])->toBe('100.00')
        ->and($statement['closing'])->toBe('0.00');

    $this->get(route('user.shipping.payments.receipt', $invoice->payments()->first()))->assertOk();
    expect(fn () => app(InvoiceService::class)->delete($invoice, $this->owner))->toThrow(ValidationException::class);
});

it('marks an agent overdue when an unpaid invoice passes its due date', function () {
    $invoice = uploadAgentInvoice($this, 'INV-9', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00'));
    $invoice->update(['due_date' => now()->subDay()]);
    app(PaymentService::class)->refreshAgentStatus($this->agent);

    expect($this->agent->refresh()->payment_status)->toBe('overdue')
        ->and($invoice->refresh()->isOverdue())->toBeTrue();
});

it('lets Accounts reconcile and pay but keeps the Sales Team and other stores out', function () {
    $invoice = uploadAgentInvoice($this, 'INV-1', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00'));
    $role = fn (string $name): User => tap(User::factory()->create(), fn (User $user) => $this->workspace->members()->attach($user->id, [
        'workspace_role_id' => WorkspaceRole::query()->where('workspace_id', $this->workspace->id)->where('name', $name)->value('id'),
        'status' => WorkspaceMemberStatus::Active->value,
    ]));
    $accounts = $role('Accounts');
    $sales = $role('Sales Team');

    $this->actingAs($accounts)->get(route('user.shipping.invoices.show', $invoice))->assertOk();
    $this->actingAs($accounts)->post(route('user.shipping.invoices.reconcile', $invoice))->assertSessionHasNoErrors();
    $this->actingAs($accounts)->post(route('user.shipping.payments.store'), ['provider' => 'agent:'.$this->agent->id, 'amount' => '10', 'currency' => 'USD', 'paid_on' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasNoErrors();
    $this->actingAs($accounts)->post(route('user.shipping.bookings.store', $this->direct), ['confirm' => '1'])->assertForbidden();

    $this->actingAs($sales)->get(route('user.shipping.invoices.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('user.shipping.payments.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('user.shipping.shipments.show', $this->viaAgent))->assertOk()->assertDontSee('Book shipment and get label');

    $stranger = User::factory()->create();
    app(WorkspaceResolver::class)->current($stranger);
    $this->actingAs($stranger)->get(route('user.shipping.invoices.show', $invoice))->assertNotFound();
    $this->actingAs($stranger)->put(route('user.shipping.invoices.lines.resolve', InvoiceLine::query()->first()), ['action' => 'ignore'])->assertNotFound();
});

it('renders the invoice, payment and statement screens', function () {
    $invoice = uploadAgentInvoice($this, 'INV-1', invoiceCsv('7946BBB,2026-11-10,12.5,70.00,,,70.00', 'NOPE,2026-11-10,1,5.00,,,5.00'));
    $pdf = $this->post(route('user.shipping.invoices.store'), ['provider' => 'carrier:ups', 'invoice_number' => 'UPS-77', 'currency' => 'USD', 'stated_total' => '30', 'file' => UploadedFile::fake()->create('ups.pdf', 40, 'application/pdf')]);
    $pdf->assertSessionHasNoErrors();
    $pdfInvoice = ShippingInvoice::query()->where('invoice_number', 'UPS-77')->firstOrFail();
    $this->post(route('user.shipping.invoices.lines.store', $pdfInvoice), ['tracking_number' => '1ZAAA', 'base_amount' => '25', 'charges' => ['fuel' => '5']])->assertSessionHasNoErrors();
    $this->post(route('user.shipping.invoices.reconcile', $pdfInvoice))->assertSessionHasNoErrors();

    expect($pdfInvoice->refresh()->lines_total)->toBe('30.0000')
        ->and($pdfInvoice->status)->toBe('reconciled')
        ->and($this->direct->refresh()->final_cost)->toBe('30.0000');

    foreach ([
        route('user.shipping.invoices.index'),
        route('user.shipping.invoices.index', ['filter' => 'needs_review', 'search' => '7946']),
        route('user.shipping.invoices.create'),
        route('user.shipping.invoices.show', $invoice),
        route('user.shipping.invoices.show', $pdfInvoice),
        route('user.shipping.invoices.file', $pdfInvoice),
        route('user.shipping.payments.index', ['invoice' => $invoice->id]),
        route('user.shipping.payments.statement', $this->agent),
        route('user.shipping.shipments.show', $this->viaAgent),
        route('user.shipping.overview'),
        route('user.shipping.carriers.create'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('matches a hand-entered line straight away and keeps the agent status in step', function () {
    $this->post(route('user.shipping.invoices.store'), ['provider' => 'agent:'.$this->agent->id, 'invoice_number' => 'WA-9', 'currency' => 'USD'])->assertSessionHasNoErrors();
    $invoice = ShippingInvoice::query()->where('invoice_number', 'WA-9')->firstOrFail();

    $this->post(route('user.shipping.invoices.lines.store', $invoice), ['tracking_number' => '7946BBB', 'base_amount' => '80'])->assertSessionHasNoErrors();

    expect($invoice->lines()->first()->match_status)->toBe('matched')
        ->and($invoice->refresh()->status)->toBe('reconciled')
        ->and($this->agent->refresh()->payment_status)->toBe('due');

    $this->get(route('user.shipping.payments.index', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee('value="agent:'.$this->agent->id.'" selected', false)
        ->assertSee('name="amount" required value="80.00"', false)
        ->assertSee('name="currency" maxlength="3" required value="USD"', false);

    $this->delete(route('user.shipping.invoices.lines.destroy', $invoice->lines()->first()))->assertSessionHasNoErrors();
    expect($this->agent->refresh()->payment_status)->toBe('clear');
});
