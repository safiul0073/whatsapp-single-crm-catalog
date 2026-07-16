<?php

namespace App\Modules\NotificationTemplates\Database\Seeders;

use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('notification-templates', []) as $slug => $definition) {
            NotificationTemplate::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'channels' => $definition['channels'] ?? ['email'],
                    'variables' => $definition['variables'] ?? [],
                    'email_subject' => $definition['defaults']['email_subject'] ?? null,
                    'email_body' => $definition['defaults']['email_body'] ?? null,
                    'sms_body' => $definition['defaults']['sms_body'] ?? null,
                    'in_app_title' => $definition['defaults']['in_app_title'] ?? null,
                    'in_app_body' => $definition['defaults']['in_app_body'] ?? null,
                    'push_title' => $definition['defaults']['push_title'] ?? null,
                    'push_body' => $definition['defaults']['push_body'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
