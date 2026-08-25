<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commerce_products', function (Blueprint $table) {
            $table->json('default_package_dimensions')->nullable()->after('default_unit_weight_kg');
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->decimal('price_per_kg', 12, 2)->default(0)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropColumn('price_per_kg');
        });

        Schema::table('commerce_products', function (Blueprint $table) {
            $table->dropColumn('default_package_dimensions');
        });
    }
};
