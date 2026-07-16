<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemovePanelCommand extends Command
{
    protected $signature = 'remove:panel {name : The name of the panel} {--confirm : Skip confirmation prompt}';

    protected $description = 'Remove an existing panel and its files';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $lowerName = Str::lower($name);

        $panelPath = app_path("Panels/{$studlyName}");
        $viewsPath = resource_path("views/panels/{$lowerName}");

        if (! File::exists($panelPath)) {
            $this->error("Panel {$studlyName} does not exist!");

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            if (! $this->confirm("Are you sure you want to remove the {$studlyName} panel? This action cannot be undone.")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Remove panel directory
        File::deleteDirectory($panelPath);
        $this->info("Removed panel directory: {$panelPath}");

        // Remove views directory
        if (File::exists($viewsPath)) {
            File::deleteDirectory($viewsPath);
            $this->info("Removed views directory: {$viewsPath}");
        }

        $this->newLine();
        $this->warn("Don't forget to remove the panel configuration from config/panels.php");

        return self::SUCCESS;
    }
}
