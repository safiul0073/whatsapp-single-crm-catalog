<?php

namespace App\Console\Commands;

use App\Modules\NotificationTemplates\Services\NotificationTemplateService;
use Illuminate\Console\Command;

class SyncNotificationTemplatesCommand extends Command
{
    protected $signature = 'notification:sync';

    protected $description = 'Sync notification templates from config/notification-templates.php to database';

    public function handle(NotificationTemplateService $service): int
    {
        $this->info('Syncing notification templates...');

        $result = $service->syncFromConfig();

        $this->line("  Created: {$result['created']}");
        $this->line("  Skipped (already exist): {$result['skipped']}");

        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }
}
