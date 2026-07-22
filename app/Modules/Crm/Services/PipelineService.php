<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmPipeline;
use App\Modules\Crm\Models\CrmStage;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public function ensureDefaultForWorkspace(int $workspaceId): CrmPipeline
    {
        return DB::transaction(function () use ($workspaceId): CrmPipeline {
            Workspace::query()->whereKey($workspaceId)->lockForUpdate()->firstOrFail();

            $pipeline = CrmPipeline::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_default', true)
                ->first();

            if ($pipeline) {
                CrmPipeline::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereKeyNot($pipeline->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                return $pipeline->load('stages');
            }

            $pipeline = CrmPipeline::query()->create([
                'workspace_id' => $workspaceId,
                'name' => 'Sales Pipeline',
                'is_default' => true,
            ]);

            $this->createDefaultStages($pipeline, $workspaceId);

            return $pipeline->load('stages');
        });
    }

    public function pipelinesForWorkspace(int $workspaceId): Collection
    {
        $this->ensureDefaultForWorkspace($workspaceId);

        return CrmPipeline::query()
            ->with(['stages' => fn ($query) => $query->orderBy('position')])
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function create(int $workspaceId, array $data): CrmPipeline
    {
        $this->ensureDefaultForWorkspace($workspaceId);

        return DB::transaction(function () use ($workspaceId, $data): CrmPipeline {
            Workspace::query()->whereKey($workspaceId)->lockForUpdate()->firstOrFail();

            if ((bool) ($data['is_default'] ?? false)) {
                CrmPipeline::query()->where('workspace_id', $workspaceId)->update(['is_default' => false]);
            }

            $pipeline = CrmPipeline::query()->create([
                'workspace_id' => $workspaceId,
                'name' => $data['name'],
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $this->createDefaultStages($pipeline, $workspaceId);

            return $pipeline->load('stages');
        });
    }

    public function update(int $workspaceId, int $pipelineId, array $data): CrmPipeline
    {
        return DB::transaction(function () use ($workspaceId, $pipelineId, $data): CrmPipeline {
            Workspace::query()->whereKey($workspaceId)->lockForUpdate()->firstOrFail();
            $pipeline = $this->pipelineForWorkspace($workspaceId, $pipelineId, true);

            if ((bool) ($data['is_default'] ?? false)) {
                CrmPipeline::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereKeyNot($pipeline->getKey())
                    ->update(['is_default' => false]);
            }

            $pipeline->update([
                'name' => $data['name'],
                'is_default' => $pipeline->is_default || (bool) ($data['is_default'] ?? false),
            ]);

            return $pipeline->fresh('stages');
        });
    }

    public function delete(int $workspaceId, int $pipelineId): void
    {
        $pipeline = $this->pipelineForWorkspace($workspaceId, $pipelineId);

        if ($pipeline->is_default || CrmLead::query()->where('workspace_id', $workspaceId)->where('pipeline_id', $pipeline->id)->exists()) {
            throw ValidationException::withMessages(['pipeline' => __('Default pipelines or pipelines containing leads cannot be deleted.')]);
        }

        $pipeline->delete();
    }

    public function createStage(int $workspaceId, int $pipelineId, array $data): CrmStage
    {
        $pipeline = $this->pipelineForWorkspace($workspaceId, $pipelineId);
        $position = CrmStage::query()->where('workspace_id', $workspaceId)->where('pipeline_id', $pipeline->id)->max('position');

        return CrmStage::query()->create([
            'workspace_id' => $workspaceId,
            'pipeline_id' => $pipeline->id,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'position' => $data['position'] ?? ((int) $position + 1),
        ]);
    }

    public function updateStage(int $workspaceId, int $stageId, array $data): CrmStage
    {
        $stage = $this->stageForWorkspace($workspaceId, $stageId);
        $stage->update($data);

        return $stage->fresh();
    }

    public function deleteStage(int $workspaceId, int $stageId, ?int $replacementStageId = null): void
    {
        DB::transaction(function () use ($workspaceId, $stageId, $replacementStageId): void {
            $stage = $this->stageForWorkspace($workspaceId, $stageId);
            $stageCount = CrmStage::query()
                ->where('workspace_id', $workspaceId)
                ->where('pipeline_id', $stage->pipeline_id)
                ->count();

            if ($stageCount < 2) {
                throw ValidationException::withMessages(['stage' => __('A pipeline must contain at least one stage.')]);
            }

            $leadQuery = CrmLead::query()->where('workspace_id', $workspaceId)->where('stage_id', $stage->id);

            if ($leadQuery->exists()) {
                if (! $replacementStageId) {
                    throw ValidationException::withMessages(['replacement_stage_id' => __('Choose a replacement stage before deleting a stage that contains leads.')]);
                }

                $replacement = $this->stageForWorkspace($workspaceId, $replacementStageId);
                if ($replacement->pipeline_id !== $stage->pipeline_id || $replacement->id === $stage->id) {
                    throw ValidationException::withMessages(['replacement_stage_id' => __('The replacement must be another stage in this pipeline.')]);
                }

                $leadQuery->update(['stage_id' => $replacement->id]);
            }

            $stage->delete();
        });
    }

    public function pipelineForWorkspace(int $workspaceId, int $pipelineId, bool $lock = false): CrmPipeline
    {
        return CrmPipeline::query()
            ->where('workspace_id', $workspaceId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->findOrFail($pipelineId);
    }

    public function stageForWorkspace(int $workspaceId, int $stageId): CrmStage
    {
        return CrmStage::query()->where('workspace_id', $workspaceId)->findOrFail($stageId);
    }

    protected function createDefaultStages(CrmPipeline $pipeline, int $workspaceId): void
    {
        foreach ([
            ['name' => 'New', 'color' => '#1FAA53'],
            ['name' => 'Contacted', 'color' => '#F59E0B'],
            ['name' => 'Qualified', 'color' => '#6366F1'],
            ['name' => 'Proposal', 'color' => '#075E54'],
        ] as $position => $stage) {
            $pipeline->stages()->create([
                'workspace_id' => $workspaceId,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'position' => $position,
            ]);
        }
    }
}
