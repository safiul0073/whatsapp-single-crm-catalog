<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('commerce_products', 'size_chart')) {
            return;
        }

        Schema::table('commerce_products', function (Blueprint $table): void {
            $table->json('size_chart')->nullable();
        });
    }

    public function down(): void
    {
        // The column belongs to the original create migration; fresh installs keep it.
    }
};
