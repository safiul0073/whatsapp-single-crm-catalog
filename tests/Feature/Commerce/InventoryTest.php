<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyLowStock;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function inventoryVariant(int $workspaceId, string $sku, int $stock, string $productName = 'Tracksuit'): ProductVariant
{
    $product = Product::query()->firstOrCreate(['workspace_id' => $workspaceId, 'slug' => Str::slug($productName)], [
        'name' => $productName, 'sku' => Str::slug($productName), 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20,
    ]);

    return ProductVariant::query()->create([
        'workspace_id' => $workspaceId, 'product_id' => $product->id, 'sku' => $sku, 'meta_retailer_id' => $sku,
        'size' => 'M', 'attributes' => ['size' => 'M'], 'price' => 20, 'stock_quantity' => $stock, 'status' => 'active',
    ]);
}

beforeEach(function (): void {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    StoreOrderSetting::forWorkspace($this->workspace->id)->update(['low_stock_threshold' => 3, 'low_stock_alerts_enabled' => true]);
    $this->actingAs($this->owner);
});

it('shows out, low and in-stock variants with summary counts', function (): void {
    inventoryVariant($this->workspace->id, 'ts-out', 0);
    inventoryVariant($this->workspace->id, 'ts-low', 2);
    inventoryVariant($this->workspace->id, 'ts-in', 10);
    $other = app(WorkspaceResolver::class)->current(User::factory()->create());
    inventoryVariant($other->id, 'foreign-sku', 0, 'Foreign');

    $this->get(route('user.commerce.inventory.index'))
        ->assertOk()
        ->assertSeeInOrder(['ts-out', 'ts-low', 'ts-in'])
        ->assertDontSee('foreign-sku')
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 3 && $summary['out'] === 1 && $summary['low'] === 1 && $summary['in'] === 1 && $summary['units'] === 12);

    $this->get(route('user.commerce.inventory.index', ['status' => 'low']))->assertSee('ts-low')->assertDontSee('ts-out')->assertDontSee('ts-in');
    $this->get(route('user.commerce.inventory.index', ['status' => 'out']))->assertSee('ts-out')->assertDontSee('ts-low');
    $this->get(route('user.commerce.inventory.index', ['q' => 'ts-in']))->assertSee('ts-in')->assertDontSee('ts-out');
});

it('sets and adds stock and records each manual change once', function (): void {
    $variant = inventoryVariant($this->workspace->id, 'ts-m', 5);
    $key = (string) Str::uuid();

    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'set', 'quantity' => 12, 'reason' => 'correction', 'idempotency_key' => $key])
        ->assertOk()->assertJson(['stock_quantity' => 12, 'status' => 'in', 'summary' => ['units' => 12]]);
    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'set', 'quantity' => 12, 'reason' => 'correction', 'idempotency_key' => $key])->assertOk();
    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 8, 'reason' => 'restock', 'note' => 'Supplier batch', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJson(['stock_quantity' => 20]);

    $movements = InventoryMovement::query()->where('variant_id', $variant->id)->orderBy('id')->get();
    expect($variant->fresh()->stock_quantity)->toBe(20)
        ->and($movements)->toHaveCount(2)
        ->and($movements[0]->reason)->toBe('manual_adjustment')
        ->and($movements[0]->metadata)->toMatchArray(['type' => 'correction', 'before' => 5, 'after' => 12, 'user_id' => $this->owner->id])
        ->and($movements[1]->quantity_delta)->toBe(8)
        ->and($movements[1]->metadata['note'])->toBe('Supplier batch');
});

it('rejects stock below zero and variants from another store', function (): void {
    $variant = inventoryVariant($this->workspace->id, 'ts-m', 2);
    $foreign = inventoryVariant(app(WorkspaceResolver::class)->current(User::factory()->create())->id, 'foreign', 5, 'Foreign');

    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => -3, 'reason' => 'damaged', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'set', 'quantity' => -1, 'reason' => 'correction', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->patchJson(route('user.commerce.inventory.adjust', $foreign), ['mode' => 'set', 'quantity' => 1, 'reason' => 'correction', 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
    $this->getJson(route('user.commerce.inventory.history', $foreign))->assertNotFound();

    expect($variant->fresh()->stock_quantity)->toBe(2)->and($foreign->fresh()->stock_quantity)->toBe(5);
});

it('raises the low-stock alert on a manual drop and clears it on restock', function (): void {
    $variant = inventoryVariant($this->workspace->id, 'ts-m', 6);

    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => -4, 'reason' => 'damaged', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJson(['status' => 'low']);
    Queue::assertPushed(NotifyLowStock::class, 1);
    expect($variant->fresh()->low_stock_notified_at)->not->toBeNull();

    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 10, 'reason' => 'restock', 'idempotency_key' => (string) Str::uuid()])->assertOk();
    expect($variant->fresh()->low_stock_notified_at)->toBeNull();
});

it('lists stock history newest first without reservation rows', function (): void {
    $variant = inventoryVariant($this->workspace->id, 'ts-m', 5);
    InventoryMovement::query()->create(['workspace_id' => $this->workspace->id, 'variant_id' => $variant->id, 'quantity_delta' => 0, 'reason' => 'reservation_reserved', 'idempotency_key' => 'res-1']);
    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 3, 'reason' => 'restock', 'idempotency_key' => (string) Str::uuid()]);
    $this->patchJson(route('user.commerce.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => -1, 'reason' => 'damaged', 'idempotency_key' => (string) Str::uuid()]);

    $this->getJson(route('user.commerce.inventory.history', $variant))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.reason', 'Damaged or lost')
        ->assertJsonPath('data.0.delta', -1)
        ->assertJsonPath('data.0.after', 7)
        ->assertJsonPath('data.1.reason', 'Restocked')
        ->assertJsonPath('data.1.by', $this->owner->name);
});

it('logs stock changed in the product editor', function (): void {
    $variant = inventoryVariant($this->workspace->id, 'ts-m', 5);

    $this->put(route('user.commerce.products.details.update', $variant->product), ['name' => 'Tracksuit', 'default_stock' => 9, 'moq' => 1, 'next_step' => 6])->assertSessionHasNoErrors();

    $movement = InventoryMovement::query()->where('variant_id', $variant->id)->sole();
    expect($variant->fresh()->stock_quantity)->toBe(9)
        ->and($movement->reason)->toBe('product_edit')
        ->and($movement->quantity_delta)->toBe(4)
        ->and($movement->metadata)->toMatchArray(['before' => 5, 'after' => 9]);
});
