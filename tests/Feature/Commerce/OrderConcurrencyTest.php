<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(DatabaseTruncation::class);

it('sees newly committed reservations when an older payment transaction reacquires stock', function () {
    $this->beforeApplicationDestroyed(function (): void {
        $this->truncateTablesForAllConnections();
        RefreshDatabaseState::$migrated = false;
    });
    Queue::fake([NotifyOrderEvent::class]);
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    $product = Product::create(['workspace_id' => $workspace->id, 'name' => 'Final piece', 'slug' => 'final-piece', 'sku' => 'FINAL', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 10]);
    $variant = $product->variants()->create(['workspace_id' => $workspace->id, 'sku' => 'FINAL-S', 'meta_retailer_id' => 'FINAL-S', 'size' => 'S', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active']);
    $data = ['submission_reference' => (string) Str::uuid(), 'customer' => ['name' => 'Buyer', 'phone' => '+15555550111'], 'shipping_address' => ['name' => 'Buyer', 'phone' => '+15555550111', 'country' => 'US', 'line1' => 'Main Street', 'city' => 'Boston'], 'groups' => [['mode' => 'retail', 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]]];
    $order = app(UnifiedOrderService::class)->create($workspace, $data);
    app(OrderWorkflowService::class)->quote($order, ['shipping_address' => $data['shipping_address'], 'shipping_amount' => 0]);
    $this->travel(25)->hours();

    $originalConnection = config('database.default');
    config(['database.connections.concurrent_order' => config('database.connections.'.$originalConnection)]);
    $connection = DB::connection($originalConnection);
    $connection->beginTransaction();
    try {
        expect($order->reservations()->count())->toBe(1);
        DB::setDefaultConnection('concurrent_order');
        $data['submission_reference'] = (string) Str::uuid();
        $other = app(UnifiedOrderService::class)->create($workspace, $data);
        expect($other->reservations()->sum('quantity'))->toBe('1');
        DB::setDefaultConnection($originalConnection);
        expect(fn () => app(OrderWorkflowService::class)->transition($order->fresh(), 'paid'))->toThrow(ValidationException::class);
        expect($variant->fresh()->stock_quantity)->toBe(1)->and($order->fresh()->payment_state)->toBe('unpaid');
    } finally {
        DB::setDefaultConnection($originalConnection);
        $connection->rollBack();
        DB::purge('concurrent_order');
    }
});
