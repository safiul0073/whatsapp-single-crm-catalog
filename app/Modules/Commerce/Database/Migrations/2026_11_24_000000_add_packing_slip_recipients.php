<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_store_order_settings') && ! Schema::hasColumn('commerce_store_order_settings', 'packing_slip_recipients')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                $table->json('packing_slip_recipients')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_store_order_settings') && Schema::hasColumn('commerce_store_order_settings', 'packing_slip_recipients')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                $table->dropColumn('packing_slip_recipients');
            });
        }
    }
};
