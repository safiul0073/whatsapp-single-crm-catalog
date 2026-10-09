<?php

namespace App\Console\Commands;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class CreateDemoContactCommand extends Command
{
    protected $signature = 'commerce:demo-contact {phone : E.164 number, e.g. +8801876150613} {--name=Demo Customer}';

    protected $description = 'Create (or reuse) a demo contact in the store workspace for testing orders';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            $this->error('Phone must be in E.164 format, e.g. +8801876150613.');

            return self::FAILURE;
        }

        $storeWorkspace = Workspace::query()
            ->where('status', 'active')
            ->when(config('commerce.store_workspace_id'), fn ($query, $workspaceId) => $query->whereKey($workspaceId))
            ->oldest('id')
            ->first();
        if (! $storeWorkspace) {
            $this->error('No active store workspace is configured.');

            return self::FAILURE;
        }

        $contact = Contact::query()->firstOrCreate(
            ['workspace_id' => $storeWorkspace->id, 'phone' => $phone],
            ['name' => (string) $this->option('name'), 'source' => 'website', 'opt_in_status' => 'unknown'],
        );

        $this->info(($contact->wasRecentlyCreated ? 'Created' : 'Already exists:')." contact #{$contact->id} ({$contact->phone}) in workspace #{$storeWorkspace->id}.");

        return self::SUCCESS;
    }
}
