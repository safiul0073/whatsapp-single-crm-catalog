<?php

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\Http\Resources\ProductDetailResource;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\ProductReadinessService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('commerce.manage', 'web');
    Permission::findOrCreate('commerce.view', 'web');
    $this->merchant = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->merchant);
    $this->merchant->givePermissionTo(['commerce.manage', 'commerce.view']);
    $this->actingAs($this->merchant);
    $this->productService = app(ProductService::class);
});

function wizardImage(User $user): Media
{
    return Media::query()->create([
        'name' => 'Wizard image', 'file_name' => 'wizard.webp', 'original_name' => 'wizard.webp',
        'mime_type' => 'image/webp', 'extension' => 'webp', 'type' => 'image', 'size' => 1024,
        'disk' => 'public', 'path' => 'wizard.webp', 'uploaded_by' => $user->id,
    ]);
}

it('round trips all nine wizard steps without resetting previously saved fields', function (): void {
    $this->post(route('user.commerce.products.store'), [
        'name' => 'Wizard Tee', 'slug' => 'custom-wizard-tee', 'status' => 'active',
        'single_piece_price' => 20, 'wholesale_price' => 12, 'selling_mode' => 'both',
        'country_of_origin' => 'BD', 'short_description' => 'A demo tee',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $product = Product::query()->sole();
    expect($product->status)->toBe('draft');

    $image = wizardImage($this->merchant);
    $this->put(route('user.commerce.products.gallery.update', $product), [
        'media' => [['id' => $image->id, 'is_primary' => true]], 'next_step' => 3,
    ])->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.options.update', $product), [
        'options' => [['name' => 'Size', 'code' => 'size', 'values' => ['S', 'M', 'L']]], 'next_step' => 4,
    ])->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.gallery.update', $product), [
        'colors' => [['name' => 'Black', 'hex_code' => '#000000']],
        'media' => [['id' => $image->id, 'color_id' => 'idx_0', 'is_primary' => true]], 'next_step' => 5,
    ])->assertSessionHasNoErrors();
    $color = $product->fresh()->colors->sole();
    $this->put(route('user.commerce.products.options.update', $product), [
        'options' => [['name' => 'Size', 'code' => 'size', 'values' => ['S', 'M', 'L']]],
    ])->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.details.update', $product), [
        'name' => 'Wizard Tee', 'default_stock' => 15, 'moq' => 1,
        'tier_prices' => [['min_quantity' => 10, 'max_quantity' => 50, 'unit_price' => 12]], 'next_step' => 6,
    ])->assertSessionHasNoErrors();
    $this->put(route('user.commerce.products.details.update', $product), [
        'name' => 'Wizard Tee', 'ws_enabled' => true, 'ws_min_sizes' => 3,
        'ws_color_moq' => 6, 'ws_main_moq' => 10, 'ws_ratio_multiplier' => 2,
        'ws_size_ratios' => [$color->id => ['S' => 1, 'M' => 2, 'L' => 1]], 'next_step' => 7,
    ])->assertSessionHasNoErrors();
    foreach ([
        ['feature_highlights' => [['icon' => 'ph-check', 'title' => 'Cotton']], 'next_step' => 8],
        ['description' => 'Long description', 'care_information' => 'Wash cold', 'next_step' => 9],
        ['specifications' => [['label' => 'Material', 'value' => 'Cotton']], 'next_step' => 9],
    ] as $step) {
        $this->put(route('user.commerce.products.details.update', $product), ['name' => 'Wizard Tee'] + $step)
            ->assertSessionHasNoErrors();
    }
    $product = $product->fresh();
    expect($product->status)->toBe('draft')
        ->and($product->slug)->toBe('custom-wizard-tee')
        ->and($product->ws_enabled)->toBeTrue()
        ->and($product->ws_main_moq)->toBe(10)
        ->and($product->ws_size_ratios[$color->id]['M'])->toBe(2)
        ->and($product->variants)->toHaveCount(3)
        ->and($product->variants->pluck('stock_quantity')->unique()->all())->toBe([15])
        ->and($product->gallery->sole()->color_id)->toBe($color->id);
    for ($step = 1; $step <= 9; $step++) {
        $this->get(route('user.commerce.products.edit', ['product' => $product, 'step' => $step]))->assertSuccessful();
    }
    $api = (new ProductDetailResource($product->load(['options.values', 'colors.swatchMedia', 'gallery.media', 'variants.media', 'tierPrices'])))->resolve();
    expect(((array) $api['wholesale_settings']['size_ratios'])[$color->id]['M'])->toBe(2)
        ->and($api['tier_prices'][0]['price'])->toBe(12.0)
        ->and($api['tier_prices'][0]['max_quantity'])->toBe(50);
    config(['filesystems.disks.public.url' => 'https://store.example/storage']);
    Storage::forgetDisk('public');
    $this->put(route('user.commerce.products.publish', $product), ['status' => 'active'])->assertSessionHasNoErrors();
    $this->getJson('/api/commerce/products/'.$product->slug)->assertSuccessful()
        ->assertJsonPath('data.description', 'Long description')
        ->assertJsonPath('data.wholesale_settings.size_ratios.'.$color->id.'.M', 2)
        ->assertJsonPath('data.gallery.0.color_id', $color->id)
        ->assertJsonPath('data.tier_prices.0.price', 12);
    $this->put(route('user.commerce.products.details.update', $product), ['name' => $product->name, 'visibility' => 'hidden'])->assertSessionHasNoErrors();
    $this->getJson('/api/commerce/products/'.$product->slug)->assertNotFound();
});

