<?php

namespace App\Modules\Crm\Database\Seeders;

use App\Modules\Crm\Services\PipelineService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::query()->eachById(function (Workspace $workspace): void {
            app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);
        });
    }
}
