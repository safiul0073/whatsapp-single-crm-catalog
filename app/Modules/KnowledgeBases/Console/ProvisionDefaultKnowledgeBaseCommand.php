<?php

namespace App\Modules\KnowledgeBases\Console;

use App\Modules\KnowledgeBases\Services\DefaultKnowledgeBaseService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ProvisionDefaultKnowledgeBaseCommand extends Command
{
    protected $signature = 'knowledge-base:provision-defaults {workspace? : Only this workspace ID}';

    protected $description = 'Create the default Store Help knowledge base, Store Assistant chatbot and website widget';

    public function handle(DefaultKnowledgeBaseService $defaults): int
    {
        $workspaces = Workspace::query()
            ->when($this->argument('workspace'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($workspaces as $workspace) {
            $defaults->provision($workspace);
            $this->line("Provisioned workspace #{$workspace->id} ({$workspace->name}).");
        }

        $this->info("Done: {$workspaces->count()} workspace(s).");

        return self::SUCCESS;
    }
}
