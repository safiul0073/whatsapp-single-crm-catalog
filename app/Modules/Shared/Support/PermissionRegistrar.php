<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PermissionRegistrar
{
    public function __construct(
        protected ModuleRegistry $modules
    ) {}

    public function permissions(): array
    {
        $modernPermissions = Collection::make($this->modules->enabledDescriptors())
            ->flatMap(function (array $module): array {
                return $this->normalizeDescriptorPermissions(
                    $module['alias'],
                    $module['descriptor']->permissions()
                );
            })
            ->keyBy(fn (array $permission): string => $permission['guard'].'|'.$permission['name']);

        $shellPermissions = Collection::make(config('permissions.modules', []))
            ->reject(function (array $config, string $module) use ($modernPermissions): bool {
                return $modernPermissions->contains(fn (array $permission): bool => $permission['module'] === $module);
            })
            ->flatMap(function (array $config, string $module): array {
                $guard = Arr::get($config, 'guard', 'web');
                $prefix = Arr::get($config, 'prefix', $module);

                return array_map(function (string $action) use ($guard, $module, $prefix): array {
                    return [
                        'name' => "{$prefix}.{$action}",
                        'guard' => $guard,
                        'label' => null,
                        'module' => $module,
                    ];
                }, Arr::get($config, 'permissions', []));
            });

        return $modernPermissions
            ->merge($shellPermissions->keyBy(fn (array $permission): string => $permission['guard'].'|'.$permission['name']))
            ->values()
            ->all();
    }

    public function permissionsForGuard(string $guard): array
    {
        return array_values(array_map(
            static fn (array $permission): string => $permission['name'],
            array_filter($this->permissions(), static fn (array $permission): bool => $permission['guard'] === $guard)
        ));
    }

    protected function normalizeDescriptorPermissions(string $moduleAlias, array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $guard => $definitions) {
            if (! is_array($definitions)) {
                continue;
            }

            foreach ($definitions as $permission => $label) {
                if (is_int($permission)) {
                    $permission = $label;
                    $label = null;
                }

                $normalized[] = [
                    'name' => (string) $permission,
                    'guard' => (string) $guard,
                    'label' => is_string($label) ? $label : null,
                    'module' => $moduleAlias,
                ];
            }
        }

        return $normalized;
    }
}
