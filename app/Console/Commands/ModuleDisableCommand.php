<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\CacheBuster;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {name : Module name or alias}';

    protected $description = 'Disable a module via runtime module-state.json overrides';

    public function handle(ModuleRegistry $modules, CacheBuster $cacheBuster): int
    {
        $module = $modules->find($this->argument('name'));

        if (! $module) {
            $this->error('Module not found.');

            return self::FAILURE;
        }

        foreach ($modules->enabled() as $candidate) {
            if (in_array($module['alias'], $candidate['requires'], true)) {
                $this->error("Cannot disable {$module['name']}: [{$candidate['name']}] depends on it.");

                return self::FAILURE;
            }
        }

        $modules->setRuntimeState($module['alias'], false);
        $cacheBuster->run();

        $this->info("Disabled module [{$module['name']}].");

        return self::SUCCESS;
    }
}
