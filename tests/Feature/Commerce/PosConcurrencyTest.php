<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\PosService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(DatabaseTruncation::class);

it('reads committed payments and reservations after an older POS transaction acquires locks', function () {
    $this->beforeApplicationDestroyed(function (): void {
        $this->truncateTablesForAllConnections();
        RefreshDatabaseState::$migrated = false;
    });
    Queue::fake([NotifyOrderEvent::class]);
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'POS final piece', 'slug' => 'pos-final-piece', 'sku' => 'POS-FINAL', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 10]);
    $variant = $product->variants()->create(['workspace_id' => $workspace->id, 'sku' => 'POS-FINAL-S', 'meta_retailer_id' => 'POS-FINAL-S', 'size' => 'S', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active']);
    $data = ['submission_reference' => (string) Str::uuid(), 'fulfillment_type' => 'pickup', 'walk_in' => false, 'handover' => false, 'customer' => ['name' => 'Credit customer', 'phone' => '+15555550112'], 'groups' => [['mode' => 'retail', 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]]];
    $order = app(PosService::class)->checkout($workspace, $data, $user);
    $this->travel(25)->hours();
    $original = config('database.default');
    config(['database.connections.concurrent_pos' => config('database.connections.'.$original)]);
    $connection = DB::connection($original);
    $connection->beginTransaction();
    try {
        expect($order->payments()->count())->toBe(0);
        DB::setDefaultConnection('concurrent_pos');
        $payment = ['submission_reference' => (string) Str::uuid(), 'amount' => '10', 'currency' => 'USD', 'method' => 'cash'];
        app(PosService::class)->recordPayment($order, $payment, $user);
        $data['submission_reference'] = (string) Str::uuid();
        $otherOrder = app(PosService::class)->checkout($workspace, $data, $user);
        DB::setDefaultConnection($original);
        $extraPayment = array_replace($payment, ['submission_reference' => (string) Str::uuid(), 'amount' => '1']);
        expect(fn () => app(PosService::class)->recordPayment($order, $extraPayment, $user))->toThrow(ValidationException::class);
        expect(app(PosService::class)->recordPayment($order, $payment, $user)->amount)->toBe('10.0000');
        expect(fn () => app(PosService::class)->pickup($order))->toThrow(ValidationException::class);
        expect(fn () => app(PosService::class)->transition($order, 'cancelled'))->toThrow(ValidationException::class);
        expect(app(PosService::class)->checkout($workspace, $data, $user)->id)->toBe($otherOrder->id);
        expect($variant->fresh()->stock_quantity)->toBe(1);
        expect($otherOrder->reservations()->count())->toBe(1);
    } finally {
        DB::setDefaultConnection($original);
        $connection->rollBack();
        DB::purge('concurrent_pos');
    }
    expect(OrderPayment::query()->where('order_id', $order->id)->count())->toBe(1);
});
