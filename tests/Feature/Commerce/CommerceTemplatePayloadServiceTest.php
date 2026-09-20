<?php

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Commerce\Services\CommerceTemplatePayloadService;

it('resolves catalog button index when catalog is the only button', function (): void {
    $service = app(CommerceTemplatePayloadService::class);
    $campaign = new Campaign;
    $campaign->settings = [
        'commerce' => ['thumbnail_product_retailer_id' => 'sku-001'],
    ];

    $components = [
        ['type' => 'BODY', 'text' => 'Check out our catalog.'],
        ['type' => 'BUTTONS', 'buttons' => [
            ['type' => 'CATALOG', 'text' => 'View catalog'],
        ]],
    ];

    $result = $service->catalogButtonComponent($campaign, $components);

    expect($result)->not->toBeNull()
        ->and($result['index'])->toBe('0')
        ->and($result['sub_type'])->toBe('CATALOG');
});

it('resolves catalog button index when a URL button comes first', function (): void {
    $service = app(CommerceTemplatePayloadService::class);
    $campaign = new Campaign;
    $campaign->settings = [
        'commerce' => ['thumbnail_product_retailer_id' => 'sku-001'],
    ];

    $components = [
        ['type' => 'BODY', 'text' => 'Check out our catalog.'],
        ['type' => 'BUTTONS', 'buttons' => [
            ['type' => 'URL', 'text' => 'Visit website', 'url' => 'https://example.com'],
            ['type' => 'CATALOG', 'text' => 'View catalog'],
        ]],
    ];

    $result = $service->catalogButtonComponent($campaign, $components);

    expect($result)->not->toBeNull()
        ->and($result['index'])->toBe('1')
        ->and($result['sub_type'])->toBe('CATALOG');
});

it('resolves mpm button index when url and quick reply buttons come first', function (): void {
    $service = app(CommerceTemplatePayloadService::class);
    $campaign = new Campaign;
    $campaign->settings = [
        'commerce' => [
            'thumbnail_product_retailer_id' => 'sku-001',
            'sections' => [['title' => 'Products', 'product_items' => [['product_retailer_id' => 'sku-001']]]],
        ],
    ];

    $components = [
        ['type' => 'BODY', 'text' => 'Choose from these products.'],
        ['type' => 'BUTTONS', 'buttons' => [
            ['type' => 'QUICK_REPLY', 'text' => 'Yes'],
            ['type' => 'QUICK_REPLY', 'text' => 'No'],
            ['type' => 'MPM', 'text' => 'View items'],
        ]],
    ];

    $result = $service->multiProductButtonComponent($campaign, $components);

    expect($result)->not->toBeNull()
        ->and($result['index'])->toBe('2')
        ->and($result['sub_type'])->toBe('MPM');
});

it('defaults to index 0 when no BUTTONS component is found', function (): void {
    $service = app(CommerceTemplatePayloadService::class);
    $campaign = new Campaign;
    $campaign->settings = [
        'commerce' => ['thumbnail_product_retailer_id' => 'sku-001'],
    ];

    $result = $service->catalogButtonComponent($campaign, []);

    expect($result)->not->toBeNull()
        ->and($result['index'])->toBe('0');
});

it('returns null catalog component when thumbnail is blank', function (): void {
    $service = app(CommerceTemplatePayloadService::class);
    $campaign = new Campaign;
    $campaign->settings = ['commerce' => []];

    $result = $service->catalogButtonComponent($campaign, []);

    expect($result)->toBeNull();
});
