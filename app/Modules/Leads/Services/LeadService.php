<?php

namespace App\Modules\Leads\Services;

use App\Models\User;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Leads\Models\Lead;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlaceApiSettings\Services\PlaceApiSettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ContactService $contacts,
        protected LeadGenerationService $generator,
        protected PlaceApiSettingsService $placeApiSettings,
    ) {}

    public function listForUser(?User $user, array $filters = []): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $stageFilters = $this->normalizeFilterValues($filters['stage'] ?? []);
        $sourceFilters = $this->normalizeFilterValues($filters['source'] ?? []);
        $verificationFilters = $this->normalizeFilterValues($filters['verification_status'] ?? []);

        return Lead::query()
            ->with('contact')
            ->where('workspace_id', $workspace->id)
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = (string) $filters['search'];
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($stageFilters !== [], fn (Builder $query) => $query->whereIn('stage', $stageFilters))
            ->when($sourceFilters !== [], fn (Builder $query) => $query->whereIn('source', $sourceFilters))
            ->when($verificationFilters !== [], fn (Builder $query) => $query->whereIn('verification_status', $verificationFilters))
            ->when(filled($filters['country'] ?? null), fn (Builder $query) => $query->where('country', Str::upper((string) $filters['country'])))
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->where('category', 'like', '%'.$filters['category'].'%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return array{total: int, new: int, converted: int, average_score: int}
     */
    public function statsForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $query = Lead::query()->where('workspace_id', $workspace->id);

        return [
            'total' => (clone $query)->count(),
            'new' => (clone $query)->where('stage', 'new')->count(),
            'converted' => (clone $query)->whereNotNull('contact_id')->count(),
            'average_score' => (int) round((float) (clone $query)->avg('score')),
        ];
    }

    /**
     * @return array{groups: Collection<int, ContactGroup>, tags: Collection<int, ContactTag>, generationProvider: array<string, mixed>|null, placeApiStatus: array<string, mixed>, connectedChannels: array<string, bool>}
     */
    public function formDataForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $connectedProviders = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ChannelAccountStatus::Connected->value)
            ->pluck('provider')
            ->unique()
            ->values();

        return [
            'groups' => ContactGroup::query()->where('workspace_id', $workspace->id)->where('type', 'static')->orderBy('name')->get(),
            'tags' => ContactTag::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'generationProvider' => $this->generator->providerMetadataFor($user),
            'placeApiStatus' => $this->placeApiSettings->status(),
            'connectedChannels' => $connectedProviders->mapWithKeys(fn (string $provider): array => [$provider => true])->all(),
        ];
    }

    public function storeForUser(?User $user, array $data): Lead
    {
        $workspace = $this->workspaces->current($user);

        return Lead::query()->create($this->payload($workspace->id, $data, [
            'source' => $data['source'] ?? 'manual',
            'contact_status' => 'draft',
            'verification_status' => 'manual',
        ]));
    }

    public function updateForUser(?User $user, string $lead, array $data): Lead
    {
        $workspace = $this->workspaces->current($user);
        $model = Lead::query()->where('workspace_id', $workspace->id)->findOrFail($lead);
        $model->update($this->payload($workspace->id, $data, []));

        return $model->fresh('contact');
    }

    public function deleteForUser(?User $user, string $lead): void
    {
        $workspace = $this->workspaces->current($user);

        Lead::query()->where('workspace_id', $workspace->id)->findOrFail($lead)->delete();
    }

    public function generateForUser(?User $user, array $criteria): array
    {
        $workspace = $this->workspaces->current($user);
        $criteria['country'] = Str::upper((string) $criteria['country']);
        $requestedCount = (int) $criteria['count'];

        // 1. Check if same workspace already has leads matching the criteria
        $hasSameWorkspaceLeads = $this->matchingCriteriaQuery($criteria)
            ->where('workspace_id', $workspace->id)
            ->exists();

        if ($hasSameWorkspaceLeads) {
            $criteria['count'] = 10;
            $requestedCount = 10;
        } else {
            // 2. Check if leads exist in DIFFERENT workspaces matching the criteria
            $otherWorkspaceLeadsQuery = $this->matchingCriteriaQuery($criteria)
                ->where('workspace_id', '!=', $workspace->id);

            $otherWorkspaceLeads = $otherWorkspaceLeadsQuery->take($requestedCount)->get();

            if ($otherWorkspaceLeads->isNotEmpty()) {
                $leads = DB::transaction(function () use ($workspace, $otherWorkspaceLeads, $criteria): Collection {
                    $copied = collect();
                    foreach ($otherWorkspaceLeads as $oldLead) {
                        $leadData = [
                            'workspace_id' => $workspace->id,
                            'contact_id' => null,
                            'name' => $oldLead->name,
                            'company' => $oldLead->company,
                            'phone' => $oldLead->phone,
                            'email' => $oldLead->email,
                            'country' => $oldLead->country,
                            'city' => $oldLead->city,
                            'place' => $oldLead->place,
                            'category' => $oldLead->category,
                            'stage' => 'new',
                            'source' => 'google_places',
                            'external_source' => $oldLead->external_source,
                            'external_id' => $oldLead->external_id,
                            'score' => $oldLead->score,
                            'contact_status' => 'draft',
                            'verification_status' => 'unverified',
                            'ai_prompt' => $oldLead->ai_prompt,
                            'criteria' => $criteria,
                            'value' => $oldLead->value,
                            'notes' => $oldLead->notes,
                            'metadata' => $oldLead->metadata,
                        ];

                        $copied->push(Lead::query()->create($leadData));
                    }

                    return $copied;
                });

                return ['leads' => $leads, 'skipped' => 0];
            }
        }

        $providerMetadata = $this->generator->providerMetadataFor($user) ?? [];
        $prompt = $this->promptFromCriteria($criteria);
        $generated = $this->generator->generate($user, $criteria, $workspace->id);
        $drafts = collect($generated['leads'])
            ->filter(fn (array $draft): bool => $this->hasContactRoute($draft))
            ->values();
        $skipped = (int) ($generated['skipped_uncontactable'] ?? 0) + (int) ($generated['skipped_duplicates'] ?? 0) + max(0, $requestedCount - $drafts->count() - (int) ($generated['skipped_uncontactable'] ?? 0) - (int) ($generated['skipped_duplicates'] ?? 0));
        $metadata = array_merge($providerMetadata, $generated['metadata']);
        $leads = DB::transaction(function () use ($workspace, $criteria, $prompt, $drafts, $metadata): Collection {
            $leads = $drafts
                ->map(fn (array $draft): Lead => Lead::query()->create($this->payload($workspace->id, $draft, [
                    'source' => ($draft['_lead_source'] ?? 'google_places') === 'ai_fallback' ? 'ai' : 'google_places',
                    'stage' => 'new',
                    'external_source' => ($draft['_lead_source'] ?? 'google_places') === 'ai_fallback' ? null : ($draft['external_source'] ?? null),
                    'external_id' => ($draft['_lead_source'] ?? 'google_places') === 'ai_fallback' ? null : ($draft['external_id'] ?? null),
                    'contact_status' => 'draft',
                    'verification_status' => 'unverified',
                    'ai_prompt' => $prompt,
                    'criteria' => $criteria,
                    'metadata' => $this->generationMetadata($draft, $criteria, $metadata),
                ])));

            return $leads;
        });

        return ['leads' => $leads, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return Builder<Lead>
     */
    protected function matchingCriteriaQuery(array $criteria): Builder
    {
        $query = Lead::query()
            ->where('criteria->country', $criteria['country'])
            ->where('criteria->category', $criteria['category'])
            ->where('criteria->count', (int) $criteria['count']);

        foreach (['place', 'audience', 'channel'] as $key) {
            if (filled($criteria[$key] ?? null)) {
                $query->where("criteria->{$key}", $criteria[$key]);
            } else {
                $query->where(function (Builder $q) use ($key): void {
                    $q->whereNull("criteria->{$key}")->orWhere("criteria->{$key}", '');
                });
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function generationMetadata(array $draft, array $criteria, array $metadata): array
    {
        $leadSource = (string) ($draft['_lead_source'] ?? 'google_places');
        $generation = (array) ($draft['_generation'] ?? []);
        $generatedBy = $leadSource === 'ai_fallback'
            ? ($generation['generated_by'] ?? $metadata['generated_by'] ?? 'local')
            : ($metadata['generated_by'] ?? 'local');

        return [
            'generated_by' => $generatedBy,
            'provider' => $leadSource === 'ai_fallback' ? ($generation['provider'] ?? null) : ($metadata['provider'] ?? null),
            'model' => $leadSource === 'ai_fallback' ? ($generation['model'] ?? null) : ($metadata['model'] ?? null),
            'platform_ai_used' => $leadSource === 'ai_fallback'
                ? ($generatedBy === 'platform_ai')
                : (bool) ($metadata['platform_ai_used'] ?? false),
            'ai_query_used' => (bool) ($metadata['ai_query_used'] ?? false),
            'lead_source' => $leadSource,
            'channel' => $criteria['channel'] ?? 'any',
            'google_place_id' => $leadSource === 'ai_fallback' ? null : ($draft['metadata']['google_place_id'] ?? null),
            'google_maps_uri' => $leadSource === 'ai_fallback' ? null : ($draft['metadata']['google_maps_uri'] ?? null),
            'website_uri' => $draft['metadata']['website_uri'] ?? null,
            'website_domain' => $this->domainFromUrl($draft['metadata']['website_uri'] ?? null),
            'google_places' => $metadata,
        ];
    }

    public function convertForUser(?User $user, string $lead, array $options = []): Contact
    {
        $workspace = $this->workspaces->current($user);
        $model = Lead::query()->where('workspace_id', $workspace->id)->findOrFail($lead);

        return $this->convert($workspace->id, $model, $options);
    }

    public function bulkConvertForUser(?User $user, array $leadIds, array $options = []): int
    {
        $workspace = $this->workspaces->current($user);
        $leads = Lead::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $leadIds)
            ->get()
            ->filter(fn (Lead $lead): bool => ! $lead->isConverted() && $this->hasContactRoute($lead->toArray()));

        return DB::transaction(function () use ($workspace, $leads, $options): int {
            $count = 0;

            foreach ($leads as $lead) {
                $this->convert($workspace->id, $lead, $options);
                $count++;
            }

            return $count;
        });
    }

    public function bulkDeleteForUser(?User $user, array $leadIds): int
    {
        $workspace = $this->workspaces->current($user);
        $leads = Lead::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $leadIds)
            ->get();
        $count = $leads->count();

        Lead::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $leads->pluck('id'))
            ->delete();

        return $count;
    }

    protected function convert(int $workspaceId, Lead $lead, array $options = []): Contact
    {
        if (! $this->hasContactRoute($lead->toArray())) {
            throw ValidationException::withMessages([
                'lead' => 'Add a phone number or email before converting this lead to a contact.',
            ]);
        }

        $contact = $this->contacts->upsert($workspaceId, [
            'name' => $lead->name ?: $lead->company ?: $lead->phone ?: $lead->email,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'country' => $lead->country,
            'city' => $lead->city ?: $lead->place,
            'source' => ContactSource::AiGenerated->value,
            'opt_in_status' => ContactOptInStatus::Unknown->value,
            'custom_fields' => [
                'generated_from_lead' => true,
                'lead_id' => $lead->id,
                'company' => $lead->company,
                'category' => $lead->category,
                'score' => $lead->score,
                'verification_status' => $lead->verification_status,
            ],
            'tag_ids' => $options['tag_ids'] ?? [],
            'group_ids' => $options['group_ids'] ?? [],
        ]);

        $lead->update([
            'contact_id' => $contact->id,
            'contact_status' => 'converted',
            'stage' => in_array($lead->stage, ['won', 'converted'], true) ? $lead->stage : 'converted',
        ]);

        return $contact;
    }

    protected function payload(int $workspaceId, array $data, array $overrides): array
    {
        return array_merge([
            'workspace_id' => $workspaceId,
            'name' => $data['name'] ?? null,
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'country' => filled($data['country'] ?? null) ? Str::upper((string) $data['country']) : null,
            'city' => $data['city'] ?? null,
            'place' => $data['place'] ?? null,
            'category' => $data['category'] ?? null,
            'stage' => $data['stage'] ?? 'new',
            'source' => $data['source'] ?? 'manual',
            'external_source' => $data['external_source'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'score' => $data['score'] ?? null,
            'value' => $data['value'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $overrides);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeFilterValues(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn (mixed $item): bool => filled($item) && $item !== 'all')
            ->map(fn (mixed $item): string => (string) $item)
            ->values()
            ->all();
    }

    protected function hasContactRoute(array $lead): bool
    {
        return filled($lead['phone'] ?? null) || filled($lead['email'] ?? null);
    }

    protected function promptFromCriteria(array $criteria): string
    {
        return trim(sprintf(
            'Generate %d %s leads in %s%s for %s. %s %s',
            (int) $criteria['count'],
            (string) ($criteria['channel'] ?? 'any-channel'),
            (string) $criteria['country'],
            filled($criteria['place'] ?? null) ? ', '.$criteria['place'] : '',
            (string) $criteria['category'],
            (string) ($criteria['audience'] ?? ''),
            (string) ($criteria['notes'] ?? ''),
        ));
    }

    protected function domainFromUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url((string) $url, PHP_URL_HOST);
        $host = $host ?: parse_url('https://'.ltrim((string) $url, '/'), PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }
}
