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
        Schema::table('commerce_product_option_values', function (Blueprint $table) {
            $table->decimal('weight', 8, 3)->nullable()->after('value');
            $table->string('weight_unit', 10)->default('kg')->after('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_product_option_values', function (Blueprint $table) {
            $table->dropColumn(['weight', 'weight_unit']);
        });
    }
};
