<?php

namespace App\Modules\Contacts\Services;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactGroupService
{
    private const CONTACT_FIELDS = [
        'name', 'phone', 'email', 'country', 'city', 'source', 'opt_in_status',
        'opt_in_at', 'opt_out_at', 'blocked_at', 'last_interaction_at',
        'created_at', 'updated_at',
    ];

    private const RELATION_FIELDS = ['tag_id', 'tag_name', 'group_id'];

    private const OPERATORS = [
        '=', '!=', 'contains', 'not_contains', 'starts_with', 'ends_with',
        '>', '>=', '<', '<=', 'in', 'not_in', 'is_null', 'is_not_null',
        'within_days', 'before_days',
    ];

    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $groups = ContactGroup::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate(20);

        $groups->getCollection()->transform(function (ContactGroup $group): ContactGroup {
            $group->setAttribute('contacts_count', $this->count($group));
            $group->setAttribute('definition', $this->definition($group));

            return $group;
        });

        return $groups;
    }

    public function allForUser(?User $user): Collection
    {
        $workspace = $this->workspaces->current($user);

        return ContactGroup::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get();
    }

    public function formDataForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);

        return [
            'contacts' => Contact::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->limit(1000)
                ->get(['id', 'name', 'phone', 'email']),
            'ruleFields' => array_merge(self::CONTACT_FIELDS, self::RELATION_FIELDS),
            'ruleOperators' => self::OPERATORS,
        ];
    }

    public function storeForUser(?User $user, array $data): ContactGroup
    {
        $workspace = $this->workspaces->current($user);
        $group = ContactGroup::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'static',
            'rules' => ($data['type'] ?? 'static') === 'dynamic' ? $this->cleanRules($data['rules'] ?? []) : [],
        ]);

        if ($group->type === 'static') {
            $this->syncStaticContacts($group, $data['contact_ids'] ?? []);
        }

        return $group->fresh('contacts');
    }

    public function updateForUser(?User $user, string $group, array $data): ContactGroup
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group);
        $type = $data['type'] ?? $model->type ?? 'static';

        $model->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'type' => $type,
            'rules' => $type === 'dynamic' ? $this->cleanRules($data['rules'] ?? []) : [],
        ]);

        if ($type === 'static') {
            $this->syncStaticContacts($model, $data['contact_ids'] ?? []);
        } else {
            $model->contacts()->sync([]);
        }

        return $model->fresh('contacts');
    }

    public function deleteForUser(?User $user, string $group): void
    {
        $workspace = $this->workspaces->current($user);
        ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group)->delete();
    }

    public function duplicateForUser(?User $user, string $group): ContactGroup
    {
        $workspace = $this->workspaces->current($user);
        $source = ContactGroup::query()->with('contacts')->where('workspace_id', $workspace->id)->findOrFail($group);
        $name = $this->copyName($workspace->id, $source->name);
        $copy = ContactGroup::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $source->description,
            'type' => $source->type,
            'rules' => $source->rules ?? [],
        ]);

        if ($source->type === 'static') {
            $copy->contacts()->sync($source->contacts->pluck('id')->all());
        }

        return $copy->fresh('contacts');
    }

    public function previewForUser(?User $user, string $group): Collection
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group);

        return $this->query($model)->latest()->limit(50)->get();
    }

    public function contacts(?User $user, string $group)
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group);

        return $this->query($model)->latest()->paginate(20);
    }

    public function attachContacts(?User $user, string $group, array $contactIds): void
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group);

        if ($model->type !== 'static') {
            throw ValidationException::withMessages(['group' => 'Contacts can only be manually attached to static groups.']);
        }

        $ids = Contact::query()->whereIn('id', $contactIds)->where('workspace_id', $workspace->id)->pluck('id')->all();
        $model->contacts()->syncWithoutDetaching($ids);
    }

    public function detachContact(?User $user, string $group, string $contact): void
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($group);
        $contactId = Contact::query()->where('workspace_id', $workspace->id)->findOrFail($contact)->id;
        $model->contacts()->detach($contactId);
    }

    public function query(ContactGroup $group): Builder
    {
        if ($group->type === 'dynamic') {
            return $this->applyRules(
                Contact::query()->where('workspace_id', $group->workspace_id),
                $group->rules ?? [],
                $group,
            );
        }

        return Contact::query()
            ->where('workspace_id', $group->workspace_id)
            ->whereHas('groups', fn (Builder $query) => $query->where('contact_groups.id', $group->id));
    }

    public function count(ContactGroup $group): int
    {
        return $this->query($group)->count();
    }

    public function applyRules(Builder $query, array $rules, ?ContactGroup $currentGroup = null): Builder
    {
        $rules = $this->cleanRules($rules);

        if ($rules === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($rules, $currentGroup): void {
            foreach ($rules as $index => $rule) {
                $method = $index === 0 || ($rule['boolean'] ?? 'and') === 'and' ? 'where' : 'orWhere';
                $outer->{$method}(fn (Builder $inner) => $this->applyRule($inner, $rule, $currentGroup));
            }
        });
    }

    public function definition(ContactGroup $group): string
    {
        if ($group->type === 'static') {
            return $group->description ?: 'Manual contact selection';
        }

        $count = count($this->cleanRules($group->rules ?? []));

        return $count === 0 ? 'All contacts' : $count.' dynamic rule'.($count === 1 ? '' : 's');
    }

    protected function applyRule(Builder $query, array $rule, ?ContactGroup $currentGroup = null): void
    {
        $field = $rule['field'];
        $operator = $rule['operator'];
        $value = $rule['value'] ?? null;

        if (in_array($field, self::CONTACT_FIELDS, true)) {
            $this->applyColumnRule($query, $field, $operator, $value);

            return;
        }

        if ($field === 'tag_id') {
            $this->applyRelationRule($query, 'tags', 'contact_tags.id', $operator, $value);

            return;
        }

        if ($field === 'tag_name') {
            $this->applyRelationRule($query, 'tags', 'contact_tags.name', $operator, $value);

            return;
        }

        if ($field === 'group_id') {
            $ids = array_map('intval', (array) $this->normalizeList($value));
            if ($currentGroup) {
                $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== (int) $currentGroup->id));
            }

            $this->applyRelationRule($query, 'groups', 'contact_groups.id', $operator, $ids);
        }
    }

    protected function applyColumnRule(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '=' => $query->where($field, $value),
            '!=' => $query->where($field, '!=', $value),
            'contains' => $query->where($field, 'like', '%'.$value.'%'),
            'not_contains' => $query->where($field, 'not like', '%'.$value.'%'),
            'starts_with' => $query->where($field, 'like', $value.'%'),
            'ends_with' => $query->where($field, 'like', '%'.$value),
            '>' => $query->where($field, '>', $value),
            '>=' => $query->where($field, '>=', $value),
            '<' => $query->where($field, '<', $value),
            '<=' => $query->where($field, '<=', $value),
            'in' => $query->whereIn($field, $this->normalizeList($value)),
            'not_in' => $query->whereNotIn($field, $this->normalizeList($value)),
            'is_null' => $query->whereNull($field),
            'is_not_null' => $query->whereNotNull($field),
            'within_days' => $query->where($field, '>=', now()->subDays((int) $value)),
            'before_days' => $query->where($field, '<=', now()->subDays((int) $value)),
            default => null,
        };
    }

    protected function applyRelationRule(Builder $query, string $relation, string $column, string $operator, mixed $value): void
    {
        $positiveOperator = match ($operator) {
            '!=' => '=',
            'not_in' => 'in',
            'not_contains' => 'contains',
            default => $operator,
        };

        $callback = function (Builder $relationQuery) use ($column, $positiveOperator, $value): void {
            $this->applyColumnRule($relationQuery, $column, $positiveOperator, $value);
        };

        in_array($operator, ['!=', 'not_in', 'not_contains'], true)
            ? $query->whereDoesntHave($relation, $callback)
            : $query->whereHas($relation, $callback);
    }

    protected function syncStaticContacts(ContactGroup $group, array $contactIds): void
    {
        $ids = Contact::query()
            ->where('workspace_id', $group->workspace_id)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->all();

        $group->contacts()->sync($ids);
    }

    protected function cleanRules(array $rules): array
    {
        $clean = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? null;

            if (! in_array($field, array_merge(self::CONTACT_FIELDS, self::RELATION_FIELDS), true)
                || ! in_array($operator, self::OPERATORS, true)) {
                continue;
            }

            $clean[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $rule['value'] ?? null,
                'boolean' => ($rule['boolean'] ?? 'and') === 'or' ? 'or' : 'and',
            ];
        }

        return $clean;
    }

    protected function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item): bool => $item !== null && $item !== ''));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) $value) ?: [])));
    }

    protected function copyName(int $workspaceId, string $name): string
    {
        $base = $name.' Copy';
        $candidate = $base;
        $count = 2;

        while (ContactGroup::query()->where('workspace_id', $workspaceId)->where('name', $candidate)->exists()) {
            $candidate = $base.' '.$count;
            $count++;
        }

        return $candidate;
    }
}
