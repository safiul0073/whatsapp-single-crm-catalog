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
                if (! Schema::hasColumn('commerce_store_order_settings', 'review_request_enabled')) {
                    $table->boolean('review_request_enabled')->default(true);
                }
                if (! Schema::hasColumn('commerce_store_order_settings', 'review_request_delay_hours')) {
                    $table->unsignedSmallInteger('review_request_delay_hours')->default(24);
                }
            });
        }

        if (Schema::hasTable('commerce_orders') && ! Schema::hasColumn('commerce_orders', 'review_request_sent_at')) {
            Schema::table('commerce_orders', function (Blueprint $table): void {
                $table->timestamp('review_request_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_store_order_settings')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                foreach (['review_request_enabled', 'review_request_delay_hours'] as $column) {
                    if (Schema::hasColumn('commerce_store_order_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('commerce_orders') && Schema::hasColumn('commerce_orders', 'review_request_sent_at')) {
            Schema::table('commerce_orders', function (Blueprint $table): void {
                $table->dropColumn('review_request_sent_at');
            });
        }
    }
};
