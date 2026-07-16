<?php

namespace App\Modules\Segments\Services;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Segments\Models\Segment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SegmentService
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $segments = Segment::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate(20);

        $segments->getCollection()->transform(function (Segment $segment): Segment {
            $segment->setAttribute('contacts_count', $this->count($segment));
            $segment->setAttribute('definition', $this->definition($segment));

            return $segment;
        });

        return $segments;
    }

    public function statsForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $segments = Segment::query()->where('workspace_id', $workspace->id)->get();
        $counts = $segments->mapWithKeys(fn (Segment $segment): array => [$segment->id => $this->count($segment)]);
        $largest = $segments->sortByDesc(fn (Segment $segment): int => (int) ($counts[$segment->id] ?? 0))->first();

        return [
            'total' => $segments->count(),
            'dynamic' => $segments->where('type', 'dynamic')->count(),
            'reach' => Contact::query()
                ->where('workspace_id', $workspace->id)
                ->where('opt_in_status', 'subscribed')
                ->whereNull('opt_out_at')
                ->whereNull('blocked_at')
                ->count(),
            'largest' => $largest ? [
                'name' => $largest->name,
                'count' => (int) ($counts[$largest->id] ?? 0),
            ] : null,
        ];
    }

    public function formDataForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);

        return [
            'contacts' => Contact::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'phone']),
            'tags' => ContactTag::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'groups' => ContactGroup::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function storeForUser(?User $user, array $data): Segment
    {
        $workspace = $this->workspaces->current($user);
        $segment = Segment::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'rules' => $data['type'] === 'dynamic' ? $this->cleanRules($data['rules'] ?? []) : [],
        ]);

        if ($segment->type === 'static') {
            $this->syncStaticContacts($segment, $data['contact_ids'] ?? []);
        }

        return $segment->fresh('contacts');
    }

    public function updateForUser(?User $user, string $segment, array $data): Segment
    {
        $workspace = $this->workspaces->current($user);
        $model = Segment::query()->where('workspace_id', $workspace->id)->findOrFail($segment);
        $model->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'rules' => $data['type'] === 'dynamic' ? $this->cleanRules($data['rules'] ?? []) : [],
        ]);

        if ($model->type === 'static') {
            $this->syncStaticContacts($model, $data['contact_ids'] ?? []);
        } else {
            $model->contacts()->sync([]);
        }

        return $model->fresh('contacts');
    }

    public function deleteForUser(?User $user, string $segment): void
    {
        $workspace = $this->workspaces->current($user);
        Segment::query()->where('workspace_id', $workspace->id)->findOrFail($segment)->delete();
    }

    public function duplicateForUser(?User $user, string $segment): Segment
    {
        $workspace = $this->workspaces->current($user);
        $source = Segment::query()->with('contacts')->where('workspace_id', $workspace->id)->findOrFail($segment);
        $copy = Segment::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $this->copyName($workspace->id, $source->name),
            'description' => $source->description,
            'type' => $source->type,
            'rules' => $source->rules ?? [],
        ]);

        if ($source->type === 'static') {
            $copy->contacts()->sync($source->contacts->pluck('id')->all());
        }

        return $copy->fresh('contacts');
    }

    public function previewForUser(?User $user, string $segment): Collection
    {
        $workspace = $this->workspaces->current($user);
        $model = Segment::query()->where('workspace_id', $workspace->id)->findOrFail($segment);

        return $this->query($model)->latest()->limit(50)->get();
    }

    public function query(Segment $segment): Builder
    {
        if ($segment->type === 'static') {
            return Contact::query()
                ->where('workspace_id', $segment->workspace_id)
                ->whereHas('segments', fn (Builder $query) => $query->where('segments.id', $segment->id));
        }

        return $this->applyRules(
            Contact::query()->where('workspace_id', $segment->workspace_id),
            $segment->rules ?? [],
        );
    }

    public function count(Segment $segment): int
    {
        return $this->query($segment)->count();
    }

    public function applyRules(Builder $query, array $rules): Builder
    {
        if (filled($rules['opt_in_status'] ?? null)) {
            $query->where('opt_in_status', $rules['opt_in_status']);
        }

        if (filled($rules['source'] ?? null)) {
            $query->where('source', $rules['source']);
        }

        if (filled($rules['country'] ?? null)) {
            $query->where('country', strtoupper($rules['country']));
        }

        if (filled($rules['city'] ?? null)) {
            $query->where('city', $rules['city']);
        }

        if (! empty($rules['tag_ids'])) {
            $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('contact_tags.id', (array) $rules['tag_ids']));
        }

        if (! empty($rules['group_ids'])) {
            $query->whereHas('groups', fn (Builder $groupQuery) => $groupQuery->whereIn('contact_groups.id', (array) $rules['group_ids']));
        }

        if (filled($rules['created_within_days'] ?? null)) {
            $query->where('created_at', '>=', now()->subDays((int) $rules['created_within_days']));
        }

        if (filled($rules['last_interaction_before_days'] ?? null)) {
            $query->where('last_interaction_at', '<=', now()->subDays((int) $rules['last_interaction_before_days']));
        }

        if (array_key_exists('blocked', $rules) && $rules['blocked'] !== null && $rules['blocked'] !== '') {
            filter_var($rules['blocked'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('blocked_at')
                : $query->whereNull('blocked_at');
        }

        return $query;
    }

    public function definition(Segment $segment): string
    {
        if ($segment->type === 'static') {
            return $segment->description ?: 'Manual contact list';
        }

        $rules = array_filter($segment->rules ?? [], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        return $rules === [] ? 'All contacts' : count($rules).' rule'.(count($rules) === 1 ? '' : 's');
    }

    protected function syncStaticContacts(Segment $segment, array $contactIds): void
    {
        $ids = Contact::query()
            ->where('workspace_id', $segment->workspace_id)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->all();

        $segment->contacts()->sync($ids);
    }

    protected function cleanRules(array $rules): array
    {
        return array_filter([
            'opt_in_status' => $rules['opt_in_status'] ?? null,
            'source' => $rules['source'] ?? null,
            'country' => isset($rules['country']) ? strtoupper((string) $rules['country']) : null,
            'city' => $rules['city'] ?? null,
            'tag_ids' => array_values(array_filter((array) ($rules['tag_ids'] ?? []))),
            'group_ids' => array_values(array_filter((array) ($rules['group_ids'] ?? []))),
            'created_within_days' => $rules['created_within_days'] ?? null,
            'last_interaction_before_days' => $rules['last_interaction_before_days'] ?? null,
            'blocked' => $rules['blocked'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    protected function copyName(int $workspaceId, string $name): string
    {
        $base = $name.' Copy';
        $candidate = $base;
        $count = 2;

        while (Segment::query()->where('workspace_id', $workspaceId)->where('name', $candidate)->exists()) {
            $candidate = $base.' '.$count;
            $count++;
        }

        return $candidate;
    }
}
