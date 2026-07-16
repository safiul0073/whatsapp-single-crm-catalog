<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class BasePanelModuleProvider extends ServiceProvider
{
    public function boot(): void
    {
        $module = $this->resolveModule();

        if (! $module || ! $module['active']) {
            return;
        }

        $this->registerModuleConfig($module);
        $this->registerModuleViews($module);
        $this->registerModuleTranslations($module);
        $this->registerModuleMigrations($module);
        $this->registerModulePolicies($module);
        $this->registerModuleRoutes($module);

        $this->bootModule($module);
    }

    protected function bootModule(array $module): void
    {
        //
    }

    protected function resolveModule(): ?array
    {
        $moduleName = Str::before(Str::after(static::class, 'App\\Modules\\'), '\\');

        return app(ModuleRegistry::class)->find($moduleName);
    }

    protected function registerModuleConfig(array $module): void
    {
        foreach (glob($module['module_path'].'/Config/*.php') ?: [] as $configFile) {
            $this->mergeConfigFrom($configFile, basename($configFile, '.php'));
        }
    }

    protected function registerModuleViews(array $module): void
    {
        $viewsPath = $module['module_path'].'/Resources/views';

        if (! is_dir($viewsPath)) {
            return;
        }

        $this->loadViewsFrom($viewsPath, $module['alias']);

        $componentsPath = $viewsPath.'/components';
        if (is_dir($componentsPath)) {
            Blade::anonymousComponentPath($componentsPath, $module['alias']);
        }
    }

    protected function registerModuleTranslations(array $module): void
    {
        $langPath = $module['module_path'].'/Resources/lang';

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $module['alias']);
        }
    }

    protected function registerModuleMigrations(array $module): void
    {
        $migrationPath = $module['module_path'].'/Database/Migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }

    protected function registerModulePolicies(array $module): void
    {
        if (! $module['descriptor'] instanceof BasePanelModule) {
            return;
        }

        foreach ($module['descriptor']->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    protected function registerModuleRoutes(array $module): void
    {
        foreach (config('panels', []) as $panelKey => $panelConfig) {
            $routePath = $module['routes'][$panelKey] ?? null;

            if (! $routePath || empty($panelConfig['active'])) {
                continue;
            }

            Route::middleware($panelConfig['middleware'] ?? ['web'])
                ->prefix($panelConfig['prefix'] ?? $panelKey)
                ->name($panelKey.'.')
                ->group($routePath);
        }

        if (! empty($module['routes']['web'])) {
            Route::middleware('web')->group($module['routes']['web']);
        }

        foreach ($this->apiRouteGroups($module['routes'] ?? []) as $group) {
            Route::middleware('api')
                ->prefix($group['prefix'])
                ->as($group['name'])
                ->group($group['path']);
        }
    }

    /**
     * @param  array<string, string>  $routes
     * @return array<int, array{path: string, prefix: string, name: string}>
     */
    protected function apiRouteGroups(array $routes): array
    {
        $groups = [];

        if (! empty($routes['api'])) {
            $groups[] = [
                'path' => $routes['api'],
                'prefix' => 'api',
                'name' => 'api.',
            ];
        }

        foreach ($routes as $key => $path) {
            if (! preg_match('/^api_v(\d+)$/', $key, $matches)) {
                continue;
            }

            $version = 'v'.$matches[1];

            $groups[] = [
                'path' => $path,
                'prefix' => 'api/'.$version,
                'name' => 'api.'.$version.'.',
            ];
        }

        usort($groups, function (array $left, array $right): int {
            return strcmp($left['prefix'], $right['prefix']);
        });

        return $groups;
    }
}
