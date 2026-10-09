<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column was added by editing already-run migrations, so databases migrated before that change lack it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_orders') && ! Schema::hasColumn('commerce_orders', 'fulfillment_type')) {
            Schema::table('commerce_orders', function (Blueprint $table): void {
                $table->string('fulfillment_type')->default('delivery');
            });
        }
    }

    public function down(): void
    {
    }
};
