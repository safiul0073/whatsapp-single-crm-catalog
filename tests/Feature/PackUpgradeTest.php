<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('upgrades an existing cart without changing its quantities and can resume each schema step', function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    Schema::create('workspaces', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('commerce_carts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('workspace_id');
    });
    Schema::create('commerce_cart_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('workspace_id');
        $table->foreignId('cart_id');
        $table->foreignId('product_id');
        $table->foreignId('variant_id');
        $table->unsignedInteger('quantity');
        $table->decimal('price_snapshot', 18, 4);
        $table->unique(['cart_id', 'variant_id']);
    });
    Schema::create('commerce_order_groups', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('pieces_per_box')->default(0);
    });
    DB::table('workspaces')->insert(['id' => 1]);
    DB::table('commerce_carts')->insert(['id' => 1, 'workspace_id' => 1]);
    DB::table('commerce_cart_items')->insert(['id' => 1, 'workspace_id' => 1, 'cart_id' => 1, 'product_id' => 5, 'variant_id' => 7, 'quantity' => 13, 'price_snapshot' => 20]);

    $migration = require app_path('Modules/Commerce/Database/Migrations/2026_10_18_000001_add_pack_templates_and_cart_groups.php');
    $migration->up();
    $migration->up();

    expect(DB::table('commerce_cart_items')->where('id', 1)->value('quantity'))->toBe(13)
        ->and(DB::table('commerce_cart_items')->where('id', 1)->value('group_id'))->toBeNull()
        ->and(Schema::hasIndex('commerce_cart_items', 'commerce_cart_items_cart_id_group_id_variant_id_unique'))->toBeTrue()
        ->and(Schema::hasIndex('commerce_cart_items', 'commerce_cart_items_cart_id_variant_id_unique'))->toBeFalse()
        ->and(Schema::hasColumn('commerce_order_groups', 'pack_snapshot'))->toBeTrue();

    DB::disconnect('sqlite');
});
