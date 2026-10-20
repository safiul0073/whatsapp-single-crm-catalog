<?php

namespace App\Modules\Commerce\Storefront\Console;

use App\Modules\Commerce\Storefront\Services\LegacyImportService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ImportLegacy extends Command
{
    protected $signature = 'commerce:import-legacy {--source=legacy : Read-only configured source connection} {--workspace= : Destination workspace ID} {--dry-run : Inventory without writing records} {--media-root= : Approved legacy asset directory} {--report= : JSON reconciliation report path}';

    protected $description = 'Resume a legacy storefront import and report unresolved mappings without external side effects';

    public function handle(LegacyImportService $import): int
    {
        $workspace = Workspace::query()->findOrFail($this->option('workspace'));
        $report = $import->run($this->option('source'), $workspace->id, (bool) $this->option('dry-run'), $this->option('media-root'));
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($this->option('report')) {
            file_put_contents($this->option('report'), $json);
        } else {
            $this->line($json);
        }

        return count($report['issues']) ? self::FAILURE : self::SUCCESS;
    }
}
