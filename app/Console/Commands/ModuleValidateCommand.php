<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleValidateCommand extends Command
{
    protected $signature = 'module:validate {name? : Optional module name or alias}';

    protected $description = 'Validate module manifests, descriptors, and duplicate permissions';

    public function handle(ModuleRegistry $modules): int
    {
        $targets = $this->argument('name')
            ? array_filter([$modules->find($this->argument('name'))])
            : $modules->all();

        if ($targets === []) {
            $this->error('No matching modules found.');

            return self::FAILURE;
        }

        $seenPermissions = [];

        foreach ($targets as $module) {
            if (! $module['descriptor'] instanceof BasePanelModule) {
                $this->error("Module [{$module['name']}] does not provide a valid descriptor.");

                return self::FAILURE;
            }

            foreach ($module['descriptor']->permissions() as $guard => $definitions) {
                foreach ($definitions as $permission => $label) {
                    if (is_int($permission)) {
                        $permission = $label;
                    }

                    $key = $guard.'|'.$permission;
                    if (isset($seenPermissions[$key])) {
                        $this->error("Duplicate permission [{$permission}] for guard [{$guard}] in modules [{$seenPermissions[$key]}] and [{$module['name']}].");

                        return self::FAILURE;
                    }

                    $seenPermissions[$key] = $module['name'];
                }
            }
        }

        $this->info('Module validation passed.');

        return self::SUCCESS;
    }
}
