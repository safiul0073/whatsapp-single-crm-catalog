<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RemoveModuleCommand extends Command
{
    protected $signature = 'remove:module {name : The name of the module}';

    protected $description = 'Frozen command placeholder for the retired legacy module removal workflow';

    public function handle(): int
    {
        $this->error('remove:module is frozen.');
        $this->line('The old removal workflow assumed panel-owned routes, requests, and views.');
        $this->line('Update this command for the isolated module runtime before using it again.');

        return self::FAILURE;
    }
}
