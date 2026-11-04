<?php

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Media\Jobs\GenerateImageVariants;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\ImageVariantService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Storage::fake('public');
});

afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('queues variants on upload and writes resized webp copies', function (): void {
    Queue::fake();
    $media = app(MediaService::class)->upload(UploadedFile::fake()->image('shirt.jpg', 2000, 2500));
    Queue::assertPushed(GenerateImageVariants::class, fn ($job) => $job->mediaId === $media->id);
    expect($media->variantUrl('thumb'))->toBe($media->url);

    (new GenerateImageVariants($media->id))->handle(app(ImageVariantService::class));
    $media = $media->fresh();

    foreach (['thumb' => 400, 'large' => 1200] as $size => $width) {
        $path = ImageVariantService::variantPath($media->path, $size);
        Storage::disk('public')->assertExists($path);
        expect(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBe($width)
            ->and($media->variantUrl($size))->toEndWith($path);
    }
    expect($media->thumbnail_url)->toEndWith('-400.webp');

    app(MediaService::class)->delete($media);
    Storage::disk('public')->assertMissing(ImageVariantService::variantPath($media->path, 'thumb'));
});

it('does not upscale small images and skips unsupported files', function (): void {
    Queue::fake();
    $small = app(MediaService::class)->upload(UploadedFile::fake()->image('small.png', 300, 300));
    expect(app(ImageVariantService::class)->generate($small))->toBe(['thumb', 'large'])
        ->and(getimagesizefromstring(Storage::disk('public')->get(ImageVariantService::variantPath($small->path, 'large')))[0])->toBe(300);

    $gif = app(MediaService::class)->upload(UploadedFile::fake()->image('anim.gif', 800, 800));
    expect(app(ImageVariantService::class)->generate($gif))->toBe([])
        ->and($gif->variantUrl('thumb'))->toBe($gif->url);
    Queue::assertPushed(GenerateImageVariants::class, 1);

    $remote = Media::query()->create(['name' => 'r', 'file_name' => 'r.jpg', 'original_name' => 'r.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'type' => 'image', 'size' => 1, 'disk' => 'public', 'path' => 'https://cdn.example.com/r.jpg']);
    expect($remote->variantUrl('large'))->toBe('https://cdn.example.com/r.jpg');
});

it('refreshes the storefront cache when variants finish and when gallery images change', function (): void {
    Queue::fake();
    $workspace = Workspace::query()->create(['name' => 'One', 'slug' => 'one', 'status' => 'active']);
    $media = app(MediaService::class)->upload(UploadedFile::fake()->image('shirt.jpg', 1600, 2000));
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'shirt', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20, 'primary_media_id' => $media->id]);
    Queue::fake();

    (new GenerateImageVariants($media->id))->handle(app(ImageVariantService::class));
    Queue::assertPushed(RevalidateStorefront::class, fn ($job) => $job->workspace === $workspace->id);

    Queue::fake();
    $gallery = ProductMedia::query()->create(['workspace_id' => $workspace->id, 'product_id' => $product->id, 'media_id' => $media->id, 'media_type' => 'image', 'role' => 'gallery', 'position' => 0, 'is_primary' => false]);
    $gallery->delete();
    Queue::assertPushed(RevalidateStorefront::class, 2);
});
