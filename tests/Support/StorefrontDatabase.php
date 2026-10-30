<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class StorefrontDatabase
{
    public static function migrate(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
        });
        foreach (['Workspaces', 'Contacts', 'Media', 'MarketingChannels', 'Inbox', 'Shipping', 'AuditLog'] as $module) {
            $paths = glob(app_path("Modules/{$module}/Database/Migrations/*"));
            sort($paths);
            foreach ($paths as $path) {
                if (str_contains($path, 'workspace_role') || str_contains($path, 'extend_channel')) {
                    continue;
                } (require $path)->up();
            }
        }
        (require app_path('Modules/Chatbots/Database/Migrations/2026_07_07_000003_create_chatbot_widgets_table.php'))->up();
        $paths = glob(app_path('Modules/Commerce/Database/Migrations/*'));
        sort($paths);
        foreach ($paths as $path) {
            if (str_contains($path, 'seed_garment')) {
                continue;
            } (require $path)->up();
        }
        $paths = glob(database_path('migrations/*'));
        foreach ($paths as $path) {
            if (str_contains($path, 'wholesale_settings') || str_contains($path, 'wholesale_pack_pricing') || str_contains($path, 'volumetric_shipping') || str_contains($path, 'weight_to_commerce')) {
                (require $path)->up();
            }
        }
        $paths = glob(database_path('migrations/*personal_access_tokens*'));
        foreach ($paths as $path) {
            (require $path)->up();
        }
    }
}
