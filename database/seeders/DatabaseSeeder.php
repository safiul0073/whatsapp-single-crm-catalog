<?php

namespace Database\Seeders;

use App\Modules\AiSettings\Database\Seeders\AiSettingSeeder;
use App\Modules\Blogs\Database\Seeders\BlogsSeeder;
use App\Modules\Contacts\Database\Seeders\LegacyContactTagSeeder;
use App\Modules\Crm\Database\Seeders\CrmSeeder;
use App\Modules\Currencies\Database\Seeders\CurrencySeeder;
use App\Modules\Faqs\Database\Seeders\FaqsSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendMenuSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Languages\Database\Seeders\LanguagesSeeder;
use App\Modules\Newsletter\Database\Seeders\NewsletterModuleSeeder;
use App\Modules\NotificationTemplates\Database\Seeders\NotificationTemplateSeeder;
use App\Modules\SchedulerQueue\Database\Seeders\SchedulerQueueSeeder;
use App\Modules\Settings\Database\Seeders\SettingSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SettingSeeder::class,
            LanguagesSeeder::class,
            NotificationTemplateSeeder::class,
            AiSettingSeeder::class,
            CurrencySeeder::class,
            FaqsSeeder::class,
            LegacyContactTagSeeder::class,
            CrmSeeder::class,
            NewsletterModuleSeeder::class,
            SchedulerQueueSeeder::class,
            FrontendThemeSettingSeeder::class,
            FrontendSectionSeeder::class,
            FrontendPageSeeder::class,
            BlogsSeeder::class,
            FrontendMenuSeeder::class,
            WaProLandingSeeder::class,
        ]);
    }
}
