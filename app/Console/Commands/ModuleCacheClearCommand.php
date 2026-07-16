<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleCacheClearCommand extends Command
{
    protected $signature = 'module:cache:clear';

    protected $description = 'Clear the module registry cache';

    public function handle(ModuleRegistry $modules): int
    {
        $modules->clearCache();

        $this->info('Module cache cleared.');

        return self::SUCCESS;
    }
}
