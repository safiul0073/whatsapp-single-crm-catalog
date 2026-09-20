<?php

use App\Models\User;
use App\Modules\Commerce\AiTools\SearchProductsTool;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Laravel\Ai\Tools\Request;

it('searches for published products matching the query', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);

    Product::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Premium Cotton T-Shirt',
        'status' => 'published',
        'slug' => 'premium-cotton-t-shirt',
        'single_piece_price' => 25.00,
    ]);

    Product::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Cotton T-Shirt v2',
        'status' => 'draft',
        'slug' => 'cotton-t-shirt-v2',
        'single_piece_price' => 20.00,
    ]);

    $tool = new SearchProductsTool($workspace->id);

    // Create a mock tool request
    $request = new Request([
        'query' => 'T-Shirt',
    ]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('Premium Cotton T-Shirt')
        ->toContain('$25.00')
        ->toContain('premium-cotton-t-shirt')
        ->not->toContain('Cotton T-Shirt v2'); // Draft product should not appear
});
