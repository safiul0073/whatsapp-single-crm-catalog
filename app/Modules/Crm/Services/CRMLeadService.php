<?php

namespace App\Modules\Crm\Services;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Crm\Enums\CrmActivityType;
use App\Modules\Crm\Enums\CrmLeadSource;
use App\Modules\Crm\Enums\CrmLeadStatus;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CRMLeadService
{
    public function __construct(protected PipelineService $pipelines, protected ContactService $contacts) {}

    public function createOrUpdate(int $workspaceId, int $contactId, array $data, ?User $actor = null): CrmLead
    {
        return DB::transaction(function () use ($workspaceId, $contactId, $data, $actor): CrmLead {
            $contact = Contact::query()->where('workspace_id', $workspaceId)->lockForUpdate()->findOrFail($contactId);
            $stage = filled($data['stage_id'] ?? null)
                ? $this->pipelines->stageForWorkspace($workspaceId, (int) $data['stage_id'])
                : null;
            $pipeline = $stage
                ? $this->pipelines->pipelineForWorkspace($workspaceId, (int) $stage->pipeline_id)
                : (filled($data['pipeline_id'] ?? null)
                    ? $this->pipelines->pipelineForWorkspace($workspaceId, (int) $data['pipeline_id'])
                    : $this->pipelines->ensureDefaultForWorkspace($workspaceId));
            $stage ??= $pipeline->stages()->orderBy('position')->firstOrFail();

            $conversation = $this->conversation($workspaceId, $contact->id, $data['conversation_id'] ?? null);
            $conversationId = $conversation?->id;
            $campaignId = $this->campaignId($workspaceId, $data['campaign_id'] ?? null);
            $assignedTo = $this->assignedUserId($workspaceId, $data['assigned_to'] ?? $contact->assigned_to);
            $lead = CrmLead::query()
                ->where('workspace_id', $workspaceId)
                ->where('contact_id', $contact->id)
                ->where('pipeline_id', $pipeline->id)
                ->where('status', CrmLeadStatus::Open->value)
                ->first();
            $created = ! $lead;

            $payload = [
                'conversation_id' => $conversationId ?: $lead?->conversation_id,
                'campaign_id' => $campaignId ?: $lead?->campaign_id,
                'stage_id' => $stage->id,
                'title' => $data['title'] ?? $lead?->title ?? ($contact->name ?: $contact->phone ?: __('New lead')),
                'value' => array_key_exists('value', $data) ? $data['value'] : $lead?->value,
                'source' => $data['source'] ?? $lead?->source?->value ?? ($conversation?->provider === 'whatsapp' ? CrmLeadSource::WhatsApp->value : CrmLeadSource::Manual->value),
                'assigned_to' => $assignedTo ?: $lead?->assigned_to,
            ];

            if ($lead) {
                $lead->update($payload);
            } else {
                $lead = CrmLead::query()->create($payload + [
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'pipeline_id' => $pipeline->id,
                    'status' => CrmLeadStatus::Open->value,
                ]);
            }

            if ($created) {
                $this->record($lead, CrmActivityType::LeadCreated, __('Lead created'), null, $actor, ['source' => $lead->source->value]);
            }

            return $lead->fresh(['contact.tags', 'pipeline', 'stage', 'assignee', 'tasks']);
        });
    }

    public function boardLeads(int $workspaceId, int $pipelineId, array $filters = []): Collection
    {
        $this->pipelines->pipelineForWorkspace($workspaceId, $pipelineId);

        return CrmLead::query()
            ->with(['contact.tags', 'stage', 'assignee', 'tasks' => fn ($query) => $query->where('status', 'pending')->orderBy('due_at')])
            ->where('workspace_id', $workspaceId)
            ->where('pipeline_id', $pipelineId)
            ->when(filled($filters['status'] ?? null) && $filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when(blank($filters['status'] ?? null), fn ($query) => $query->where('status', CrmLeadStatus::Open->value))
            ->when(filled($filters['source'] ?? null), fn ($query) => $query->where('source', $filters['source']))
            ->when(filled($filters['assigned_to'] ?? null), fn ($query) => $query->where('assigned_to', $filters['assigned_to']))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $search = (string) $filters['q'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('contact', fn ($contacts) => $contacts
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    public function handleCampaignReply(int $workspaceId, int $contactId, int $conversationId, Campaign $campaign): ?CrmLead
    {
        $campaign = Campaign::query()->where('workspace_id', $workspaceId)->findOrFail($campaign->id);
        $contact = Contact::query()->where('workspace_id', $workspaceId)->findOrFail($contactId);
        $this->contacts->attachTags($contact, $this->contacts->tagIdsForNames($workspaceId, ['Campaign Replied']));

        if (! (bool) data_get($campaign->settings, 'crm_create_lead_on_reply', false)) {
            CrmActivity::query()->create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'conversation_id' => $this->conversation($workspaceId, $contact->id, $conversationId)?->id,
                'type' => CrmActivityType::CampaignReply->value,
                'title' => __('Campaign reply received'),
                'description' => $campaign->name,
                'metadata' => ['campaign_id' => $campaign->id],
            ]);

            return null;
        }

        $lead = $this->createOrUpdate($workspaceId, $contactId, [
            'conversation_id' => $conversationId,
            'campaign_id' => $campaign->id,
            'source' => CrmLeadSource::Campaign->value,
        ]);
        $this->record($lead, CrmActivityType::CampaignReply, __('Campaign reply received'), $campaign->name, null, ['campaign_id' => $campaign->id]);

        return $lead;
    }

    public function moveStage(int $workspaceId, int $leadId, int $stageId, ?User $actor = null): CrmLead
    {
        return DB::transaction(function () use ($workspaceId, $leadId, $stageId, $actor): CrmLead {
            $lead = $this->leadForWorkspace($workspaceId, $leadId, true);
            $this->ensureOpen($lead);
            $stage = $this->pipelines->stageForWorkspace($workspaceId, $stageId);
            if ($stage->pipeline_id !== $lead->pipeline_id) {
                throw ValidationException::withMessages(['stage_id' => __('Leads can only move to a stage in their current pipeline.')]);
            }

            $from = $lead->stage?->name;
            $lead->update(['stage_id' => $stage->id]);
            $this->record($lead, CrmActivityType::StageChanged, __('Stage changed'), __('Moved from :from to :to.', ['from' => $from ?: '-', 'to' => $stage->name]), $actor, ['from_stage' => $from, 'to_stage' => $stage->name]);

            return $lead->fresh(['stage', 'pipeline', 'contact.tags', 'assignee']);
        });
    }

    public function markWon(int $workspaceId, int $leadId, ?User $actor = null): CrmLead
    {
        return DB::transaction(function () use ($workspaceId, $leadId, $actor): CrmLead {
            $lead = $this->leadForWorkspace($workspaceId, $leadId, true);
            $this->ensureOpen($lead);
            $lead->update(['status' => CrmLeadStatus::Won->value, 'won_at' => now(), 'lost_at' => null, 'lost_reason' => null]);
            $this->record($lead, CrmActivityType::Won, __('Lead marked won'), null, $actor);

            return $lead->fresh();
        });
    }

    public function markLost(int $workspaceId, int $leadId, ?string $reason = null, ?User $actor = null): CrmLead
    {
        return DB::transaction(function () use ($workspaceId, $leadId, $reason, $actor): CrmLead {
            $lead = $this->leadForWorkspace($workspaceId, $leadId, true);
            $this->ensureOpen($lead);
            $lead->update(['status' => CrmLeadStatus::Lost->value, 'lost_at' => now(), 'won_at' => null, 'lost_reason' => $reason]);
            $this->record($lead, CrmActivityType::Lost, __('Lead marked lost'), $reason, $actor);

            return $lead->fresh();
        });
    }

    public function addNote(int $workspaceId, int $leadId, string $description, ?User $actor = null): CrmActivity
    {
        $lead = $this->leadForWorkspace($workspaceId, $leadId);

        return $this->record($lead, CrmActivityType::Note, __('Note added'), $description, $actor);
    }

    public function leadForWorkspace(int $workspaceId, int $leadId, bool $lock = false): CrmLead
    {
        return CrmLead::query()
            ->with(['contact', 'pipeline', 'stage', 'assignee'])
            ->where('workspace_id', $workspaceId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->findOrFail($leadId);
    }

    public function record(CrmLead $lead, CrmActivityType $type, string $title, ?string $description = null, ?User $actor = null, array $metadata = []): CrmActivity
    {
        return CrmActivity::query()->create([
            'workspace_id' => $lead->workspace_id,
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'conversation_id' => $lead->conversation_id,
            'type' => $type->value,
            'title' => $title,
            'description' => $description,
            'created_by' => $actor?->id,
            'metadata' => $metadata ?: null,
        ]);
    }

    protected function conversation(int $workspaceId, int $contactId, mixed $conversationId): ?Conversation
    {
        if (! filled($conversationId)) {
            return null;
        }

        return Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contactId)
            ->findOrFail((int) $conversationId);
    }

    protected function campaignId(int $workspaceId, mixed $campaignId): ?int
    {
        if (! filled($campaignId)) {
            return null;
        }

        return Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail((int) $campaignId)
            ->id;
    }

    protected function assignedUserId(int $workspaceId, mixed $userId): ?int
    {
        if (! filled($userId)) {
            return null;
        }

        $workspace = Workspace::query()->findOrFail($workspaceId);

        return User::query()
            ->whereKey((int) $userId)
            ->where(function ($query) use ($workspace): void {
                $query->whereKey($workspace->owner_id)
                    ->orWhereHas('workspaces', fn ($members) => $members
                        ->where('workspaces.id', $workspace->id)
                        ->where('workspace_members.status', WorkspaceMemberStatus::Active->value));
            })
            ->firstOrFail()
            ->id;
    }

    protected function ensureOpen(CrmLead $lead): void
    {
        if ($lead->status !== CrmLeadStatus::Open) {
            throw ValidationException::withMessages(['lead' => __('Only open leads can be changed.')]);
        }
    }
}
