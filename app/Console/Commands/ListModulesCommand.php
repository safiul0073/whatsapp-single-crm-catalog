<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ListModulesCommand extends Command
{
    protected $signature = 'module:list';

    protected $aliases = ['modules:list'];

    protected $description = 'List all discovered modules and their runtime state';

    public function handle(ModuleRegistry $modules): int
    {
        $rows = array_map(function (array $module): array {
            return [
                'Name' => $module['name'],
                'Alias' => $module['alias'],
                'Active' => $module['active'] ? 'yes' : 'no',
                'Requires' => implode(', ', $module['requires']),
                'Providers' => count($module['providers']),
                'Path' => str_replace(base_path().'\\', '', $module['module_path']),
            ];
        }, $modules->all());

        if ($rows === []) {
            $this->warn('No modules were discovered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Alias', 'Active', 'Requires', 'Providers', 'Path'], $rows);

        return self::SUCCESS;
    }
}
