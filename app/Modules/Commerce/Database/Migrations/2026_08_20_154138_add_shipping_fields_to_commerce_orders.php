<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_method_id')->nullable()->after('status');
            $table->unsignedBigInteger('shipping_zone_id')->nullable()->after('shipping_method_id');
            $table->decimal('shipping_weight_kg', 8, 3)->nullable()->after('shipping_zone_id');
            $table->decimal('shipping_subtotal', 12, 2)->nullable()->after('shipping_amount'); // renamed or added in case shipping_amount exists
            $table->decimal('shipping_discount', 12, 2)->nullable()->after('shipping_subtotal');
            $table->string('shipping_currency', 3)->nullable()->after('shipping_discount');
            $table->json('shipping_metadata')->nullable()->after('shipping_currency');
        });

        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->decimal('unit_weight_kg', 8, 3)->nullable()->after('unit_price');
            $table->decimal('total_weight_kg', 8, 3)->nullable()->after('unit_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_weight_kg', 'total_weight_kg']);
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_method_id',
                'shipping_zone_id',
                'shipping_weight_kg',
                'shipping_subtotal',
                'shipping_discount',
                'shipping_currency',
                'shipping_metadata',
            ]);
        });
    }
};
