<?php

use App\Models\User;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\CatalogItemSync;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\CatalogFeedService;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    $user = User::factory()->create();
    $this->workspace = Workspace::query()->create([
        'owner_id' => $user->id, 'name' => 'Demo Tests', 'slug' => 'demo-tests',
        'status' => 'active', 'settings' => ['commerce' => ['currency' => 'USD']],
    ]);
    config(['commerce-demo.workspace_id' => $this->workspace->id]);
});

it('seeds the complete matrix and preserves IDs and unrelated data on rerun', function (): void {
    $other = Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Merchant product', 'slug' => 'merchant-product', 'status' => 'draft']);
    $this->seed(CommerceDemoSeeder::class);
    $products = Product::query()->where('slug', 'like', 'demo-%')->with(['colors.gallery', 'variants.media', 'gallery.media', 'options.values'])->get();
    expect($products)->toHaveCount(5)->and(Media::query()->count())->toBe(175);
    foreach ($products as $product) {
        expect($product->colors)->toHaveCount(7)
            ->and($product->variants)->toHaveCount(42)
            ->and($product->gallery)->toHaveCount(35)
            ->and($product->gallery->where('is_primary', true))->toHaveCount(1)
            ->and($product->options->firstWhere('code', 'size')->values->pluck('value')->all())->toBe(CommerceDemoSeeder::SIZES)
            ->and($product->reviews_count)->toBe(0);
        foreach ($product->colors as $color) {
            expect($color->gallery)->toHaveCount(5)
                ->and($product->variants->where('color_id', $color->id)->pluck('size')->all())->toBe(CommerceDemoSeeder::SIZES)
                ->and($product->variants->where('color_id', $color->id)->pluck('media_id')->unique()->all())->toBe([$color->swatch_media_id])
                ->and(array_keys($product->ws_size_ratios[$color->id]))->toEqualCanonicalizing(CommerceDemoSeeder::SIZES);
        }
        foreach ($product->gallery as $gallery) {
            Storage::disk('public')->assertExists($gallery->media->path);
        }
    }
    $variantIds = ProductVariant::query()->pluck('id', 'sku')->all();
    $mediaIds = Media::query()->pluck('id', 'path')->all();
    $this->seed(CommerceDemoSeeder::class);
    expect(ProductVariant::query()->pluck('id', 'sku')->all())->toBe($variantIds)
        ->and(Media::query()->pluck('id', 'path')->all())->toBe($mediaIds)
        ->and(ProductMedia::query()->count())->toBe(175)
        ->and(ProductVariant::query()->distinct()->count('meta_retailer_id'))->toBe(210)
        ->and($other->fresh()->name)->toBe('Merchant product');
    Http::assertNothingSent();
});

it('exports only the selected color images and exposes their mapping to the storefront', function (): void {
    $this->seed(CommerceDemoSeeder::class);
    $product = Product::query()->where('slug', 'like', 'demo-%')->firstOrFail();
    $feed = app(CatalogFeedService::class);
    foreach ($product->variants as $variant) {
        $payload = $feed->itemPayload($variant);
        $colorKey = strtolower($variant->attributes['color']);
        expect($payload['additional_image_urls'])->toHaveCount(4)
            ->and($payload['size'])->toBe($variant->size)
            ->and($payload['item_group_id'])->toBe('product-'.$product->id)
            ->and($payload['gender'])->toBe('unisex')
            ->and($payload['age_group'])->toBe('adult');
        foreach ([$payload['image_url'], ...$payload['additional_image_urls']] as $url) {
            expect($url)->toContain('/'.$colorKey.'/');
        }
    }
    $response = $this->getJson('/api/commerce/products/'.$product->slug)->assertSuccessful();
    expect($response->json('data.gallery'))->toHaveCount(35)
        ->and(collect($response->json('data.gallery'))->pluck('color_id')->unique())->toHaveCount(7)
        ->and($response->json('data.variants'))->toHaveCount(42);
});

it('does not choose a workspace implicitly', function (): void {
    config(['commerce-demo.workspace_id' => null]);
    $this->seed(CommerceDemoSeeder::class);
    expect(Product::query()->count())->toBe(0)->and(Media::query()->count())->toBe(0);
});

it('fails before database changes when an asset is missing', function (): void {
    $seeder = new class extends CommerceDemoSeeder
    {
        protected function assetPath(string $key, string $color, string $view): string
        {
            return '/missing-commerce-demo-asset.png';
        }
    };
    expect(fn () => $seeder->run())->toThrow(RuntimeException::class, 'Missing demo image');
    expect(Product::query()->count())->toBe(0)->and(Media::query()->count())->toBe(0);
});

it('reconciles obsolete unsynced demo variants but preserves unrelated products', function (): void {
    $this->seed(CommerceDemoSeeder::class);
    $product = Product::query()->where('sku', 'DEMO-001')->firstOrFail();
    $legacy = ProductVariant::query()->create([
        'workspace_id' => $this->workspace->id, 'product_id' => $product->id,
        'sku' => 'DEMO-001-S-ROYAL-BLUE', 'meta_retailer_id' => 'demo-001-s-royal-blue',
        'attributes' => ['size' => 'S', 'color' => 'Royal Blue'], 'status' => 'active', 'price' => 9,
    ]);
    $this->seed(CommerceDemoSeeder::class);
    expect($legacy->fresh())->toBeNull()->and($product->variants()->count())->toBe(42);
});

it('rolls back when an obsolete variant has already been catalog-synced', function (): void {
    $this->seed(CommerceDemoSeeder::class);
    $product = Product::query()->where('sku', 'DEMO-001')->firstOrFail();
    $legacy = ProductVariant::query()->create([
        'workspace_id' => $this->workspace->id, 'product_id' => $product->id,
        'sku' => 'DEMO-001-S-ROYAL-BLUE', 'meta_retailer_id' => 'demo-001-s-royal-blue', 'price' => 9, 'status' => 'active',
    ]);
    $channel = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Test Catalog Channel',
        'status' => 'connected',
        'provider_account_id' => 'test-account',
        'provider_phone_id' => 'test-phone',
    ]);
    $catalog = Catalog::query()->create(['workspace_id' => $this->workspace->id, 'channel_account_id' => $channel->id, 'name' => 'Test Catalog', 'currency' => 'USD', 'feed_token' => 'test-demo-catalog']);
    CatalogItemSync::query()->create(['catalog_id' => $catalog->id, 'variant_id' => $legacy->id, 'workspace_id' => $this->workspace->id, 'retailer_id' => $legacy->meta_retailer_id]);
    $product->update(['name' => 'Name before failed seed']);
    expect(fn () => $this->seed(CommerceDemoSeeder::class))->toThrow(RuntimeException::class, 'manual reconciliation');
    expect($product->fresh()->name)->toBe('Name before failed seed')->and($legacy->fresh())->not->toBeNull();
});
