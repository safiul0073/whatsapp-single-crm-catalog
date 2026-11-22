<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('commerce_products', 'translations')) {
            return;
        }

        Schema::table('commerce_products', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        // The column belongs to the original create migration; fresh installs keep it.
    }
};
