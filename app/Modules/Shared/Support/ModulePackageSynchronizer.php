<?php

namespace App\Modules\Shared\Support;

use InvalidArgumentException;

class ModulePackageSynchronizer
{
    public function synchronize(array $composer, array $modules): array
    {
        $desired = $this->desiredPackages($modules);
        $managed = $this->managedPackages($composer);

        $conflicts = array_merge(
            $desired['conflicts'],
            $this->crossSectionConflicts($desired['packages'])
        );

        if ($conflicts !== []) {
            return [
                'composer' => $composer,
                'changes' => ['require' => [], 'require-dev' => []],
                'conflicts' => $conflicts,
                'desired' => $desired['packages'],
            ];
        }

        $nextComposer = $composer;
        $changes = [
            'require' => ['added' => [], 'updated' => [], 'removed' => []],
            'require-dev' => ['added' => [], 'updated' => [], 'removed' => []],
        ];

        foreach (['require', 'require-dev'] as $section) {
            $current = $composer[$section] ?? [];
            $current = is_array($current) ? $current : [];
            $previouslyManaged = array_fill_keys($managed[$section], true);
            $desiredPackages = $desired['packages'][$section];

            foreach ($current as $package => $constraint) {
                if (isset($previouslyManaged[$package]) && ! isset($desiredPackages[$package])) {
                    $changes[$section]['removed'][$package] = $constraint;
                    unset($current[$package]);
                }
            }

            foreach ($desiredPackages as $package => $constraint) {
                if (! array_key_exists($package, $current)) {
                    $changes[$section]['added'][$package] = $constraint;
                    $current[$package] = $constraint;

                    continue;
                }

                if ($current[$package] !== $constraint) {
                    $changes[$section]['updated'][$package] = [
                        'from' => $current[$package],
                        'to' => $constraint,
                    ];
                    $current[$package] = $constraint;
                }
            }

            ksort($current);
            $nextComposer[$section] = $current;
        }

        $nextComposer['extra'] ??= [];
        $nextComposer['extra']['module-package-sync'] = [
            'managed' => [
                'require' => array_keys($desired['packages']['require']),
                'require-dev' => array_keys($desired['packages']['require-dev']),
            ],
            'owners' => $desired['owners'],
        ];

        return [
            'composer' => $nextComposer,
            'changes' => $changes,
            'conflicts' => [],
            'desired' => $desired['packages'],
        ];
    }

    protected function desiredPackages(array $modules): array
    {
        $packages = [
            'require' => [],
            'require-dev' => [],
        ];
        $owners = [
            'require' => [],
            'require-dev' => [],
        ];
        $conflicts = [];

        foreach ($modules as $module) {
            $manifest = $module['manifest'] ?? null;
            if (! $manifest instanceof ModuleManifest) {
                continue;
            }

            foreach (['require', 'require-dev'] as $section) {
                foreach ($manifest->packages[$section] ?? [] as $package => $constraint) {
                    if (isset($packages[$section][$package]) && $packages[$section][$package] !== $constraint) {
                        $owners[$section][$package][] = $module['alias'];

                        $conflicts[] = sprintf(
                            'Package [%s] has conflicting %s constraints: [%s] from modules [%s].',
                            $package,
                            $section,
                            implode(', ', array_unique(array_merge(
                                [$packages[$section][$package], $constraint]
                            ))),
                            implode(', ', array_unique(array_merge($owners[$section][$package] ?? [], [$module['alias']])))
                        );

                        continue;
                    }

                    $packages[$section][$package] = $constraint;
                    $owners[$section][$package] ??= [];
                    $owners[$section][$package][] = $module['alias'];
                }
            }
        }

        foreach (['require', 'require-dev'] as $section) {
            foreach ($owners[$section] as $package => $moduleOwners) {
                sort($moduleOwners);
                $owners[$section][$package] = $moduleOwners;
            }

            ksort($packages[$section]);
            ksort($owners[$section]);
        }

        return [
            'packages' => $packages,
            'owners' => $owners,
            'conflicts' => $conflicts,
        ];
    }

    protected function managedPackages(array $composer): array
    {
        $managed = $composer['extra']['module-package-sync']['managed'] ?? [];

        return [
            'require' => $this->normalizeManagedSection($managed['require'] ?? []),
            'require-dev' => $this->normalizeManagedSection($managed['require-dev'] ?? []),
        ];
    }

    protected function normalizeManagedSection(array $packages): array
    {
        $normalized = array_values(array_filter($packages, static fn ($package): bool => is_string($package) && trim($package) !== ''));
        sort($normalized);

        return $normalized;
    }

    protected function crossSectionConflicts(array $packages): array
    {
        $conflicts = [];
        $duplicates = array_intersect(array_keys($packages['require']), array_keys($packages['require-dev']));

        foreach ($duplicates as $package) {
            $conflicts[] = sprintf(
                'Package [%s] is declared in both require [%s] and require-dev [%s].',
                $package,
                $packages['require'][$package],
                $packages['require-dev'][$package]
            );
        }

        return $conflicts;
    }

    public function encodeComposer(array $composer): string
    {
        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new InvalidArgumentException('Unable to encode composer.json payload.');
        }

        return $json.PHP_EOL;
    }
}
