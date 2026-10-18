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
                if (! Schema::hasColumn('commerce_store_order_settings', 'order_number_prefix')) {
                    $table->string('order_number_prefix', 20)->default('ORD-');
                }
                if (! Schema::hasColumn('commerce_store_order_settings', 'order_number_digits')) {
                    $table->unsignedSmallInteger('order_number_digits')->default(5);
                }
                if (! Schema::hasColumn('commerce_store_order_settings', 'next_order_sequence')) {
                    $table->unsignedInteger('next_order_sequence')->default(1);
                }
            });
        }

        if (Schema::hasTable('commerce_orders')) {
            // Attempt to replace global unique(number) with per-workspace unique(workspace_id, number)
            try {
                Schema::table('commerce_orders', function (Blueprint $table): void {
                    $table->dropUnique('commerce_orders_number_unique');
                });
            } catch (Throwable) {
                // Ignore if unique index was already dropped or unsupported by driver
            }

            if (! Schema::hasIndex('commerce_orders', 'commerce_orders_workspace_id_number_unique')) {
                try {
                    Schema::table('commerce_orders', function (Blueprint $table): void {
                        $table->unique(['workspace_id', 'number'], 'commerce_orders_workspace_id_number_unique');
                    });
                } catch (Throwable) {
                    // Ignore on sqlite if table already has unique
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_store_order_settings')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                $columns = array_filter(
                    ['order_number_prefix', 'order_number_digits', 'next_order_sequence'],
                    fn ($col) => Schema::hasColumn('commerce_store_order_settings', $col)
                );
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
