<?php

namespace App\Modules\Contacts\Models;

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'workspace_id', 'assigned_to', 'name', 'phone', 'email', 'country', 'city',
        'custom_fields', 'source', 'opt_in_status', 'opt_in_at',
        'opt_out_at', 'blocked_at', 'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'opt_in_status' => ContactOptInStatus::class,
            'source' => ContactSource::class,
            'custom_fields' => 'array',
            'opt_in_at' => 'datetime',
            'opt_out_at' => 'datetime',
            'blocked_at' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Contact $contact): void {
            rescue(function () use ($contact): void {
                app(AutomationDispatcher::class)->dispatch([
                    'type' => 'contact_created',
                    'workspace_id' => $contact->workspace_id,
                    'contact_id' => $contact->id,
                    'body' => null,
                    'event_key' => 'contact-created:'.$contact->id,
                ]);
            }, report: false);
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ContactProviderIdentity::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_contact')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_contact')
            ->withTimestamps();
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function crmTasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    public function isOptedOut(): bool
    {
        return $this->opt_in_status === ContactOptInStatus::Unsubscribed
            || $this->opt_out_at !== null
            || $this->blocked_at !== null;
    }

    public function hasValidPhone(): bool
    {
        return is_string($this->phone) && preg_match('/^\+[1-9]\d{7,14}$/', $this->phone) === 1;
    }
}
