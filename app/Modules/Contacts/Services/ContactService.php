<?php

namespace App\Modules\Contacts\Services;

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
    ) {}

    public function listForUser(?User $user)
    {
        return $this->queryForUser($user)->latest()->paginate(20);
    }

    public function exportCsvForUser(?User $user, array $filters = []): StreamedResponse
    {
        $filename = 'contacts-'.now()->format('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
        $columns = [
            'Name',
            'Phone',
            'Email',
            'Country',
            'City',
            'Tags',
            'Groups',
            'Opt-in Status',
            'Source',
            'Custom Fields',
            'Opted In At',
            'Opted Out At',
            'Blocked At',
            'Last Interaction At',
            'Created At',
        ];

        return response()->stream(function () use ($user, $filters, $columns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $this->queryForUser($user, $filters)
                ->orderBy('id')
                ->chunkById(500, function ($contacts) use ($handle): void {
                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $contact->name,
                            $contact->phone,
                            $contact->email,
                            $contact->country,
                            $contact->city,
                            $contact->tags->pluck('name')->implode(', '),
                            $contact->groups->pluck('name')->implode(', '),
                            $contact->opt_in_status?->value ?? '',
                            $contact->source?->value ?? '',
                            $contact->custom_fields ? json_encode($contact->custom_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                            $contact->opt_in_at?->toDateTimeString() ?? '',
                            $contact->opt_out_at?->toDateTimeString() ?? '',
                            $contact->blocked_at?->toDateTimeString() ?? '',
                            $contact->last_interaction_at?->toDateTimeString() ?? '',
                            $contact->created_at?->toDateTimeString() ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, Response::HTTP_OK, $headers);
    }

    public function storeForUser(?User $user, array $data): Contact
    {
        $workspace = $this->workspaces->current($user);

        return $this->upsert($workspace->id, $data);
    }

    public function updateForUser(?User $user, string $contact, array $data): Contact
    {
        $workspace = $this->workspaces->current($user);
        $model = $this->findContactForUser($user, $workspace->id, $contact);
        $tagIds = $data['tag_ids'] ?? null;
        $groupIds = $data['group_ids'] ?? null;
        $tagNames = $data['tags'] ?? null;
        $groupNames = $data['groups'] ?? null;

        unset($data['tag_ids'], $data['group_ids'], $data['tags'], $data['groups']);

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->cleanCustomFields((array) $data['custom_fields']);
        }

        if (isset($data['phone'])) {
            $data['phone'] = $this->normalizePhone($data['phone']);
        }

        if (isset($data['opt_in_status']) && $data['opt_in_status'] === ContactOptInStatus::Unsubscribed->value) {
            $data['opt_out_at'] = now();
        }

        if (isset($data['opt_in_status']) && $data['opt_in_status'] === ContactOptInStatus::Subscribed->value) {
            $data['opt_in_at'] = $model->opt_in_at ?? now();
            $data['opt_out_at'] = null;
        }

        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;

        if ($phone !== null || $email !== null) {
            $existingByPhone = null;
            if ($phone !== null && $phone !== $model->phone) {
                $existingByPhone = Contact::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('phone', $phone)
                    ->where('id', '!=', $model->id)
                    ->first();
            }

            $existingByEmail = null;
            if ($email !== null && $email !== $model->email) {
                $existingByEmail = Contact::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('email', $email)
                    ->where('id', '!=', $model->id)
                    ->first();
            }

            if ($existingByPhone && $existingByEmail) {
                if ($existingByPhone->id === $existingByEmail->id) {
                    $this->mergeContacts($model, $existingByPhone);
                } else {
                    $this->mergeContacts($model, $existingByPhone);
                    $this->mergeContacts($model, $existingByEmail);
                }
            } elseif ($existingByPhone) {
                $this->mergeContacts($model, $existingByPhone);
            } elseif ($existingByEmail) {
                $this->mergeContacts($model, $existingByEmail);
            }
        }

        $model->update($data);

        if (is_array($tagIds)) {
            $this->syncTags($model, $this->validTagIds($workspace->id, $tagIds));
        }

        if (is_array($tagNames)) {
            $this->syncTags($model, $this->tagIdsForNames($workspace->id, $tagNames));
        }

        if (is_array($groupIds)) {
            $this->syncGroups($model, $this->validGroupIds($workspace->id, $groupIds));
        }

        if (is_array($groupNames)) {
            $this->syncGroups($model, $this->groupIdsForNames($workspace->id, $groupNames));
        }

        return $model->fresh('identities', 'tags', 'groups');
    }

    protected function findContactForUser(?User $user, int $workspaceId, string $contact): Contact
    {
        $query = Contact::query()->where('workspace_id', $workspaceId);

        if ($user && ! $user->can('contacts.view') && $user->can('contacts.assigned_only')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->findOrFail($contact);
    }

    public function upsert(int $workspaceId, array $data): Contact
    {
        $phone = filled($data['phone'] ?? null) ? $this->normalizePhone((string) $data['phone']) : null;
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;

        if ($phone === null && $email === null) {
            throw ValidationException::withMessages([
                'contact' => 'Add a phone number or email before saving this contact.',
            ]);
        }

        $existingByPhone = null;
        if ($phone !== null) {
            $existingByPhone = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->where('phone', $phone)
                ->first();
        }

        $existingByEmail = null;
        if ($email !== null) {
            $existingByEmail = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->where('email', $email)
                ->first();
        }

        $contact = null;

        if ($existingByPhone && $existingByEmail) {
            if ($existingByPhone->id === $existingByEmail->id) {
                $contact = $existingByPhone;
            } else {
                $this->mergeContacts($existingByPhone, $existingByEmail);
                $contact = $existingByPhone;
            }
        } elseif ($existingByPhone) {
            $contact = $existingByPhone;
        } elseif ($existingByEmail) {
            $contact = $existingByEmail;
        }

        $contactData = [
            'name' => $data['name'] ?? ($contact?->name ?? $phone ?? $email),
            'phone' => $phone,
            'email' => $email,
            'country' => $data['country'] ?? ($contact?->country ?? null),
            'city' => $data['city'] ?? ($contact?->city ?? null),
            'custom_fields' => $this->cleanCustomFields(array_merge(
                $contact?->custom_fields ?? [],
                (array) ($data['custom_fields'] ?? [])
            )),
            'opt_in_status' => $data['opt_in_status'] ?? ($contact?->opt_in_status->value ?? ContactOptInStatus::Subscribed->value),
            'source' => $data['source'] ?? ($contact?->source->value ?? ContactSource::Manual->value),
        ];

        if ($contactData['opt_in_status'] === ContactOptInStatus::Subscribed->value) {
            $contactData['opt_in_at'] = $data['opt_in_at'] ?? ($contact?->opt_in_at ?? now());
            $contactData['opt_out_at'] = null;
        }

        if ($contactData['opt_in_status'] === ContactOptInStatus::Unsubscribed->value) {
            $contactData['opt_out_at'] = $data['opt_out_at'] ?? ($contact?->opt_out_at ?? now());
        }

        if ($contact) {
            $contact->update($contactData);
        } else {
            $contact = Contact::query()->create(array_merge(['workspace_id' => $workspaceId], $contactData));
        }

        if ($phone !== null) {
            ContactProviderIdentity::query()->updateOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'provider' => $data['provider'] ?? 'whatsapp',
                    'provider_contact_id' => $data['provider_contact_id'] ?? $phone,
                ],
                ['contact_id' => $contact->id, 'identity_type' => 'phone'],
            );
        }

        if (! empty($data['tag_ids'])) {
            $this->attachTags($contact, $this->validTagIds($workspaceId, $data['tag_ids']));
        }

        if (! empty($data['tags'])) {
            $this->attachTags($contact, $this->tagIdsForNames($workspaceId, (array) $data['tags']));
        }

        if (! empty($data['group_ids'])) {
            $contact->groups()->syncWithoutDetaching($this->validGroupIds($workspaceId, $data['group_ids']));
        }

        if (! empty($data['groups'])) {
            $contact->groups()->syncWithoutDetaching($this->groupIdsForNames($workspaceId, (array) $data['groups']));
        }

        return $contact->fresh('identities', 'tags', 'groups');
    }

    public function deleteForUser(?User $user, string $contact): void
    {
        $workspace = $this->workspaces->current($user);
        $model = $this->findContactForUser($user, $workspace->id, $contact);

        $model->delete();
    }

    public function attachTags(Contact $contact, array $tagIds): void
    {
        $existing = $contact->tags()->pluck('contact_tags.id')->all();
        $contact->tags()->syncWithoutDetaching($tagIds);
        $contact->load('tags');
        $this->dispatchTagAdded($contact, array_values(array_diff($tagIds, $existing)));
    }

    public function syncTags(Contact $contact, array $tagIds): void
    {
        $existing = $contact->tags()->pluck('contact_tags.id')->all();
        $contact->tags()->sync($tagIds);
        $contact->load('tags');
        $this->dispatchTagAdded($contact, array_values(array_diff($tagIds, $existing)));
    }

    public function attachGroups(Contact $contact, array $groupIds): void
    {
        $contact->groups()->syncWithoutDetaching($groupIds);
        $contact->load('groups');
    }

    public function syncGroups(Contact $contact, array $groupIds): void
    {
        $contact->groups()->sync($groupIds);
        $contact->load('groups');
    }

    public function bulkTag(?User $user, array $contactIds, int $tagId): void
    {
        $workspace = $this->workspaces->current($user);
        $tag = ContactTag::query()->where('workspace_id', $workspace->id)->findOrFail($tagId);
        $contacts = Contact::query()->whereIn('id', $contactIds)->where('workspace_id', $workspace->id)->get();

        foreach ($contacts as $contact) {
            $this->attachTags($contact, [$tag->id]);
        }
    }

    public function bulkGroup(?User $user, array $contactIds, int $groupId): void
    {
        $workspace = $this->workspaces->current($user);
        $group = ContactGroup::query()->where('workspace_id', $workspace->id)->findOrFail($groupId);
        $contacts = Contact::query()->whereIn('id', $contactIds)->where('workspace_id', $workspace->id)->get();

        foreach ($contacts as $contact) {
            $contact->groups()->syncWithoutDetaching($group->id);
        }
    }

    public function bulkDelete(?User $user, array $contactIds): void
    {
        $workspace = $this->workspaces->current($user);
        Contact::query()->whereIn('id', $contactIds)->where('workspace_id', $workspace->id)->delete();
    }

    public function touchLastInteraction(Contact $contact): void
    {
        $contact->updateQuietly(['last_interaction_at' => now()]);
    }

    protected function queryForUser(?User $user, array $filters = []): Builder
    {
        $workspace = $this->workspaces->current($user);
        $query = Contact::query()
            ->with('tags', 'groups')
            ->where('workspace_id', $workspace->id);

        if ($user && ! $user->can('contacts.view') && $user->can('contacts.assigned_only')) {
            $query->where('assigned_to', $user->id);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('country', 'like', '%'.$search.'%')
                    ->orWhereHas('tags', fn (Builder $tags): Builder => $tags->where('name', 'like', '%'.$search.'%'));
            });
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '' && $tag !== 'all') {
            $query->whereHas('tags', fn (Builder $tags): Builder => $tags->where('name', $tag));
        }

        $optIn = (string) ($filters['optin'] ?? 'all');
        if ($optIn === 'opted-in') {
            $query->where('opt_in_status', ContactOptInStatus::Subscribed->value);
        } elseif ($optIn === 'not-opted-in') {
            $query->where('opt_in_status', '!=', ContactOptInStatus::Subscribed->value);
        }

        return $query;
    }

    public function findByPhone(int $workspaceId, string $phone): ?Contact
    {
        return Contact::query()
            ->where('workspace_id', $workspaceId)
            ->where('phone', $this->normalizePhone($phone))
            ->first();
    }

    public function findOrCreate(int $workspaceId, string $phone, ?string $name = null): Contact
    {
        $normalized = $this->normalizePhone($phone);

        return Contact::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'phone' => $normalized],
            ['name' => $name ?? $normalized, 'source' => ContactSource::Import->value],
        );
    }

    public function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (! str_starts_with($phone, '+')) {
            throw ValidationException::withMessages([
                'phone' => 'Enter the phone number in international E.164 format, for example +14155552671.',
            ]);
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($phone, null);
        } catch (NumberParseException) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid international phone number.',
            ]);
        }

        if (! $util->isValidNumber($number)) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid international phone number.',
            ]);
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function validTagIds(int $workspaceId, array $tagIds): array
    {
        return ContactTag::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', array_filter($tagIds))
            ->pluck('id')
            ->all();
    }

    public function validGroupIds(int $workspaceId, array $groupIds): array
    {
        return ContactGroup::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', array_filter($groupIds))
            ->pluck('id')
            ->all();
    }

    public function tagIdsForNames(int $workspaceId, array $names): array
    {
        return array_map(fn (string $name): int => ContactTag::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
            ['name' => $name],
        )->id, $this->cleanNames($names));
    }

    public function groupIdsForNames(int $workspaceId, array $names): array
    {
        return array_map(fn (string $name): int => ContactGroup::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
            ['name' => $name],
        )->id, $this->cleanNames($names));
    }

    protected function cleanNames(array $names): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($name): string => trim((string) $name),
            $names,
        ))));
    }

    protected function cleanCustomFields(array $fields): array
    {
        $cleaned = [];

        foreach ($fields as $key => $value) {
            $key = strtolower(trim((string) $key));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: '';
            $key = trim($key, '_');
            if (is_bool($value)) {
                // Keep boolean value as is
            } else {
                $value = is_scalar($value) ? trim((string) $value) : $value;
            }

            if ($key === '' || $value === '' || $value === null) {
                continue;
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    protected function mergeContacts(Contact $keep, Contact $delete): void
    {
        $fields = ['name', 'country', 'city'];
        $updates = [];
        foreach ($fields as $field) {
            if (empty($keep->{$field}) && ! empty($delete->{$field})) {
                $updates[$field] = $delete->{$field};
            }
        }

        $keepCustom = $keep->custom_fields ?? [];
        $deleteCustom = $delete->custom_fields ?? [];
        $mergedCustom = array_merge($deleteCustom, $keepCustom);
        if ($mergedCustom !== $keepCustom) {
            $updates['custom_fields'] = $mergedCustom;
        }

        if (! empty($updates)) {
            $keep->update($updates);
        }

        $keep->tags()->syncWithoutDetaching($delete->tags()->pluck('contact_tags.id')->all());
        $keep->groups()->syncWithoutDetaching($delete->groups()->pluck('contact_groups.id')->all());

        foreach ($delete->identities as $identity) {
            $exists = $keep->identities()
                ->where('provider', $identity->provider)
                ->where('provider_contact_id', $identity->provider_contact_id)
                ->exists();
            if ($exists) {
                $identity->delete();
            } else {
                $identity->update(['contact_id' => $keep->id]);
            }
        }

        DB::table('conversations')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);
        DB::table('messages')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);
        DB::table('leads')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);
        DB::table('campaign_recipients')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);
        DB::table('telegram_opt_in_tokens')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);
        DB::table('chatbot_widget_sessions')->where('contact_id', $delete->id)->update(['contact_id' => $keep->id]);

        $existingSegments = DB::table('contact_segment')->where('contact_id', $keep->id)->pluck('segment_id')->all();
        DB::table('contact_segment')
            ->where('contact_id', $delete->id)
            ->whereIn('segment_id', $existingSegments)
            ->delete();
        DB::table('contact_segment')
            ->where('contact_id', $delete->id)
            ->update(['contact_id' => $keep->id]);

        $delete->delete();
    }

    protected function dispatchTagAdded(Contact $contact, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            rescue(function () use ($contact, $tagId): void {
                app(AutomationDispatcher::class)->dispatch([
                    'type' => 'tag_added',
                    'workspace_id' => $contact->workspace_id,
                    'contact_id' => $contact->id,
                    'tag_id' => $tagId,
                    'event_key' => 'tag-added:'.$contact->id.':'.$tagId,
                ]);
            }, report: false);
        }
    }
}