it('clears optional values and tier rows explicitly while preserving omitted values', function (): void {
    $product = $this->productService->createDraft($this->workspace->id, ['name' => 'Clear Tee', 'features' => ['Cotton'], 'material' => 'Cotton']);
    $this->productService->updateDetails($product, ['name' => 'Clear Tee', 'tier_prices' => [['min_quantity' => 10, 'unit_price' => 5]]]);
    $this->put(route('user.commerce.products.details.update', $product), ['name' => 'Clear Tee', 'features' => [], 'tier_prices' => [], 'material' => null])->assertSessionHasNoErrors();
    expect($product->fresh()->features)->toBe([])->and($product->fresh()->material)->toBeNull()->and($product->fresh()->tierPrices)->toHaveCount(0);
});

it('rejects foreign media colors and products without changing saved data', function (): void {
    $product = $this->productService->createDraft($this->workspace->id, ['name' => 'Protected Tee']);
    $other = User::factory()->create();
    $otherWorkspace = app(WorkspaceResolver::class)->current($other);
    $foreign = $this->productService->createDraft($otherWorkspace->id, ['name' => 'Foreign', 'colors' => [['name' => 'Red']]]);
    $image = wizardImage($other);
    $this->putJson(route('user.commerce.products.gallery.update', $product), ['media' => [['id' => $image->id]]])->assertUnprocessable();
    $this->putJson(route('user.commerce.products.details.update', $product), ['name' => 'Changed', 'colors' => [['id' => $foreign->colors->sole()->id, 'name' => 'Red']]])->assertUnprocessable();
    $this->putJson(route('user.commerce.products.details.update', $foreign), ['name' => 'Changed'])->assertNotFound();
    expect($product->fresh()->name)->toBe('Protected Tee')->and($product->fresh()->gallery)->toHaveCount(0);
});

it('rolls back attempted publication of an incomplete product', function (): void {
    $product = $this->productService->createDraft($this->workspace->id, ['name' => 'Incomplete']);
    $this->put(route('user.commerce.products.details.update', $product), ['name' => 'Changed', 'status' => 'active', 'next_step' => 9])->assertSessionHasErrors('status');
    expect($product->fresh()->name)->toBe('Incomplete')->and($product->fresh()->status)->toBe('draft')->and($product->fresh()->wizard_step)->toBe(2);
});

it('rejects malformed ratios tier ranges option weights and unsafe uploads', function (): void {
    $product = $this->productService->createDraft($this->workspace->id, ['name' => 'Validated']);
    $this->putJson(route('user.commerce.products.details.update', $product), ['name' => 'Validated', 'ws_size_ratios' => [999 => ['XX' => 1.5]]])->assertUnprocessable();
    $this->putJson(route('user.commerce.products.details.update', $product), ['name' => 'Validated', 'tier_prices' => [['min_quantity' => 10, 'max_quantity' => 5, 'unit_price' => 5]]])->assertUnprocessable();
    $this->putJson(route('user.commerce.products.options.update', $product), ['options' => [['name' => 'Size', 'code' => 'size', 'values' => [['value' => 'M', 'weight' => -1, 'weight_unit' => 'kg']]]]])->assertUnprocessable();
    $this->postJson(route('user.commerce.products.media.upload'), ['file' => UploadedFile::fake()->create('payload.html', 1, 'text/html')])->assertUnprocessable();
    expect($product->fresh()->wizard_step)->toBe(2)->and($product->fresh()->tierPrices)->toHaveCount(0);
});

it('rolls back related option and color changes when a step fails after saving', function (): void {
    $product = $this->productService->createDraft($this->workspace->id, ['name' => 'Atomic', 'colors' => [['name' => 'Black']]]);
    $this->productService->updateOptions($product, [['name' => 'Size', 'code' => 'size', 'values' => ['M']]]);
    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('logCustom')->once()->andThrow(new RuntimeException('Audit unavailable'));
    $service = new ProductService(app(ProductReadinessService::class), $audit);
    expect(fn () => $service->updateOptions($product, [['name' => 'Size', 'code' => 'size', 'values' => ['L']]], [['name' => 'White']]))->toThrow(RuntimeException::class);
    $product = $product->fresh();
    expect($product->colors->sole()->name)->toBe('Black')
        ->and($product->options->firstWhere('code', 'size')->values->sole()->value)->toBe('M');
});

it('preserves five demo products and each complete matrix during gallery and detail edits', function (): void {
    Storage::fake('public');
    config(['commerce-demo.workspace_id' => $this->workspace->id]);
    $this->seed(CommerceDemoSeeder::class);
    foreach (Product::query()->where('workspace_id', $this->workspace->id)->get() as $product) {
        $this->put(route('user.commerce.products.details.update', $product), ['name' => $product->name, 'description' => 'Updated demo description'])->assertSessionHasNoErrors();
        $this->put(route('user.commerce.products.gallery.update', $product), [
            'media' => $product->gallery->map(fn ($item): array => ['id' => $item->media_id, 'color_id' => $item->color_id, 'is_primary' => $item->is_primary])->all(),
            'colors' => $product->colors->map(fn ($color): array => ['id' => $color->id, 'swatch_media_id' => $color->swatch_media_id])->all(),
        ])->assertSessionHasNoErrors();
        $product = $product->fresh();
        expect($product->gallery)->toHaveCount(35)->and($product->variants)->toHaveCount(42)
            ->and($product->colors)->toHaveCount(7)->and($product->colors->pluck('name')->filter())->toHaveCount(7)
            ->and($product->ws_enabled)->toBeTrue();
    }
    expect(Product::query()->where('workspace_id', $this->workspace->id)->count())->toBe(5);
});
