<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListPanelsCommand extends Command
{
    protected $signature = 'panels:list';

    protected $description = 'List all registered panels';

    public function handle(): int
    {
        $panels = config('panels', []);

        if (empty($panels)) {
            $this->warn('No panels registered in config/panels.php');

            return self::SUCCESS;
        }

        $this->info('Registered Panels:');
        $this->newLine();

        $tableData = [];

        foreach ($panels as $key => $panel) {
            $panelPath = app_path('Panels/'.ucfirst($key));
            $exists = File::exists($panelPath) ? '✓' : '✗';
            $active = ! empty($panel['active']) ? '✓' : '✗';

            $tableData[] = [
                'Key' => $key,
                'Name' => $panel['name'] ?? '-',
                'Prefix' => $panel['prefix'] ?? '-',
                'Roles' => empty($panel['roles']) ? 'All' : implode(', ', $panel['roles']),
                'Active' => $active,
                'Exists' => $exists,
            ];
        }

        $this->table(
            ['Key', 'Name', 'Prefix', 'Roles', 'Active', 'Exists'],
            $tableData
        );

        return self::SUCCESS;
    }
}
