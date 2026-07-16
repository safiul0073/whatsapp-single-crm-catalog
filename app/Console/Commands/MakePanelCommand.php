<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakePanelCommand extends Command
{
    protected $signature = 'make:panel {name : The name of the panel} {--custom-components : Create a custom components directory for this panel}';

    protected $description = 'Create a new panel with routes, controllers, middleware, and views';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $lowerName = Str::lower($name);
        $kebabName = Str::kebab($name);
        $customComponents = $this->option('custom-components');

        $panelPath = app_path("Panels/{$studlyName}");
        $viewsPath = resource_path("views/panels/{$lowerName}");
        $layoutComponentPath = resource_path('views/components/layouts');

        if (File::exists($panelPath)) {
            $this->error("Panel {$studlyName} already exists!");

            return self::FAILURE;
        }

        // Create directory structure
        File::makeDirectory("{$panelPath}/Controllers", 0755, true);
        File::makeDirectory("{$panelPath}/Middleware", 0755, true);
        File::makeDirectory("{$panelPath}/Requests", 0755, true);
        File::makeDirectory($viewsPath, 0755, true);

        if (! File::exists($layoutComponentPath)) {
            File::makeDirectory($layoutComponentPath, 0755, true);
        }

        // Create routes.php
        $this->createFromStub('panel/routes', "{$panelPath}/routes.php", [
            'studlyName' => $studlyName,
        ]);

        // Create DashboardController.php
        $this->createFromStub('panel/DashboardController', "{$panelPath}/Controllers/DashboardController.php", [
            'studlyName' => $studlyName,
            'lowerName' => $lowerName,
        ]);

        // Create PanelAccess middleware
        $this->createFromStub('panel/PanelAccess', "{$panelPath}/Middleware/{$studlyName}Access.php", [
            'studlyName' => $studlyName,
        ]);

        // Create layout component
        $this->createFromStub('panel/layout.blade', "{$layoutComponentPath}/{$lowerName}.blade.php", [
            'studlyName' => $studlyName,
            'lowerName' => $lowerName,
        ]);

        // Create dashboard view
        $this->createFromStub('panel/dashboard.blade', "{$viewsPath}/dashboard.blade.php", [
            'studlyName' => $studlyName,
            'lowerName' => $lowerName,
        ]);

        // Create custom components directory if requested
        if ($customComponents) {
            $this->createCustomComponents($lowerName, $viewsPath);
        }

        $this->info("Panel {$studlyName} created successfully!");
        $this->newLine();

        $componentsConfig = $customComponents ? 'custom' : 'default';

        $this->line('Add the following to config/panels.php:');
        $this->newLine();
        $this->line("    '{$lowerName}' => [");
        $this->line("        'name'       => '{$studlyName} Panel',");
        $this->line("        'prefix'     => '{$kebabName}',");
        $this->line("        'middleware' => ['web', 'auth', 'panel:{$lowerName}'],");
        $this->line("        'roles'      => [],");
        $this->line("        'guard'      => 'web',");
        $this->line("        'theme'      => 'light',");
        $this->line("        'components' => '{$componentsConfig}',");
        $this->line("        'active'     => true,");
        $this->line("        'navigation' => [");
        $this->line('            [');
        $this->line("                'label' => 'Dashboard',");
        $this->line("                'icon'  => 'ph-house',");
        $this->line("                'route' => '{$lowerName}.dashboard',");
        $this->line("                'group' => 'Main Menu',");
        $this->line('            ],');
        $this->line('        ],');
        $this->line('    ],');

        if ($customComponents) {
            $this->newLine();
            $this->info("Custom components directory created at: resources/views/panels/{$lowerName}/components/");
            $this->line('Override any shared component by creating the same file structure here.');
            $this->line('Example: components/forms/input.blade.php overrides <x-forms.input>');
        }

        return self::SUCCESS;
    }

    protected function createCustomComponents(string $lowerName, string $viewsPath): void
    {
        $componentsPath = "{$viewsPath}/components";

        // Create base directories matching shared component structure
        File::makeDirectory("{$componentsPath}/layouts", 0755, true);
        File::makeDirectory("{$componentsPath}/forms", 0755, true);
        File::makeDirectory("{$componentsPath}/ui", 0755, true);
        File::makeDirectory("{$componentsPath}/navigation", 0755, true);
        File::makeDirectory("{$componentsPath}/tables", 0755, true);

        // Copy the layout as a starting point for customization
        $sharedLayout = resource_path("views/components/layouts/{$lowerName}.blade.php");
        $customLayout = "{$componentsPath}/layouts/{$lowerName}.blade.php";

        if (File::exists($sharedLayout)) {
            File::copy($sharedLayout, $customLayout);
        }

        // Create a README in the components dir
        $readme = <<<'MD'
# Panel Custom Components

Place your custom component overrides here. Only override what you need —
everything else falls back to the shared components in `resources/views/components/`.

## Directory Structure

```
components/
├── forms/          # Override form components
│   ├── input.blade.php
│   ├── select.blade.php
│   └── ...
├── layouts/        # Override layout components
│   └── {panel}.blade.php
├── navigation/     # Override navigation components
│   ├── sidebar.blade.php
│   └── topbar.blade.php
├── tables/         # Override table components
│   └── table.blade.php
└── ui/             # Override UI components
    ├── button.blade.php
    └── badge.blade.php
```

## How It Works

When `'components' => 'custom'` is set in `config/panels.php`,
the system checks this directory first for any Blade component.
If not found here, it falls back to the shared components.

Simply create a file with the same path as the shared component to override it.
MD;
        File::put("{$componentsPath}/README.md", $readme);

        $this->info('Custom components directory created with base structure.');
    }

    protected function createFromStub(string $stub, string $destination, array $replacements): void
    {
        $stubPath = base_path("stubs/{$stub}.stub");

        if (! File::exists($stubPath)) {
            $this->warn("Stub not found: {$stubPath}");

            return;
        }

        $content = File::get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        File::put($destination, $content);
    }
}
