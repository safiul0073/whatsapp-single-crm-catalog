<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\CacheBuster;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {name : Module name or alias}';

    protected $description = 'Enable a module via runtime module-state.json overrides';

    public function handle(ModuleRegistry $modules, CacheBuster $cacheBuster): int
    {
        $module = $modules->find($this->argument('name'));

        if (! $module) {
            $this->error('Module not found.');

            return self::FAILURE;
        }

        foreach ($module['requires'] as $dependency) {
            $required = $modules->find($dependency);

            if (! $required || ! $required['active']) {
                $this->error("Cannot enable {$module['name']}: dependency [{$dependency}] is missing or disabled.");

                return self::FAILURE;
            }
        }

        $modules->setRuntimeState($module['alias'], true);
        $cacheBuster->run();

        $this->info("Enabled module [{$module['name']}].");

        return self::SUCCESS;
    }
}
