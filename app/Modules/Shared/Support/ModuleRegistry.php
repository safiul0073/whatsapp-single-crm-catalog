<?php

namespace App\Modules\Shared\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleRegistry
{
    protected ?array $modules = null;

    public function __construct(
        protected Application $app
    ) {}

    public function registerModules(): void
    {
        foreach ($this->enabled() as $module) {
            foreach ($module['helpers'] as $helper) {
                require_once $helper;
            }

            foreach ($module['providers'] as $providerClass) {
                $this->app->register($providerClass);
            }
        }
    }

    public function all(): array
    {
        return $this->modules ??= $this->loadModules();
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (array $module): bool => $module['active']));
    }

    public function enabledDescriptors(): array
    {
        return array_values(array_filter(
            $this->enabled(),
            static fn (array $module): bool => $module['descriptor'] instanceof BasePanelModule
        ));
    }

    public function descriptorFor(string $alias): ?BasePanelModule
    {
        foreach ($this->enabledDescriptors() as $module) {
            if ($module['alias'] === Str::kebab($alias)) {
                return $module['descriptor'];
            }
        }

        return null;
    }

    public function buildNavigation(string $panel): array
    {
        $items = [];

        foreach ($this->enabledDescriptors() as $module) {
            $builder = new NavigationBuilder;
            $module['descriptor']->navigation($panel, $builder);
            $items = array_merge($items, $builder->toArray());
        }

        usort($items, function (array $left, array $right): int {
            $order = ($left['order'] ?? 9999) <=> ($right['order'] ?? 9999);

            if ($order !== 0) {
                return $order;
            }

            return strcmp($left['label'] ?? '', $right['label'] ?? '');
        });

        return $items;
    }

    public function find(string $nameOrAlias): ?array
    {
        $needle = Str::kebab($nameOrAlias);

        foreach ($this->all() as $module) {
            if ($module['alias'] === $needle || Str::kebab($module['name']) === $needle) {
                return $module;
            }
        }

        return null;
    }

    public function cache(): array
    {
        $modules = $this->discoverModules();
        $payload = [
            'fingerprint' => $this->fingerprint(),
            'modules' => array_map(fn (array $module): array => $this->serializeModule($module), $modules),
        ];

        File::ensureDirectoryExists(dirname($this->cachePath()));
        File::put($this->cachePath(), '<?php return '.var_export($payload, true).';');

        $this->modules = $this->hydrateCachedModules($payload['modules']);

        return $this->modules;
    }

    public function clearCache(): void
    {
        if (File::exists($this->cachePath())) {
            File::delete($this->cachePath());
        }

        $this->modules = null;
    }

    public function runtimeState(): array
    {
        if (! File::exists($this->statePath())) {
            return [];
        }

        $decoded = json_decode(File::get($this->statePath()), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setRuntimeState(string $alias, bool $active): void
    {
        $state = $this->runtimeState();
        $state[Str::kebab($alias)] = $active;

        File::ensureDirectoryExists(dirname($this->statePath()));
        File::put($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->modules = null;
    }

    public function forgetRuntimeState(string $alias): void
    {
        $state = $this->runtimeState();
        unset($state[Str::kebab($alias)]);

        File::ensureDirectoryExists(dirname($this->statePath()));
        File::put($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->modules = null;
    }

    public function cachePath(): string
    {
        return base_path('bootstrap/cache/modules.php');
    }

    public function statePath(): string
    {
        return storage_path('app/module-state.json');
    }

    protected function loadModules(): array
    {
        $cachePath = $this->cachePath();

        if (File::exists($cachePath)) {
            $payload = require $cachePath;

            if (
                is_array($payload)
                && Arr::get($payload, 'fingerprint') === $this->fingerprint()
                && is_array(Arr::get($payload, 'modules'))
            ) {
                return $this->hydrateCachedModules($payload['modules']);
            }
        }

        return $this->discoverModules();
    }

    protected function discoverModules(): array
    {
        $modulesPath = app_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            return [];
        }

        $runtimeState = $this->runtimeState();
        $discovered = [];

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = basename($modulePath);
            $manifestPath = $modulePath.'/module.json';
            $descriptorPath = $modulePath.'/Module.php';
            if (! File::exists($manifestPath) || ! File::exists($descriptorPath)) {
                throw new RuntimeException("Module [{$moduleName}] must contain both module.json and Module.php.");
            }

            $manifest = ModuleManifest::fromFile($manifestPath, $modulePath);
            $descriptorClass = "App\\Modules\\{$moduleName}\\Module";
            $descriptor = null;

            if (class_exists($descriptorClass)) {
                $descriptor = $this->app->make($descriptorClass);
            }

            if (! $descriptor instanceof BasePanelModule) {
                throw new RuntimeException("Module [{$moduleName}] must expose App\\Modules\\{$moduleName}\\Module extending BasePanelModule.");
            }

            $moduleAlias = $manifest->alias;
            $providerClasses = $manifest->providers;
            $requires = $manifest->requires;
            $priority = $manifest->priority;
            $active = $runtimeState[$moduleAlias] ?? $manifest->active;

            $discovered[] = [
                'name' => $manifest->name,
                'alias' => $moduleAlias,
                'manifest' => $manifest,
                'module_path' => $modulePath,
                'providers' => $providerClasses,
                'helpers' => File::glob($modulePath.'/Helpers/*.php') ?: [],
                'routes' => $this->routeFiles($modulePath),
                'descriptor_class' => $descriptor ? $descriptor::class : null,
                'descriptor' => $descriptor,
                'requires' => array_map(static fn (string $dependency): string => Str::kebab($dependency), $requires),
                'priority' => $priority,
                'active' => $active,
            ];
        }

        return $this->sortModules($discovered);
    }

    protected function routeFiles(string $modulePath): array
    {
        $routes = [];

        foreach (File::glob($modulePath.'/Routes/*.php') ?: [] as $path) {
            $routes[Str::lower(basename($path, '.php'))] = $path;
        }

        return $routes;
    }

    protected function sortModules(array $modules): array
    {
        $indexed = [];
        foreach ($modules as $module) {
            $indexed[$module['alias']] = $module;
        }

        $sorted = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $alias) use (&$visit, &$sorted, &$visiting, &$visited, $indexed): void {
            if (isset($visited[$alias])) {
                return;
            }

            if (isset($visiting[$alias])) {
                throw new RuntimeException("Circular module dependency detected at [{$alias}].");
            }

            if (! isset($indexed[$alias])) {
                return;
            }

            if (! $indexed[$alias]['active']) {
                $visited[$alias] = true;
                $sorted[] = $indexed[$alias];

                return;
            }

            $visiting[$alias] = true;

            foreach ($indexed[$alias]['requires'] as $dependency) {
                if (! isset($indexed[$dependency]) || ! $indexed[$dependency]['active']) {
                    throw new RuntimeException("Module [{$indexed[$alias]['name']}] requires missing or disabled module [{$dependency}].");
                }

                $visit($dependency);
            }

            unset($visiting[$alias]);
            $visited[$alias] = true;
            $sorted[] = $indexed[$alias];
        };

        uasort($indexed, function (array $left, array $right): int {
            $priority = $left['priority'] <=> $right['priority'];

            if ($priority !== 0) {
                return $priority;
            }

            return strcmp($left['name'], $right['name']);
        });

        foreach (array_keys($indexed) as $alias) {
            $visit($alias);
        }

        return $sorted;
    }

    protected function fingerprint(): string
    {
        $parts = [];

        foreach (File::directories(app_path('Modules')) as $modulePath) {
            foreach (['module.json', 'Module.php'] as $file) {
                $path = $modulePath.'/'.$file;
                $parts[] = $path.'|'.(File::exists($path) ? File::lastModified($path) : 0);
            }

            foreach (File::glob($modulePath.'/Providers/*.php') ?: [] as $path) {
                $parts[] = $path.'|'.File::lastModified($path);
            }
        }

        return sha1(implode(';', $parts));
    }

    protected function serializeModule(array $module): array
    {
        return [
            'name' => $module['name'],
            'alias' => $module['alias'],
            'manifest' => $module['manifest']->toArray(),
            'module_path' => $module['module_path'],
            'providers' => $module['providers'],
            'helpers' => $module['helpers'],
            'routes' => $module['routes'],
            'descriptor_class' => $module['descriptor_class'],
            'requires' => $module['requires'],
            'priority' => $module['priority'],
            'active' => $module['active'],
        ];
    }

    protected function hydrateCachedModules(array $cachedModules): array
    {
        return array_map(function (array $module): array {
            $descriptor = null;

            if (! empty($module['descriptor_class']) && class_exists($module['descriptor_class'])) {
                $descriptor = $this->app->make($module['descriptor_class']);
            }

            return [
                'name' => $module['name'],
                'alias' => $module['alias'],
                'manifest' => ModuleManifest::fromArray(
                    Arr::except($module['manifest'], ['module_path', 'manifest_path']),
                    $module['manifest']['module_path'] ?? $module['module_path'],
                    $module['manifest']['manifest_path'] ?? null
                ),
                'module_path' => $module['module_path'],
                'providers' => $module['providers'],
                'helpers' => $module['helpers'],
                'routes' => $module['routes'],
                'descriptor_class' => $module['descriptor_class'],
                'descriptor' => $descriptor,
                'requires' => $module['requires'],
                'priority' => $module['priority'],
                'active' => $module['active'],
            ];
        }, $cachedModules);
    }
}
