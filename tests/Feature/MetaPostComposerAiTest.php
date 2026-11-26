<?php

use App\Models\User;
use App\Modules\AiSettings\Models\AiSetting;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Image;

uses(RefreshDatabase::class);

function composerProduct(int $workspaceId, string $slug = 'eid-panjabi'): Product
{
    return Product::query()->create([
        'workspace_id' => $workspaceId, 'name' => 'Eid Panjabi', 'slug' => $slug, 'sku' => $slug,
        'status' => 'published', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 45,
        'short_description' => 'Soft cotton panjabi', 'description' => '<p>Hand stitched.</p>',
    ]);
}

function composerMedia(): Media
{
    return Media::query()->create([
        'name' => 'photo', 'file_name' => 'p.jpg', 'original_name' => 'p.jpg', 'mime_type' => 'image/jpeg',
        'extension' => 'jpg', 'type' => 'image', 'size' => 10, 'disk' => 'public', 'path' => 'media/p.jpg',
    ]);
}

it('returns the product photos, price and link for the composer', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $product = composerProduct($workspace->id);
    $media = composerMedia();
    ProductMedia::query()->create(['workspace_id' => $workspace->id, 'product_id' => $product->id, 'media_id' => $media->id, 'media_type' => 'image', 'position' => 1]);

    $this->withoutMiddleware()->actingAs($user)
        ->getJson(route('user.meta-social.posts.product-assets', $product->id))
        ->assertOk()
        ->assertJsonPath('name', 'Eid Panjabi')
        ->assertJsonPath('images.0.media_id', $media->id)
        ->assertJsonPath('short_description', 'Soft cotton panjabi');
});

it('does not show products from another workspace', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $product = composerProduct(app(WorkspaceResolver::class)->current($other)->id, 'other');

    $this->withoutMiddleware()->actingAs($user)
        ->getJson(route('user.meta-social.posts.product-assets', $product->id))
        ->assertUnprocessable();
});

it('writes a post caption with AI from the product', function (): void {
    Cache::flush();
    config(['ai.providers' => ['gemini' => ['driver' => 'gemini', 'key' => 'test-key']]]);
    AiSetting::query()->updateOrCreate(['key' => 'ai_default_text_provider'], ['value' => 'gemini']);
    Cache::forget('ai_settings_cache');
    $prompts = [];
    AnonymousAgent::fake(function (string $prompt) use (&$prompts): string {
        $prompts[] = $prompt;

        return 'New Eid Panjabi just dropped! #eid';
    });

    $user = User::factory()->create(['email_verified_at' => now()]);
    $product = composerProduct(app(WorkspaceResolver::class)->current($user)->id);

    $this->withoutMiddleware()->actingAs($user)
        ->postJson(route('user.meta-social.posts.ai-caption'), ['product_id' => $product->id, 'tone' => 'promotional', 'language' => 'en', 'hashtags' => true, 'emojis' => false])
        ->assertOk()
        ->assertJsonPath('message', 'New Eid Panjabi just dropped! #eid');

    expect($prompts[0])->toContain('Eid Panjabi')->toContain('Hand stitched.');
});

it('saves an AI generated image to the media library', function (): void {
    Storage::fake('public');
    config(['ai.default_for_images' => 'openai']);
    Image::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->withoutMiddleware()->actingAs($user)
        ->postJson(route('user.meta-social.posts.ai-image'), ['prompt' => 'Panjabi on a model', 'style' => 'lifestyle'])
        ->assertOk();

    $media = Media::query()->findOrFail($response->json('media_id'));
    expect($media->type)->toBe('image')->and($media->uploaded_by)->toBe($user->id);
    Storage::disk('public')->assertExists($media->path);
});
