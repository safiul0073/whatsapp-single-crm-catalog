<?php

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('commerce.manage', 'web');
    Permission::findOrCreate('commerce.view', 'web');
    $this->merchant = User::factory()->create();
    app(WorkspaceResolver::class)->current($this->merchant);
    $this->merchant->givePermissionTo(['commerce.manage', 'commerce.view']);
    $this->actingAs($this->merchant);
});

/**
 * @param  list<string>  $sizes
 * @param  list<string>  $columns
 * @return array<string, mixed>
 */
function sizesFormPayload(array $sizes, array $columns = []): array
{
    $payload = ['next_step' => 4, 'options' => [['code' => 'size', 'name' => 'Size', 'values' => []]], 'size_chart' => ['unit' => 'cm', 'note' => '']];
    foreach ($sizes as $i => $size) {
        $payload['options'][0]['values'][$i] = ['value' => $size, 'weight' => '', 'weight_unit' => 'kg'];
        $payload['size_chart']['rows'][$i] = ['size' => $size, 'values' => array_fill(0, count($columns), '')];
    }
    foreach ($columns as $i => $column) {
        $payload['size_chart']['columns'][$i] = $column;
    }

    return $payload;
}

function sizesTestProduct(): Product
{
    test()->post(route('user.commerce.products.store'), [
        'name' => 'Summer Set', 'status' => 'active', 'single_piece_price' => 20, 'wholesale_price' => 12,
        'selling_mode' => 'both', 'country_of_origin' => 'BD', 'short_description' => 'Set',
    ])->assertSessionHasNoErrors();

    return Product::query()->sole();
}

it('saves a first size on a product that has none', function (): void {
    $product = sizesTestProduct();

    $this->put(route('user.commerce.products.options.update', $product), sizesFormPayload(['1 YEAR']))
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($product->fresh()->options()->where('code', 'size')->first()->values()->pluck('value')->all())->toBe(['1 YEAR']);
});

it('saves sizes with a custom size chart column and colors with stock', function (): void {
    $product = sizesTestProduct();
    $this->put(route('user.commerce.products.gallery.update', $product), ['colors' => [['name' => 'Black', 'hex_code' => '#000000']], 'media' => [], 'next_step' => 4])->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.options.update', $product), sizesFormPayload(['M', 'L'], ['Chest']))->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.details.update', $product), ['name' => 'Summer Set', 'default_stock' => 10, 'moq' => 1])->assertSessionHasNoErrors();

    $payload = sizesFormPayload(['M', 'L', '1 YEAR'], ['Chest', '1 YEAR']);
    $payload['size_chart']['rows'][0]['values'] = ['50', '60'];
    $this->put(route('user.commerce.products.options.update', $product), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
});
