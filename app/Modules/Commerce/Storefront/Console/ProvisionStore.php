<?php

namespace App\Modules\Commerce\Storefront\Console;

use App\Modules\Commerce\Storefront\Services\ProvisionStoreService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ProvisionStore extends Command
{
    protected $signature = 'commerce:storefront-provision {workspace} {--host=}';

    protected $description = 'Create a public system domain for a storefront workspace';

    public function handle(): int
    {
        $w = Workspace::query()->findOrFail($this->argument('workspace'));
        if ($this->option('host') && ! app()->environment(['local', 'testing'])) {
            $this->error('Explicit hosts are restricted to local testing.');

            return self::FAILURE;
        }
        $domain = app(ProvisionStoreService::class)->provision($w, $this->option('host'));
        $host = $domain->host;
        $this->info($host);

        return self::SUCCESS;
    }
}
