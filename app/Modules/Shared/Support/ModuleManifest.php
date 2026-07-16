<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ModuleManifest
{
    public function __construct(
        public readonly string $name,
        public readonly string $alias,
        public readonly string $description,
        public readonly string $version,
        public readonly int $priority,
        public readonly array $providers,
        public readonly array $requires,
        public readonly array $packages,
        public readonly bool $active,
        public readonly string $modulePath,
        public readonly ?string $manifestPath = null,
    ) {}

    public static function fromFile(string $manifestPath, string $modulePath): self
    {
        if (! File::exists($manifestPath)) {
            throw new InvalidArgumentException("Module manifest not found: {$manifestPath}");
        }

        $decoded = json_decode(File::get($manifestPath), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Invalid module manifest JSON: {$manifestPath}");
        }

        return self::fromArray($decoded, $modulePath, $manifestPath);
    }

    public static function fromArray(array $data, string $modulePath, ?string $manifestPath = null): self
    {
        $name = Arr::get($data, 'name');

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Module manifest requires a non-empty "name".');
        }

        $alias = Arr::get($data, 'alias', Str::kebab($name));
        if (! is_string($alias) || $alias === '') {
            throw new InvalidArgumentException("Module [{$name}] has an invalid \"alias\".");
        }

        $providers = Arr::get($data, 'providers', []);
        if (! is_array($providers)) {
            throw new InvalidArgumentException("Module [{$name}] has an invalid \"providers\" list.");
        }

        $requires = array_map(
            static fn (string $dependency): string => Str::kebab($dependency),
            array_values(array_filter(Arr::get($data, 'requires', []), 'is_string'))
        );

        $packages = Arr::get($data, 'packages', []);
        if (! is_array($packages)) {
            throw new InvalidArgumentException("Module [{$name}] has an invalid \"packages\" map.");
        }

        $normalizedPackages = [];
        foreach (['require', 'require-dev'] as $type) {
            $definitions = Arr::get($packages, $type, []);

            if (! is_array($definitions)) {
                throw new InvalidArgumentException("Module [{$name}] has an invalid \"packages.{$type}\" map.");
            }

            $normalizedPackages[$type] = [];

            foreach ($definitions as $package => $constraint) {
                if (! is_string($package) || trim($package) === '') {
                    throw new InvalidArgumentException("Module [{$name}] has an invalid package name in \"packages.{$type}\".");
                }

                if (! is_string($constraint) || trim($constraint) === '') {
                    throw new InvalidArgumentException("Module [{$name}] has an invalid constraint for package [{$package}] in \"packages.{$type}\".");
                }

                $normalizedPackages[$type][trim($package)] = trim($constraint);
            }

            ksort($normalizedPackages[$type]);
        }

        return new self(
            name: $name,
            alias: Str::kebab($alias),
            description: (string) Arr::get($data, 'description', ''),
            version: (string) Arr::get($data, 'version', '1.0.0'),
            priority: (int) Arr::get($data, 'priority', 0),
            providers: array_values(array_filter($providers, 'is_string')),
            requires: $requires,
            packages: $normalizedPackages,
            active: (bool) Arr::get($data, 'active', true),
            modulePath: $modulePath,
            manifestPath: $manifestPath,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'description' => $this->description,
            'version' => $this->version,
            'priority' => $this->priority,
            'providers' => $this->providers,
            'requires' => $this->requires,
            'packages' => $this->packages,
            'active' => $this->active,
            'module_path' => $this->modulePath,
            'manifest_path' => $this->manifestPath,
        ];
    }
}
