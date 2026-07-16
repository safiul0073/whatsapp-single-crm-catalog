<?php

namespace App\Modules\Contacts\Services;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Support\Str;

class ContactTagService
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function listForUser(?User $user)
    {
        $workspace = $this->workspaces->current($user);

        return ContactTag::query()
            ->withCount('contacts')
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate(20);
    }

    public function allForUser(?User $user)
    {
        $workspace = $this->workspaces->current($user);

        return ContactTag::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get();
    }

    public function storeForUser(?User $user, array $data): ContactTag
    {
        $workspace = $this->workspaces->current($user);

        return ContactTag::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);
    }

    public function updateForUser(?User $user, string $tag, array $data): ContactTag
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactTag::query()->where('workspace_id', $workspace->id)->findOrFail($tag);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $model->update($data);

        return $model->fresh();
    }

    public function deleteForUser(?User $user, string $tag): void
    {
        $workspace = $this->workspaces->current($user);
        ContactTag::query()->where('workspace_id', $workspace->id)->findOrFail($tag)->delete();
    }

    public function contacts(?User $user, string $tag)
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactTag::query()->where('workspace_id', $workspace->id)->findOrFail($tag);

        return $model->contacts()->latest()->paginate(20);
    }

    public function bulkAttach(?User $user, string $tag, array $contactIds): void
    {
        $workspace = $this->workspaces->current($user);
        $model = ContactTag::query()->where('workspace_id', $workspace->id)->findOrFail($tag);
        $contacts = Contact::query()->whereIn('id', $contactIds)->where('workspace_id', $workspace->id)->get();

        $model->contacts()->syncWithoutDetaching($contacts->pluck('id')->toArray());
    }
}
