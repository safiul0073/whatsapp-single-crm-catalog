<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_store_order_settings') && ! Schema::hasColumn('commerce_store_order_settings', 'owner_whatsapp_number')) {
            Schema::table('commerce_store_order_settings', function (Blueprint $table): void {
                $table->string('owner_whatsapp_number', 32)->nullable();
            });
        }

        if (Schema::hasTable('commerce_order_events') && ! Schema::hasColumn('commerce_order_events', 'owner_whatsapp_notified_at')) {
            Schema::table('commerce_order_events', function (Blueprint $table): void {
                $table->timestamp('owner_whatsapp_notified_at')->nullable();
            });
        }
    }

    public function down(): void {}
};
