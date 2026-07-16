<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeWidgetCommand extends Command
{
    protected $signature = 'make:widget {name : Widget class name (e.g. RecentOrders)}
        {--module=Shared : Module to place the widget in}
        {--panel=admin : Target panel: admin, user, or all}
        {--width=half : Widget width: full, half, or quarter}
        {--position=50 : Sort position (lower = first)}
        {--chart : Create a chart widget (extends ChartWidget)}
        {--chart-type=area : Chart type: area, line, bar, donut, pie, radialBar}';

    protected $description = 'Create a new dashboard widget';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->option('module'));
        $panel = $this->option('panel');
        $width = $this->option('width');
        $position = (int) $this->option('position');
        $isChart = $this->option('chart');
        $chartType = $this->option('chart-type');

        $widgetId = $panel.'-'.Str::kebab($name);
        $widgetTitle = Str::headline($name);
        $className = $name.'Widget';

        // Paths
        $widgetDir = app_path("Modules/{$module}/Widgets");
        $widgetFile = "{$widgetDir}/{$className}.php";
        $viewDir = resource_path("views/widgets/{$panel}");
        $viewFile = "{$viewDir}/".Str::kebab($name).'.blade.php';
        $viewPath = "widgets.{$panel}.".Str::kebab($name);

        // Check if already exists
        if (File::exists($widgetFile)) {
            $this->error("Widget {$className} already exists at {$widgetFile}");

            return self::FAILURE;
        }

        // Choose stub
        $stub = $isChart ? 'ChartWidget' : 'Widget';
        $stubPath = base_path("stubs/widget/{$stub}.stub");

        if (! File::exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");

            return self::FAILURE;
        }

        // Build replacements
        $replacements = [
            '{{ moduleName }}' => $module,
            '{{ className }}' => $className,
            '{{ widgetId }}' => $widgetId,
            '{{ widgetTitle }}' => $widgetTitle,
            '{{ viewPath }}' => $viewPath,
            '{{ position }}' => $position,
            '{{ width }}' => $width,
            '{{ panel }}' => $panel,
            '{{ chartType }}' => $chartType,
        ];

        // Create widget class
        File::ensureDirectoryExists($widgetDir);
        $content = File::get($stubPath);
        foreach ($replacements as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        File::put($widgetFile, $content);
        $this->info("Created widget: {$widgetFile}");

        // Create view (only for non-chart widgets — chart uses shared partial)
        if (! $isChart) {
            File::ensureDirectoryExists($viewDir);
            $viewContent = File::get(base_path('stubs/widget/view.blade.stub'));
            $viewContent = str_replace('{{ widgetTitle }}', $widgetTitle, $viewContent);
            File::put($viewFile, $viewContent);
            $this->info("Created view:   {$viewFile}");
        }

        // Register in module ServiceProvider
        $this->registerInServiceProvider($module, $className);

        $this->newLine();
        $this->info('Widget created successfully!');
        $this->newLine();

        $this->line("  <fg=cyan>Class:</> App\\Modules\\{$module}\\Widgets\\{$className}");
        $this->line("  <fg=cyan>Panel:</> {$panel}");
        $this->line("  <fg=cyan>Width:</> {$width}");
        $this->line("  <fg=cyan>Position:</> {$position}");

        if ($isChart) {
            $this->line("  <fg=cyan>Chart:</> {$chartType}");
            $this->line('  <fg=cyan>View:</>  widgets.partials.chart (shared)');
        } else {
            $this->line("  <fg=cyan>View:</>  {$viewPath}");
        }

        $this->newLine();
        $this->line("  Register in your module's ServiceProvider boot():");
        $this->line("  <fg=yellow>\$this->app->make(WidgetRegistry::class)->register(new {$className});</>");

        return self::SUCCESS;
    }

    protected function registerInServiceProvider(string $module, string $className): void
    {
        $providerDir = app_path("Modules/{$module}/Providers");
        $providerFiles = glob("{$providerDir}/*ServiceProvider.php");

        if (empty($providerFiles)) {
            $this->warn("No ServiceProvider found in {$providerDir}. Register the widget manually.");

            return;
        }

        $providerFile = $providerFiles[0];
        $content = File::get($providerFile);

        $widgetClass = "App\\Modules\\{$module}\\Widgets\\{$className}";
        $shortClass = $className;

        // Check if already registered
        if (Str::contains($content, $shortClass)) {
            $this->line('  Widget already referenced in ServiceProvider.');

            return;
        }

        // Add use statement
        $useStatement = "use App\\Modules\\{$module}\\Widgets\\{$shortClass};";
        $registryUse = 'use App\\Services\\WidgetRegistry;';

        if (! Str::contains($content, $registryUse)) {
            // Add both use statements after namespace line
            $content = preg_replace(
                '/(namespace [^;]+;)/',
                "$1\n\n{$useStatement}\n{$registryUse}",
                $content,
                1
            );
        } else {
            // Just add the widget use statement
            $content = str_replace($registryUse, "{$useStatement}\n{$registryUse}", $content);
        }

        // Add registration in boot()
        $registrationCode = "\n        if (\$this->app->bound(WidgetRegistry::class)) {\n"
            ."            \$this->app->make(WidgetRegistry::class)->register(new {$shortClass});\n"
            .'        }';

        // Find boot() method and add code
        if (preg_match('/public function boot\(\): void\s*\{([^}]*)\}/', $content, $matches)) {
            $bootBody = $matches[1];

            // Check if there's already widget registration code
            if (Str::contains($bootBody, 'WidgetRegistry::class')) {
                // Add before the closing }
                $newRegistration = "            \$this->app->make(WidgetRegistry::class)->register(new {$shortClass});";

                // Find the last register() call and add after it
                $lastPos = strrpos($content, '->register(new ');
                if ($lastPos !== false) {
                    $lineEnd = strpos($content, "\n", $lastPos);
                    $content = substr($content, 0, $lineEnd + 1)
                        .$newRegistration."\n"
                        .substr($content, $lineEnd + 1);
                }
            } else {
                // No existing widget code — add the full block
                $content = preg_replace(
                    '/(public function boot\(\): void\s*\{)/',
                    "$1{$registrationCode}\n",
                    $content,
                    1
                );
            }
        }

        File::put($providerFile, $content);
        $this->info("Registered in:  {$providerFile}");
    }
}
