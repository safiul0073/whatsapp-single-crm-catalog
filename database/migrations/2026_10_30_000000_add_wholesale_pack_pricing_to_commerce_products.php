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
            $table->string('ws_moq_unit', 10)->default('pcs')->after('ws_main_moq');
            $table->json('ws_pack_prices')->nullable()->after('ws_moq_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_products', function (Blueprint $table) {
            $table->dropColumn(['ws_moq_unit', 'ws_pack_prices']);
        });
    }
};
