<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeFrontendStackCommand extends Command
{
    protected $signature = 'make:frontend-stack {--panel=admin : Panel to wire the frontend stack into} {--parent=Settings : Parent nav label for documentation/reference}';

    protected $description = 'Validate that the multi-theme frontend stack is present and show the next setup steps';

    public function handle(): int
    {
        $panel = (string) $this->option('panel');

        if ($panel !== 'admin') {
            $this->warn('The current implementation ships with admin panel integration only. Proceeding with validation for the existing stack.');
        }

        $artifacts = [
            'Frontend module' => app_path('Modules/Frontend'),
            'Frontend themes config' => config_path('frontend-themes.php'),
            'Frontend menus config' => config_path('frontend-menus.php'),
            'Frontend sections config' => config_path('frontend-sections.php'),
            'Admin themes controller' => app_path('Panels/Admin/Controllers/FrontendThemesController.php'),
            'Admin menus controller' => app_path('Panels/Admin/Controllers/FrontendMenusController.php'),
            'Admin sections controller' => app_path('Panels/Admin/Controllers/FrontendSectionsController.php'),
            'Admin pages controller' => app_path('Panels/Admin/Controllers/FrontendPagesController.php'),
            'Frontend docs' => base_path('docs/frontend-management.md'),
        ];

        $missing = [];

        foreach ($artifacts as $label => $path) {
            if (File::exists($path)) {
                $this->line("<info>OK</info> {$label}: {$path}");
            } else {
                $this->line("<error>Missing</error> {$label}: {$path}");
                $missing[] = $label;
            }
        }

        $this->newLine();

        if ($missing !== []) {
            $this->error('The frontend stack is incomplete in this repository.');
            $this->line('Missing artifacts: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->info('The multi-theme frontend stack is present.');
        $this->line('Next steps:');
        $this->line('  1. php artisan migrate');
        $this->line('  2. php artisan db:seed');
        $this->line('  3. php artisan permission:sync');
        $this->line('  4. Visit /admin/frontend-themes');
        $this->line('  5. Visit /admin/frontend-menus');
        $this->line('  6. Visit /admin/frontend-sections');
        $this->line('  7. Visit /admin/frontend-pages');

        return self::SUCCESS;
    }
}
