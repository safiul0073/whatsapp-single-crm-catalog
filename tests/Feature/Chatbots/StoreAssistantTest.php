<?php

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Commerce\AiAgents\StoreAssistantAgent;
use App\Modules\Commerce\AiTools\GetProductDetailsTool;
use App\Modules\Commerce\AiTools\OrderingGuideTool;
use App\Modules\Commerce\AiTools\SearchProductsTool;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Services\StorefrontUrl;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request as ToolRequest;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['storefront.url' => 'https://storefront.test']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->workspace = app(WorkspaceResolver::class)->current($user);
    StoreDomain::query()->where('workspace_id', $this->workspace->id)->update(['is_primary' => false]);
    StoreDomain::query()->create(['workspace_id' => $this->workspace->id, 'host' => 'shop.example.com', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'custom']);
    $media = Media::query()->create(['name' => 'black', 'file_name' => 'black.png', 'original_name' => 'black.png', 'mime_type' => 'image/png', 'extension' => 'png', 'type' => 'image', 'size' => 100, 'disk' => 'public', 'path' => 'https://cdn.example.com/black.png']);
    $this->hoodie = Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Fleece Hoodie', 'slug' => 'fleece-hoodie', 'sku' => 'hoodie', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 25, 'wholesale_price' => 12, 'ws_enabled' => true, 'ws_main_moq' => 24, 'ws_ratio_multiplier' => 1]);
    $black = ProductColor::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $this->hoodie->id, 'name' => 'Black', 'slug' => 'black', 'swatch_media_id' => $media->id]);
    ProductColor::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $this->hoodie->id, 'name' => 'Navy', 'slug' => 'navy']);
    $this->hoodie->update(['ws_size_ratios' => [$black->id => ['M' => 2, 'L' => 2]]]);
    ProductVariant::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $this->hoodie->id, 'color_id' => $black->id, 'sku' => 'hoodie-black-m', 'meta_retailer_id' => 'hoodie-black-m', 'size' => 'M', 'price' => 25, 'stock_quantity' => 10, 'status' => 'active']);
    Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Draft Hoodie', 'slug' => 'draft-hoodie', 'sku' => 'draft', 'status' => 'draft', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 30]);
});

function storeMode(int $workspaceId, bool $retail, bool $wholesale): void
{
    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $workspaceId], ['config' => ['retail_enabled' => $retail, 'wholesale_enabled' => $wholesale]]);
    StoreFeatures::flush();
}

function toolJson(object $tool, array $arguments): array
{
    return json_decode((string) $tool->handle(new ToolRequest($arguments)), true) ?? [];
}

it('finds only sellable products and links them to the storefront host', function (): void {
    $result = toolJson(new SearchProductsTool($this->workspace->id), ['query' => 'hoodie']);

    expect(collect($result['products'])->pluck('slug')->all())->toBe(['fleece-hoodie'])
        ->and($result['products'][0]['url'])->toBe('https://shop.example.com/products/fleece-hoodie')
        ->and($result['products'][0]['colors'])->toBe(['Black', 'Navy'])
        ->and($result['products'][0]['in_stock'])->toBeTrue()
        ->and($result['store_mode'])->toBe('both');
});

it('never shows retail prices when the store sells wholesale only', function (): void {
    storeMode($this->workspace->id, false, true);

    $details = toolJson(new GetProductDetailsTool($this->workspace->id), ['product' => 'fleece-hoodie']);
    $encoded = json_encode($details);

    expect($details['store_mode'])->toBe('wholesale')
        ->and($details['product']['prices'])->toBe(['Wholesale: USD 12.00 per piece (minimum 24 pieces)'])
        ->and($encoded)->not->toContain('Retail:')
        ->and($details['product']['wholesale']['one_box_per_color'])->toEqual(['Black' => ['M' => 2, 'L' => 2]]);
});

it('never shows wholesale data when the store sells retail only', function (): void {
    storeMode($this->workspace->id, true, false);

    $details = toolJson(new GetProductDetailsTool($this->workspace->id), ['product' => 'fleece-hoodie']);

    expect($details['product']['prices'])->toBe(['Retail: USD 25.00 per piece'])
        ->and($details['product']['wholesale'])->toBeNull();
});

