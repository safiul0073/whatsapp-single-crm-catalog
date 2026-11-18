<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_product_variants', 'cost_price')) {
            Schema::table('commerce_product_variants', function (Blueprint $table) {
                $table->decimal('cost_price', 19, 4)->nullable()->after('compare_at_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_product_variants', 'cost_price')) {
            Schema::table('commerce_product_variants', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
