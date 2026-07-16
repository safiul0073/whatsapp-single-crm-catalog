<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Services\ContactGroupService;
use App\Modules\Segments\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AudienceResolver
{
    public function __construct(
        protected ContactGroupService $groups,
        protected SegmentQueryService $segments,
    ) {}

    /**
     * Resolve audience contacts from a campaign audience definition.
     *
     * @param  array{audience_type: string, audience_ids?: array<int, int>, segment_id?: int|null}  $audience
     */
    public function contacts(int $workspaceId, array $audience): Collection
    {
        return $this->query($workspaceId, $audience)->get()->unique('id')->values();
    }

    public function query(int $workspaceId, array $audience): Builder
    {
        $type = $audience['audience_type'] ?? 'groups';
        $ids = array_values(array_filter((array) ($audience['audience_ids'] ?? [])));
        $segmentId = $audience['segment_id'] ?? null;

        $query = Contact::query()->where('workspace_id', $workspaceId);

        return match ($type) {
            'contacts' => $query->whereIn('contacts.id', $ids),
            'groups' => $this->queryForGroups($query, $ids),
            'tags' => $this->queryForTags($query, $ids),
            'segment' => $this->queryForSegment($query, $workspaceId, (int) $segmentId),
            'imported' => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function sendability(Contact $contact, string $provider): ?string
    {
        if ($contact->blocked_at !== null) {
            return 'blocked';
        }

        if ($contact->isOptedOut()) {
            return 'opt_out';
        }

        $status = $contact->opt_in_status instanceof \BackedEnum
            ? $contact->opt_in_status->value
            : (string) $contact->opt_in_status;

        if ($status !== 'subscribed') {
            return 'opt_out';
        }

        if (in_array($provider, ['whatsapp', 'sms'], true) && ! $this->isValidE164((string) $contact->phone)) {
            return 'invalid_phone';
        }

        if ($provider === 'telegram' && ! $this->hasProviderIdentity($contact, $provider)) {
            return 'telegram_opt_in_missing';
        }

        return null;
    }

    protected function hasProviderIdentity(Contact $contact, string $provider): bool
    {
        if ($contact->relationLoaded('identities')) {
            return $contact->identities->where('provider', $provider)->isNotEmpty();
        }

        return ContactProviderIdentity::query()
            ->where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->where('provider', $provider)
            ->exists();
    }

    protected function queryForGroups(Builder $query, array $groupIds): Builder
    {
        if ($groupIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $audienceQuery) use ($groupIds): void {
            $audienceQuery->orWhereHas('groups', function (Builder $groupQuery) use ($groupIds): void {
                $groupQuery->whereIn('contact_groups.id', $groupIds);
            });

            foreach ($this->dynamicGroups($groupIds) as $group) {
                $contactIds = $this->groups->query($group)->pluck('contacts.id')->all();
                $audienceQuery->orWhereIn('contacts.id', $contactIds);
            }
        });
    }

    protected function queryForTags(Builder $query, array $tagIds): Builder
    {
        if ($tagIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('tags', function (Builder $tagQuery) use ($tagIds): void {
            $tagQuery->whereIn('contact_tags.id', $tagIds);
        });
    }

    protected function queryForSegment(Builder $query, int $workspaceId, int $segmentId): Builder
    {
        $segment = Segment::query()->where('workspace_id', $workspaceId)->find($segmentId);

        if (! $segment) {
            return $query->whereRaw('1 = 0');
        }

        return $this->segments->query($segment)->where('workspace_id', $workspaceId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ContactGroup>
     */
    protected function dynamicGroups(array $groupIds): Collection
    {
        return ContactGroup::query()
            ->whereIn('id', $groupIds)
            ->where('type', 'dynamic')
            ->get();
    }

    protected function isValidE164(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }
}
