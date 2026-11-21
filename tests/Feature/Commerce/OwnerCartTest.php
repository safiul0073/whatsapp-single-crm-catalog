<?php

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Services\CartService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    StoreOrderSetting::forWorkspace($this->workspace->id);
    $this->product = Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'sku' => 'linen', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $this->product->id, 'sku' => 'linen-m', 'meta_retailer_id' => 'linen-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 10, 'status' => 'active']);
    $this->actingAs($this->owner);
});

/**
 * @return array{0: Cart, 1: string}
 */
function ownerCartFixture(int $workspaceId, ?int $customerId, ProductVariant $variant, int $quantity): array
{
    [$cart, $token] = app(CartService::class)->create($workspaceId, $customerId);
    app(CartService::class)->addRetail($cart, $variant->id, $quantity);

    return [$cart, $token];
}

function ownerCartCustomer(int $workspaceId, string $name, string $phone): Customer
{
    $contact = Contact::query()->create(['workspace_id' => $workspaceId, 'name' => $name, 'phone' => $phone]);

    return Customer::query()->create(['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'verified_phone' => $phone, 'verified_at' => now(), 'registered_at' => now(), 'source' => 'storefront']);
}

it('lists open carts with the customer or guest who filled them', function (): void {
    $customer = ownerCartCustomer($this->workspace->id, 'Ayesha Rahman', '+8801711000000');
    ownerCartFixture($this->workspace->id, $customer->id, $this->variant, 2);
    ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    [$completed] = ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    $completed->update(['completed_at' => now()]);
    [$expired] = ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    $expired->update(['expires_at' => now()->subMinute()]);
    app(CartService::class)->create($this->workspace->id);

    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceResolver::class)->current($otherOwner);
    ownerCartFixture($otherWorkspace->id, ownerCartCustomer($otherWorkspace->id, 'Other Store Shopper', '+8801722000000')->id, ProductVariant::query()->create(['workspace_id' => $otherWorkspace->id, 'product_id' => Product::query()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Other', 'slug' => 'other', 'sku' => 'other', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 5])->id, 'sku' => 'other-m', 'meta_retailer_id' => 'other-m', 'size' => 'M', 'price' => 5, 'stock_quantity' => 5, 'status' => 'active']), 1);

    $response = $this->get(route('user.commerce.carts.index'))->assertOk();
    $response->assertSee('Ayesha Rahman')->assertSee('Guest')->assertDontSee('Other Store Shopper');
    expect($response->viewData('carts')->total())->toBe(2)
        ->and($response->viewData('stats'))->toBe(['open' => 2, 'customers' => 1, 'pieces' => 3]);

    expect($this->get(route('user.commerce.carts.index', ['who' => 'customers']))->viewData('carts')->total())->toBe(1)
        ->and($this->get(route('user.commerce.carts.index', ['who' => 'guests']))->viewData('carts')->total())->toBe(1)
        ->and($this->get(route('user.commerce.carts.index', ['search' => 'Ayesha']))->viewData('carts')->total())->toBe(1);
});

it('shows the cart contents to the owner', function (): void {
    [$cart] = ownerCartFixture($this->workspace->id, null, $this->variant, 3);

    $this->get(route('user.commerce.carts.show', $cart))->assertOk()->assertSee('Linen Shirt')->assertSee('Guest shopper.');
});

it('lets the owner change quantities within available stock', function (): void {
    [$cart] = ownerCartFixture($this->workspace->id, null, $this->variant, 2);
    $item = CartItem::query()->where('cart_id', $cart->id)->sole();

    $this->put(route('user.commerce.carts.items.update', [$cart, $item->id]), ['quantity' => 5])->assertRedirect(route('user.commerce.carts.show', $cart));
    expect($item->fresh()->quantity)->toBe(5);

    $this->variant->update(['stock_quantity' => 6]);
    $this->from(route('user.commerce.carts.show', $cart))
        ->put(route('user.commerce.carts.items.update', [$cart, $item->id]), ['quantity' => 7])
        ->assertRedirect(route('user.commerce.carts.show', $cart))
        ->assertSessionHasErrors(['cart' => 'Only 6 left in stock.']);
    expect($item->fresh()->quantity)->toBe(5);
});

it('lets the owner add, remove and clear items, and the shopper sees the change', function (): void {
    config(['storefront.platform_secret' => str_repeat('p', 40)]);
    StoreDomain::query()->create(['workspace_id' => $this->workspace->id, 'host' => 'owner-cart.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    [$cart, $token] = ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    $second = ProductVariant::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $this->product->id, 'sku' => 'linen-l', 'meta_retailer_id' => 'linen-l', 'size' => 'L', 'price' => 20, 'stock_quantity' => 10, 'status' => 'active']);

    $this->post(route('user.commerce.carts.items.store', $cart), ['variant_id' => $second->id, 'quantity' => 2])->assertRedirect(route('user.commerce.carts.show', $cart));
    $shopper = ['X-Storefront-Secret' => str_repeat('p', 40), 'X-Store-Host' => 'owner-cart.localhost', 'X-Cart-Token' => $token];
    $items = $this->getJson('/api/storefront/v1/cart', $shopper)->assertOk()->json('data.items');
    expect(collect($items)->pluck('quantity', 'variant_id')->all())->toBe([$this->variant->id => 1, $second->id => 2]);

    $line = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $this->variant->id)->sole();
    $this->delete(route('user.commerce.carts.items.destroy', [$cart, $line->id]))->assertRedirect();
    expect(CartItem::query()->where('cart_id', $cart->id)->pluck('variant_id')->all())->toBe([$second->id]);

    $this->delete(route('user.commerce.carts.clear', $cart))->assertRedirect();
    expect(CartItem::query()->where('cart_id', $cart->id)->exists())->toBeFalse()
        ->and($this->getJson('/api/storefront/v1/cart', $shopper)->json('data.items'))->toBe([]);
});

it('removes a whole cart group', function (): void {
    [$cart] = ownerCartFixture($this->workspace->id, null, $this->variant, 2);
    $group = CartGroup::query()->where('cart_id', $cart->id)->sole();

    $this->delete(route('user.commerce.carts.groups.destroy', [$cart, $group->id]))->assertRedirect();

    expect(CartItem::query()->where('cart_id', $cart->id)->exists())->toBeFalse()
        ->and($group->fresh()->removed_at)->not->toBeNull();
});

it('refuses to add a wholesale-only product', function (): void {
    [$cart] = ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    $wholesale = Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Bulk Tee', 'slug' => 'bulk-tee', 'sku' => 'bulk', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'wholesale', 'ws_enabled' => true, 'wholesale_price' => 5]);
    $variant = ProductVariant::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $wholesale->id, 'sku' => 'bulk-m', 'meta_retailer_id' => 'bulk-m', 'size' => 'M', 'price' => 5, 'stock_quantity' => 100, 'status' => 'active']);

    $this->from(route('user.commerce.carts.show', $cart))
        ->post(route('user.commerce.carts.items.store', $cart), ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('cart');
    expect(CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variant->id)->exists())->toBeFalse();

    $names = collect($this->getJson(route('user.commerce.carts.products'))->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Linen Shirt')->not->toContain('Bulk Tee');
});

it('protects carts by workspace and permission', function (): void {
    [$cart] = ownerCartFixture($this->workspace->id, null, $this->variant, 1);
    $item = CartItem::query()->where('cart_id', $cart->id)->sole();

    $otherOwner = User::factory()->create();
    app(WorkspaceResolver::class)->current($otherOwner);
    $this->actingAs($otherOwner);
    $this->get(route('user.commerce.carts.show', $cart))->assertNotFound();
    $this->delete(route('user.commerce.carts.clear', $cart))->assertNotFound();

    $member = User::factory()->create();
    $this->workspace->members()->attach($member, ['status' => 'active']);
    $this->actingAs($member)->withSession(['active_workspace_id' => $this->workspace->id]);
    $this->put(route('user.commerce.carts.items.update', [$cart, $item->id]), ['quantity' => 2])->assertForbidden();
    $this->delete(route('user.commerce.carts.clear', $cart))->assertForbidden();

    expect($item->fresh()->quantity)->toBe(1);
});
