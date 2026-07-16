<?php

namespace App\Modules\Newsletter\Database\Seeders;

use App\Modules\Newsletter\Models\Subscriber;
use Illuminate\Database\Seeder;

class NewsletterModuleSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [
            'john.doe@example.com',
            'jane.smith@example.com',
            'admin.com',
        ];

        foreach ($emails as $email) {
            Subscriber::firstOrCreate(
                ['email' => $email],
                [
                    'active' => true,
                ]
            );
        }
    }
}
