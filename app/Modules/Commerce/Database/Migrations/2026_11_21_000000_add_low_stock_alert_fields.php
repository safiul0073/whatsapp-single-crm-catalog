<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_store_order_settings')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('commerce_store_order_settings', 'low_stock_alerts_enabled')) {
                    $table->boolean('low_stock_alerts_enabled')->default(true);
                }
                if (! Schema::hasColumn('commerce_store_order_settings', 'low_stock_threshold')) {
                    $table->unsignedInteger('low_stock_threshold')->default(3);
                }
            });
        }

        if (Schema::hasTable('commerce_product_variants') && ! Schema::hasColumn('commerce_product_variants', 'low_stock_notified_at')) {
            Schema::table('commerce_product_variants', function (Blueprint $table): void {
                $table->timestamp('low_stock_notified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_store_order_settings')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                foreach (['low_stock_alerts_enabled', 'low_stock_threshold'] as $column) {
                    if (Schema::hasColumn('commerce_store_order_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('commerce_product_variants') && Schema::hasColumn('commerce_product_variants', 'low_stock_notified_at')) {
            Schema::table('commerce_product_variants', function (Blueprint $table): void {
                $table->dropColumn('low_stock_notified_at');
            });
        }
    }
};
