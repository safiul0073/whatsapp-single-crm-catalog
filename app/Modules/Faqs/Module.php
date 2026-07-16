<?php

namespace App\Modules\Faqs;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'faqs';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'faqs.view' => 'View Faqs',
                'faqs.create' => 'Create Faqs',
                'faqs.edit' => 'Edit Faqs',
                'faqs.delete' => 'Delete Faqs',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Content')
            ->item('FAQs', 'admin.faqs.*', 'ph-question', 'faqs.view', 58);
    }
}
