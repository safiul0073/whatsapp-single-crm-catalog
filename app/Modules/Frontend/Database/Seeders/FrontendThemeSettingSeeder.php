<?php

namespace App\Modules\Frontend\Database\Seeders;

use App\Modules\Frontend\Models\FrontendThemeSetting;
use Illuminate\Database\Seeder;

class FrontendThemeSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'active_theme' => 'classic',
            'theme.classic.enabled' => '1',
            'theme.classic.logo_text' => 'Classic',
            'theme.classic.primary_color' => '#2148ff',
            'theme.classic.accent_color' => '#15164f',
            'theme.classic.show_hero_kicker' => '1',
            'theme.classic.footer_link_cookies' => '/cookie-policy',
        ];

        foreach ($defaults as $key => $value) {
            FrontendThemeSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
