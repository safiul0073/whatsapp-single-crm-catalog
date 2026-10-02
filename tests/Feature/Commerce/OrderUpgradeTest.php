<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTruncation::class);

it('upgrades existing orders without losing amounts and safely repeats on fresh schemas', function () {
    $this->beforeApplicationDestroyed(function (): void {
        $this->truncateTablesForAllConnections();
        RefreshDatabaseState::$migrated = false;
    });
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    $id = DB::table('commerce_orders')->insertGetId(['workspace_id' => $workspace->id, 'number' => 'LEGACY-123', 'provider_message_id' => 'legacy-message', 'currency' => 'EUR', 'subtotal' => '123.45', 'status' => 'requested']);
    Schema::table('commerce_orders', function (Blueprint $table): void {
        $table->dropUnique(['workspace_id', 'submission_reference']);
        $table->dropColumn(['source', 'submission_reference', 'payload_hash', 'customer_reference', 'customer_snapshot', 'tracking_code', 'payment_state', 'payment_instructions', 'payment_evidence', 'discount_amount', 'adjustments', 'shipping_quote_required', 'packed_at', 'delivered_at']);
        $table->decimal('subtotal', 12, 2)->default(0)->change();
        $table->string('provider_message_id')->nullable(false)->change();
    });
    Schema::table('commerce_order_items', fn (Blueprint $table) => $table->dropColumn('group_id'));
    Schema::table('commerce_inventory_movements', fn (Blueprint $table) => $table->dropColumn('metadata'));
    $migration = require glob(base_path('app/Modules/Commerce/Database/Migrations/*upgrade_existing_commerce_for_unified_orders.php'))[0];
    $migration->up();
    $migration->up();
    $row = DB::table('commerce_orders')->find($id);
    expect($row->number)->toBe('LEGACY-123')->and($row->currency)->toBe('EUR')->and($row->subtotal)->toBe('123.4500')
        ->and($row->provider_message_id)->toBe('legacy-message')->and($row->source)->toBe('native_whatsapp');
    expect(Schema::hasColumn('commerce_order_items', 'group_id'))->toBeTrue()->and(Schema::hasColumn('commerce_inventory_movements', 'metadata'))->toBeTrue();
    DB::table('commerce_orders')->insert(['workspace_id' => $workspace->id, 'number' => 'MANUAL-456', 'source' => 'manual', 'currency' => 'USD']);
    expect(DB::table('commerce_orders')->count())->toBe(2);
});
