<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemoveWidgetCommand extends Command
{
    protected $signature = 'remove:widget {name : Widget class name (e.g. RecentOrders)}
                            {--module= : Module the widget belongs to (auto-detected if omitted)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove a dashboard widget class, view, and service provider registration';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $className = Str::endsWith($name, 'Widget') ? $name : $name.'Widget';
        $baseName = Str::before($className, 'Widget');
        $kebabName = Str::kebab($baseName);

        // Find the widget file across modules
        $module = $this->option('module');
        $widgetFile = null;
        $widgetModule = null;

        if ($module) {
            $widgetModule = Str::studly($module);
            $found = $this->findWidgetInModule($widgetModule, $className);
            if (! $found) {
                $this->error("Widget {$className} not found in module {$widgetModule}.");

                return self::FAILURE;
            }
            $widgetFile = $found;
        } else {
            $result = $this->findWidgetFile($className);
            if (! $result) {
                $this->error("Widget {$className} not found in any module.");
                $this->newLine();
                $this->line('Available widgets:');
                $this->listAvailable();

                return self::FAILURE;
            }
            $widgetFile = $result['path'];
            $widgetModule = $result['module'];
        }

        // Read widget class to determine panel and view
        $classContent = File::get($widgetFile);
        $panel = $this->extractPanel($classContent);
        $isChart = Str::contains($classContent, 'extends ChartWidget');

        // Discover artifacts
        $viewFile = resource_path("views/widgets/{$panel}/{$kebabName}.blade.php");
        $hasView = ! $isChart && File::exists($viewFile);

        $providerResult = $this->findProviderWithRegistration($widgetModule, $className);
        $providerFile = $providerResult['file'] ?? null;
        $hasRegistration = $providerResult['found'] ?? false;

        // Scan for references
        $references = $this->scanReferences($className, $widgetFile);

        // Show summary
        if (! $this->option('force')) {
            $this->newLine();
            $this->info("=== Remove Widget: {$className} ===");
            $this->newLine();

            $relativePath = Str::after(str_replace('\\', '/', $widgetFile), str_replace('\\', '/', base_path()).'/');
            $this->line('<fg=yellow>Will be removed:</>');
            $this->line("  - Class: {$relativePath}");
            if ($hasView) {
                $this->line("  - View: resources/views/widgets/{$panel}/{$kebabName}.blade.php");
            }
            if ($hasRegistration) {
                $this->line("  - Registration in: app/Modules/{$widgetModule}/Providers/ ServiceProvider");
            }

            if (! empty($references)) {
                $this->newLine();
                $this->warn('External references found (you must fix these manually):');
                foreach ($references as $ref) {
                    $this->line("  <fg=red>[{$ref['type']}]</> {$ref['file']}:{$ref['line']}");
                }
            }

            $this->newLine();
            if (! $this->confirm('Proceed with removal?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // 1. Remove class file
        File::delete($widgetFile);
        $relativePath = Str::after(str_replace('\\', '/', $widgetFile), str_replace('\\', '/', base_path()).'/');
        $this->info("Removed class: {$relativePath}");

        // 2. Remove view file
        if ($hasView) {
            File::delete($viewFile);
            $this->info("Removed view: resources/views/widgets/{$panel}/{$kebabName}.blade.php");
        }

        // 3. Clean ServiceProvider registration
        if ($hasRegistration && $providerFile) {
            $this->cleanServiceProvider($providerFile, $className, $widgetModule);
            $this->info('Cleaned ServiceProvider registration');
        }

        // 4. Clean up empty directories
        $parentDir = dirname($widgetFile);
        if (File::exists($parentDir) && count(File::allFiles($parentDir)) === 0 && count(File::directories($parentDir)) === 0) {
            File::deleteDirectory($parentDir);
        }

        $this->newLine();
        $this->info("Widget '{$className}' has been removed.");

        if (! empty($references)) {
            $this->newLine();
            $this->warn('Fix the '.count($references).' external reference(s) listed above to avoid runtime errors.');
        }

        return self::SUCCESS;
    }

    /**
     * Search a specific module for the widget file, including subdirectories.
     */
    protected function findWidgetInModule(string $module, string $className): ?string
    {
        $widgetsDir = app_path("Modules/{$module}/Widgets");

        if (! File::exists($widgetsDir)) {
            return null;
        }

        // Check root: Widgets/ClassName.php
        $rootPath = "{$widgetsDir}/{$className}.php";
        if (File::exists($rootPath)) {
            return $rootPath;
        }

        // Check subdirectories: Widgets/Admin/ClassName.php, Widgets/User/ClassName.php
        foreach (File::directories($widgetsDir) as $subDir) {
            $subPath = "{$subDir}/{$className}.php";
            if (File::exists($subPath)) {
                return $subPath;
            }
        }

        return null;
    }

    /**
     * Search all modules for the widget file, including subdirectories.
     *
     * @return array{path: string, module: string}|null
     */
    protected function findWidgetFile(string $className): ?array
    {
        $modulesDir = app_path('Modules');
        if (! File::exists($modulesDir)) {
            return null;
        }

        foreach (File::directories($modulesDir) as $moduleDir) {
            $module = basename($moduleDir);
            $path = $this->findWidgetInModule($module, $className);
            if ($path) {
                return ['path' => $path, 'module' => $module];
            }
        }

        return null;
    }

    /**
     * Extract the panel name from the widget class source code.
     */
    protected function extractPanel(string $classContent): string
    {
        if (preg_match("/function\s+panel\(\)[^{]*\{[^}]*return\s+['\"](\w+)['\"]/s", $classContent, $match)) {
            return $match[1];
        }

        return 'admin';
    }

    /**
     * Find the ServiceProvider that registers this widget.
     * First checks the widget's own module, then scans all modules.
     *
     * @return array{file: ?string, found: bool}
     */
    protected function findProviderWithRegistration(string $module, string $className): array
    {
        // Check the widget's own module first
        $providerDir = app_path("Modules/{$module}/Providers");
        $files = glob("{$providerDir}/*ServiceProvider.php");
        foreach ($files as $file) {
            if (Str::contains(File::get($file), $className)) {
                return ['file' => $file, 'found' => true];
            }
        }

        // Scan all other modules for cross-module registration
        $modulesDir = app_path('Modules');
        if (File::exists($modulesDir)) {
            foreach (File::directories($modulesDir) as $moduleDir) {
                if (basename($moduleDir) === $module) {
                    continue;
                }
                $otherFiles = glob("{$moduleDir}/Providers/*ServiceProvider.php");
                foreach ($otherFiles as $file) {
                    if (Str::contains(File::get($file), $className)) {
                        return ['file' => $file, 'found' => true];
                    }
                }
            }
        }

        return ['file' => null, 'found' => false];
    }

    protected function cleanServiceProvider(string $providerFile, string $className, string $module): void
    {
        $content = File::get($providerFile);

        // Remove use statements for the widget (any namespace path)
        $content = preg_replace("/use [^\n]*\\\\{$className};\n/", '', $content);

        // Remove registration lines in any style:
        // Style 1: $this->app->make(WidgetRegistry::class)->register(new ClassName);
        // Style 2: $registry->register(new ClassName);
        // Style 3: $registry->register(new ClassName($dependency));
        $content = preg_replace("/[ \t]*\\\$(?:this->app->make\(WidgetRegistry::class\)|registry)->register\(new {$className}[^)]*\);\n/", '', $content);

        // If the bound() block is now empty, remove it entirely
        $content = preg_replace(
            "/\s*if\s*\(\\\$this->app->bound\(WidgetRegistry::class\)\)\s*\{\s*\}/s",
            '',
            $content
        );

        // Clean up WidgetRegistry import if no longer referenced
        if (! Str::contains($content, 'WidgetRegistry::class') && ! Str::contains($content, 'WidgetRegistry ')) {
            $content = preg_replace("/use App\\\\Services\\\\WidgetRegistry;\n/", '', $content);
        }

        // Clean multiple blank lines
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($providerFile, $content);
    }

    /**
     * @return array<array{type: string, file: string, line: int}>
     */
    protected function scanReferences(string $className, string $widgetFile): array
    {
        $references = [];
        $normalizedWidgetFile = str_replace('\\', '/', $widgetFile);

        $scanPaths = [
            app_path('Modules'),
            app_path('Panels'),
            resource_path('views'),
        ];

        foreach ($scanPaths as $scanPath) {
            if (! File::exists($scanPath)) {
                continue;
            }

            foreach (File::allFiles($scanPath) as $file) {
                $filePath = str_replace('\\', '/', $file->getRealPath());

                if ($filePath === $normalizedWidgetFile) {
                    continue;
                }

                $content = File::get($file->getRealPath());

                if (Str::contains($content, $className)) {
                    $lineNumber = $this->findLineNumber($content, $className);
                    $relativePath = Str::after($filePath, str_replace('\\', '/', base_path()).'/');

                    // Skip ServiceProvider (cleaned automatically)
                    if (Str::contains($relativePath, 'ServiceProvider.php')) {
                        continue;
                    }

                    $references[] = [
                        'type' => 'Widget reference',
                        'file' => $relativePath,
                        'line' => $lineNumber,
                    ];
                }
            }
        }

        return $references;
    }

    protected function findLineNumber(string $content, string $needle): int
    {
        $lines = explode("\n", $content);
        foreach ($lines as $index => $line) {
            if (Str::contains($line, $needle)) {
                return $index + 1;
            }
        }

        return 0;
    }

    protected function listAvailable(): void
    {
        $modulesDir = app_path('Modules');
        if (! File::exists($modulesDir)) {
            $this->line('  (none found)');

            return;
        }

        $found = false;
        foreach (File::directories($modulesDir) as $moduleDir) {
            $widgetsDir = "{$moduleDir}/Widgets";
            if (! File::exists($widgetsDir)) {
                continue;
            }

            // List root-level widgets
            foreach (File::files($widgetsDir) as $file) {
                $name = $file->getFilenameWithoutExtension();
                $module = basename($moduleDir);
                $this->line("  - {$name} <fg=gray>(module: {$module})</>");
                $found = true;
            }

            // List widgets in subdirectories (Admin/, User/)
            foreach (File::directories($widgetsDir) as $subDir) {
                foreach (File::files($subDir) as $file) {
                    $name = $file->getFilenameWithoutExtension();
                    $module = basename($moduleDir);
                    $sub = basename($subDir);
                    $this->line("  - {$name} <fg=gray>(module: {$module}, dir: {$sub})</>");
                    $found = true;
                }
            }
        }

        if (! $found) {
            $this->line('  (none found)');
        }
    }
}
