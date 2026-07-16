<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Warm the module registry cache';

    public function handle(ModuleRegistry $modules): int
    {
        $modules->cache();

        $this->info('Module cache rebuilt.');

        return self::SUCCESS;
    }
}
