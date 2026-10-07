<?php

namespace Database\Seeders;

use App\Modules\Commerce\Database\Seeders\GarmentCatalogSeeder;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GarmentCatalogSeeder::class);
    }
}