it('lists every color with its photo and stock', function (): void {
    $details = toolJson(new GetProductDetailsTool($this->workspace->id), ['product' => (string) $this->hoodie->id]);
    $colors = collect($details['product']['colors'])->keyBy('name');

    expect($colors->keys()->all())->toBe(['Black', 'Navy'])
        ->and($colors['Black']['image_url'])->toBe('https://cdn.example.com/black.png')
        ->and($colors['Black']['sizes_in_stock'])->toBe(['M'])
        ->and($colors['Navy']['in_stock'])->toBeFalse();
});

it('gives ordering steps that follow the store mode', function (): void {
    $both = toolJson(new OrderingGuideTool($this->workspace->id), ['product' => 'fleece-hoodie', 'color' => 'Black']);
    expect(array_keys($both['steps']))->toHaveCount(2)
        ->and(json_encode($both, JSON_UNESCAPED_SLASHES))->toContain('https://shop.example.com/products/fleece-hoodie')
        ->and(json_encode($both, JSON_UNESCAPED_SLASHES))->toContain('Select the color \"Black\"');

    storeMode($this->workspace->id, false, true);
    $wholesale = toolJson(new OrderingGuideTool($this->workspace->id), ['product' => 'fleece-hoodie']);
    expect(array_keys($wholesale['steps']))->toBe(['Wholesale order'])
        ->and(json_encode($wholesale, JSON_UNESCAPED_SLASHES))->toContain('M:2')
        ->and(json_encode($wholesale, JSON_UNESCAPED_SLASHES))->toContain('L:2')
        ->and(json_encode($wholesale, JSON_UNESCAPED_SLASHES))->toContain('24 pieces')
        ->and(json_encode($wholesale, JSON_UNESCAPED_SLASHES))->not->toContain('Retail');
});

it('reads the product slug from storefront page urls', function (?string $url, ?string $slug): void {
    expect(StorefrontUrl::productSlugFromUrl($url))->toBe($slug);
})->with([
    ['https://shop.example.com/products/fleece-hoodie', 'fleece-hoodie'],
    ['https://shop.example.com/en/products/fleece-hoodie/?color=4', 'fleece-hoodie'],
    ['https://shop.example.com/collections/new', null],
    ['https://shop.example.com/products/../admin', null],
    [null, null],
]);

it('gives the assistant the viewed product, chat history and returns product cards to the widget', function (): void {
    $chatbot = Chatbot::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Shop Bot', 'persona' => 'Help shoppers.', 'greeting' => 'Hi', 'is_active' => true]);
    $widget = ChatbotWidget::query()->create(['workspace_id' => $this->workspace->id, 'chatbot_id' => $chatbot->id, 'name' => 'Store', 'public_token' => 'store-widget', 'is_active' => true, 'allowed_domains' => ['shop.example.com'], 'settings' => []]);
    StoreAssistantAgent::fake(['This hoodie comes in Black and Navy.', 'Sure, here are the steps.']);

    $session = $this->withHeader('Origin', 'https://shop.example.com')
        ->postJson(route('widgets.chatbot.sessions', $widget->public_token), ['visitor_uid' => 'v1', 'page_url' => 'https://shop.example.com/'])
        ->json('session_token');

    $first = $this->withHeader('Origin', 'https://shop.example.com')->postJson(route('widgets.chatbot.messages', $widget->public_token), [
        'session_token' => $session,
        'message' => 'What colors does this come in?',
        'page_url' => 'https://shop.example.com/products/fleece-hoodie',
    ])->assertOk();

    expect($first->json('reply.products.0.name'))->toBe('Fleece Hoodie')
        ->and($first->json('reply.products.0.url'))->toBe('https://shop.example.com/products/fleece-hoodie')
        ->and(collect($first->json('reply.products.0.colors'))->pluck('color')->all())->toBe(['Black']);

    $this->withHeader('Origin', 'https://shop.example.com')->postJson(route('widgets.chatbot.messages', $widget->public_token), [
        'session_token' => $session,
        'message' => 'How do I order?',
    ])->assertOk();

    StoreAssistantAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'How do I order?'
        && $prompt->agent->currentProduct?->slug === 'fleece-hoodie'
        && count($prompt->agent->messages()) === 2
        && str_contains($prompt->agent->instructions(), 'STORE SELLING MODE: both'));
});
