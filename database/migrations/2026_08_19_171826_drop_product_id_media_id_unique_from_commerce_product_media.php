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
        Schema::table('commerce_product_media', function (Blueprint $table) {
            $table->dropUnique('commerce_product_media_product_id_media_id_unique');
            $table->unique(['product_id', 'media_id', 'color_id'], 'commerce_product_media_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_product_media', function (Blueprint $table) {
            $table->dropUnique('commerce_product_media_unique');
            $table->unique(['product_id', 'media_id'], 'commerce_product_media_product_id_media_id_unique');
        });
    }
};
