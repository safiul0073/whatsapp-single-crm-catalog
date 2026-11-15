<?php

namespace App\Modules\Commerce\Storefront\Console;

use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Services\ProvisionStoreService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ProvisionStore extends Command
{
    protected $signature = 'commerce:storefront-provision {workspace} {--host=} {--type= : Business type from config/business_types.php}';

    protected $description = 'Create a public system domain for a storefront workspace';

    public function handle(): int
    {
        $w = Workspace::query()->findOrFail($this->argument('workspace'));
        if ($this->option('host') && ! app()->environment(['local', 'testing'])) {
            $this->error('Explicit hosts are restricted to local testing.');

            return self::FAILURE;
        }
        $type = $this->option('type');
        if ($type !== null) {
            if (! array_key_exists($type, (array) config('business_types.types'))) {
                $this->error('Unknown business type.');

                return self::FAILURE;
            }
            $w->update(['settings' => [...(array) $w->settings, 'business_type' => $type]]);
            StoreFeatures::flush();
        }
        $domain = app(ProvisionStoreService::class)->provision($w, $this->option('host'));
        $host = $domain->host;
        $this->info($host);

        return self::SUCCESS;
    }
}
