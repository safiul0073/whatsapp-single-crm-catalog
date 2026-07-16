<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : The base module name}
        {--panels=admin : Comma-separated panel list}
        {--type=crud : Module type: crud or settings}
        {--api : Generate module-local API scaffolding}';

    protected $description = 'Create a self-contained module without mutating panel or config files';

    protected string $studlyName;

    protected string $singularName;

    protected string $pluralName;

    protected string $lowerName;

    protected string $variableName;

    protected string $variablePlural;

    protected string $tableName;

    public function handle(): int
    {
        $this->resolveNames($this->argument('name'));

        $type = $this->option('type') ?: 'crud';
        $panels = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('panels')))));
        $panels = $panels === [] ? ['admin'] : $panels;

        if (! in_array($type, ['crud', 'settings'], true)) {
            $this->error('Supported module types: crud, settings');

            return self::FAILURE;
        }

        $modulePath = app_path("Modules/{$this->studlyName}");
        if (File::exists($modulePath)) {
            $this->error("Module {$this->studlyName} already exists.");

            return self::FAILURE;
        }

        $this->createDirectories($modulePath, $panels, $type);
        $this->createCommonFiles($modulePath, $panels, $type);

        if ($type === 'crud') {
            $this->createCrudFiles($modulePath, $panels);
        } else {
            $this->createSettingsFiles($modulePath, $panels);
        }

        if ($this->option('api')) {
            $this->createApiFiles($modulePath);
        }

        $this->info("Module {$this->studlyName} created successfully.");
        $this->line("Generated under: app/Modules/{$this->studlyName}");
        $this->line('No shared panel routes, views, or config files were modified.');

        return self::SUCCESS;
    }

    protected function resolveNames(string $name): void
    {
        $this->studlyName = Str::studly($name);
        $this->singularName = Str::singular($this->studlyName);
        $this->pluralName = Str::plural($this->studlyName);
        $this->lowerName = Str::kebab(Str::plural($this->singularName));
        $this->variableName = Str::camel($this->singularName);
        $this->variablePlural = Str::camel($this->pluralName);
        $this->tableName = Str::snake($this->pluralName);
    }

    protected function createDirectories(string $modulePath, array $panels, string $type): void
    {
        $directories = [
            "{$modulePath}/Database/Migrations",
            "{$modulePath}/Database/Seeders",
            "{$modulePath}/Models",
            "{$modulePath}/Providers",
            "{$modulePath}/Routes",
            "{$modulePath}/Services",
            "{$modulePath}/Resources/lang/en",
            "{$modulePath}/Tests/Feature",
        ];

        if ($type === 'crud') {
            $directories[] = "{$modulePath}/Policies";
            $directories[] = "{$modulePath}/Http/Requests";
            $directories[] = "{$modulePath}/Tables";
        } else {
            $directories[] = "{$modulePath}/Config";
        }

        foreach ($panels as $panel) {
            $directories[] = "{$modulePath}/Http/Controllers/".Str::studly($panel);
            $directories[] = "{$modulePath}/Resources/views/".Str::lower($panel);
        }

        if ($this->option('api')) {
            $directories[] = "{$modulePath}/Http/Controllers/Api/V1";
            $directories[] = "{$modulePath}/Routes";
        }

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }
    }

    protected function createCommonFiles(string $modulePath, array $panels, string $type): void
    {
        $this->createFromStub('module/module.json', "{$modulePath}/module.json", $this->baseReplacements());
        $this->createFromStub('module/Module', "{$modulePath}/Module.php", array_merge($this->baseReplacements(), [
            'permissionsBlock' => $this->permissionsBlock($type),
            'policiesBlock' => $type === 'crud'
                ? "            \\App\\Modules\\{$this->studlyName}\\Models\\{$this->singularName}::class => \\App\\Modules\\{$this->studlyName}\\Policies\\{$this->singularName}Policy::class,"
                : '',
            'navigationMethods' => $this->navigationMethods($panels, $type),
        ]));
        $this->createFromStub('module/ModuleServiceProvider', "{$modulePath}/Providers/{$this->studlyName}ServiceProvider.php", $this->baseReplacements());
        $this->createFromStub('module/ModuleMessages', "{$modulePath}/Resources/lang/en/messages.php", $this->baseReplacements());
        $this->createFromStub('module/ModuleFeatureTest', "{$modulePath}/Tests/Feature/{$this->studlyName}ModuleTest.php", $this->baseReplacements());
    }

    protected function createCrudFiles(string $modulePath, array $panels): void
    {
        $timestamp = date('Y_m_d_His');

        $this->createFromStub('module/Model', "{$modulePath}/Models/{$this->singularName}.php", $this->baseReplacements());
        $this->createFromStub('module/Service', "{$modulePath}/Services/{$this->studlyName}Service.php", $this->baseReplacements());
        $this->createFromStub('module/Policy', "{$modulePath}/Policies/{$this->singularName}Policy.php", $this->baseReplacements());
        $this->createFromStub('module/Table', "{$modulePath}/Tables/{$this->studlyName}Table.php", $this->baseReplacements());
        $this->createFromStub('module/Migration', "{$modulePath}/Database/Migrations/{$timestamp}_create_{$this->tableName}_table.php", $this->baseReplacements());
        $this->createFromStub('module/Seeder', "{$modulePath}/Database/Seeders/{$this->studlyName}Seeder.php", $this->baseReplacements());
        $this->createFromStub('module/ModuleStoreRequest', "{$modulePath}/Http/Requests/Store{$this->singularName}Request.php", $this->baseReplacements());
        $this->createFromStub('module/ModuleUpdateRequest', "{$modulePath}/Http/Requests/Update{$this->singularName}Request.php", $this->baseReplacements());

        foreach ($panels as $panel) {
            $replacements = $this->panelReplacements($panel);
            $panelStudly = Str::studly($panel);
            $panelLower = Str::lower($panel);

            $this->createFromStub('module/ModuleController', "{$modulePath}/Http/Controllers/{$panelStudly}/{$this->studlyName}Controller.php", $replacements);
            $this->createFromStub('module/RoutesPanel', "{$modulePath}/Routes/{$panelLower}.php", array_merge($replacements, [
                'routeParameter' => $this->variableName,
            ]));

            $this->createFromStub('module/views/index.module.blade', "{$modulePath}/Resources/views/{$panelLower}/index.blade.php", $replacements);
            $this->createFromStub('module/views/create.module.blade', "{$modulePath}/Resources/views/{$panelLower}/create.blade.php", $replacements);
            $this->createFromStub('module/views/edit.module.blade', "{$modulePath}/Resources/views/{$panelLower}/edit.blade.php", $replacements);
            $this->createFromStub('module/views/show.module.blade', "{$modulePath}/Resources/views/{$panelLower}/show.blade.php", $replacements);
        }
    }

    protected function createSettingsFiles(string $modulePath, array $panels): void
    {
        $timestamp = date('Y_m_d_His');

        $this->createFromStub('module/settings/Model', "{$modulePath}/Models/{$this->singularName}.php", $this->baseReplacements());
        $this->createFromStub('module/settings/Service', "{$modulePath}/Services/{$this->studlyName}Service.php", $this->baseReplacements());
        $this->createFromStub('module/settings/Migration', "{$modulePath}/Database/Migrations/{$timestamp}_create_{$this->tableName}_table.php", $this->baseReplacements());
        $this->createFromStub('module/settings/Seeder', "{$modulePath}/Database/Seeders/{$this->studlyName}Seeder.php", $this->baseReplacements());
        $this->createFromStub('module/settings/Config', "{$modulePath}/Config/{$this->lowerName}.php", $this->baseReplacements());

        foreach ($panels as $panel) {
            $replacements = $this->panelReplacements($panel);
            $panelStudly = Str::studly($panel);
            $panelLower = Str::lower($panel);

            $this->createFromStub('module/ModuleSettingsController', "{$modulePath}/Http/Controllers/{$panelStudly}/{$this->studlyName}Controller.php", $replacements);
            $this->createFromStub('module/RoutesSettingsPanel', "{$modulePath}/Routes/{$panelLower}.php", $replacements);
            $this->createFromStub('module/settings/views/index.module.blade', "{$modulePath}/Resources/views/{$panelLower}/index.blade.php", $replacements);
        }
    }

    protected function createApiFiles(string $modulePath): void
    {
        $controller = <<<PHP
<?php

namespace App\Modules\\{$this->studlyName}\\Http\\Controllers\\Api\\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class {$this->singularName}Controller extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
PHP;

        $routes = <<<PHP
<?php

use App\Modules\\{$this->studlyName}\\Http\\Controllers\\Api\\V1\\{$this->singularName}Controller;
use Illuminate\Support\Facades\Route;

Route::get('{$this->lowerName}', [{$this->singularName}Controller::class, 'index'])
    ->name('{$this->lowerName}.index');
PHP;

        File::put("{$modulePath}/Http/Controllers/Api/V1/{$this->singularName}Controller.php", $controller);
        File::put("{$modulePath}/Routes/api_v1.php", $routes);
    }

    protected function permissionsBlock(string $type): string
    {
        $actions = $type === 'settings'
            ? [
                "{$this->lowerName}.view" => 'View '.$this->studlyName,
                "{$this->lowerName}.edit" => 'Edit '.$this->studlyName,
            ]
            : [
                "{$this->lowerName}.view" => 'View '.$this->pluralName,
                "{$this->lowerName}.create" => 'Create '.$this->pluralName,
                "{$this->lowerName}.edit" => 'Edit '.$this->pluralName,
                "{$this->lowerName}.delete" => 'Delete '.$this->pluralName,
            ];

        $lines = ["            'admin' => ["];
        foreach ($actions as $permission => $label) {
            $lines[] = "                '{$permission}' => '{$label}',";
        }
        $lines[] = '            ],';

        return implode("\n", $lines);
    }

    protected function navigationMethods(array $panels, string $type): string
    {
        $methods = [];

        foreach ($panels as $panel) {
            $panelStudly = Str::studly($panel);
            $group = $type === 'settings' ? 'System' : 'Management';
            $permission = "{$this->lowerName}.view";
            $label = $this->pluralName;

            $methods[] = <<<PHP
    public function {$panel}Navigation(NavigationBuilder \$navigation): void
    {
        \$navigation
            ->group('{$group}')
            ->item(label: '{$label}', route: '{$panel}.{$this->lowerName}.*')
            ->icon('ph-cube')
            ->permission('{$permission}')
            ->order(100);
    }
PHP;
        }

        return implode("\n\n", $methods);
    }

    protected function baseReplacements(): array
    {
        return [
            'studlyName' => $this->studlyName,
            'singularName' => $this->singularName,
            'pluralName' => $this->pluralName,
            'lowerName' => $this->lowerName,
            'variableName' => $this->variableName,
            'variablePlural' => $this->variablePlural,
            'tableName' => $this->tableName,
        ];
    }

    protected function panelReplacements(string $panel): array
    {
        return array_merge($this->baseReplacements(), [
            'panelStudly' => Str::studly($panel),
            'panelLower' => Str::lower($panel),
        ]);
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
            $content = str_replace("{{ {$key} }}", (string) $value, $content);
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        if (isset($replacements['routeParameter'])) {
            $content = str_replace('__ROUTE_PARAMETER__', (string) $replacements['routeParameter'], $content);
        }

        File::put($destination, $content);
    }
}
