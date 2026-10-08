<?php

use App\Modules\Commerce\Database\Seeders\GarmentCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(GarmentCatalogSeeder::class)->run();
    }

    public function down(): void
    {
        // Retain catalog data because production products may reference these records.
    }
};
