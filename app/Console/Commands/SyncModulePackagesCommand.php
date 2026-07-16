<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\ModulePackageSynchronizer;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncModulePackagesCommand extends Command
{
    protected $signature = 'module:sync-packages
        {--write : Persist the synchronized package list to composer.json}
        {--check : Exit with failure when composer.json is out of sync}';

    protected $description = 'Synchronize root composer requirements from enabled module package declarations';

    public function handle(ModuleRegistry $modules, ModulePackageSynchronizer $synchronizer): int
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(File::get($composerPath), true);

        if (! is_array($composer)) {
            $this->error('Unable to decode composer.json.');

            return self::FAILURE;
        }

        $result = $synchronizer->synchronize($composer, $modules->enabled());

        if ($result['conflicts'] !== []) {
            $this->error('Module package declarations have conflicts:');
            foreach ($result['conflicts'] as $conflict) {
                $this->line("  - {$conflict}");
            }

            return self::FAILURE;
        }

        $hasChanges = $this->hasChanges($result['changes']);

        $this->displayChanges($result['changes']);

        if ($this->option('check')) {
            if ($hasChanges) {
                $this->error('composer.json is out of sync with enabled module package declarations.');

                return self::FAILURE;
            }

            $this->info('composer.json is already in sync with enabled module package declarations.');

            return self::SUCCESS;
        }

        if (! $this->option('write')) {
            $this->warn('Dry run only. Re-run with --write to update composer.json.');

            return self::SUCCESS;
        }

        if (! $hasChanges) {
            $this->info('composer.json is already in sync with enabled module package declarations.');

            return self::SUCCESS;
        }

        File::put($composerPath, $synchronizer->encodeComposer($result['composer']));

        $this->info('composer.json synchronized.');
        $this->line('Run `composer update` to install or remove the synchronized packages.');

        return self::SUCCESS;
    }

    protected function hasChanges(array $changes): bool
    {
        foreach (['require', 'require-dev'] as $section) {
            foreach (['added', 'updated', 'removed'] as $operation) {
                if (($changes[$section][$operation] ?? []) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function displayChanges(array $changes): void
    {
        foreach (['require', 'require-dev'] as $section) {
            $this->line("<info>{$section}</info>");

            if (($changes[$section]['added'] ?? []) === [] && ($changes[$section]['updated'] ?? []) === [] && ($changes[$section]['removed'] ?? []) === []) {
                $this->line('  - no changes');

                continue;
            }

            foreach ($changes[$section]['added'] ?? [] as $package => $constraint) {
                $this->line("  + {$package}: {$constraint}");
            }

            foreach ($changes[$section]['updated'] ?? [] as $package => $change) {
                $this->line("  ~ {$package}: {$change['from']} -> {$change['to']}");
            }

            foreach ($changes[$section]['removed'] ?? [] as $package => $constraint) {
                $this->line("  - {$package}: {$constraint}");
            }
        }
    }
}
