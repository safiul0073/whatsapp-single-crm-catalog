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
            $table->string('selling_mode')->default('both');
            $table->boolean('ws_enabled')->default(false);
            $table->unsignedInteger('ws_min_sizes')->nullable();
            $table->unsignedInteger('ws_color_moq')->default(1);
            $table->unsignedInteger('ws_main_moq')->default(1);
            $table->json('ws_size_ratios')->nullable();
            $table->unsignedInteger('ws_ratio_multiplier')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_products', function (Blueprint $table) {
            $table->dropColumn([
                'selling_mode',
                'ws_enabled',
                'ws_min_sizes',
                'ws_color_moq',
                'ws_main_moq',
                'ws_size_ratios',
                'ws_ratio_multiplier',
            ]);
        });
    }
};
