<?php

namespace App\Modules\Languages\Database\Seeders;

use App\Modules\Languages\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1],
            ['code' => 'bn', 'name' => 'Bengali', 'native_name' => 'বাংলা', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'is_active' => true, 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($languages as $lang) {
            Language::updateOrCreate(['code' => $lang['code']], $lang);
        }
    }
}
