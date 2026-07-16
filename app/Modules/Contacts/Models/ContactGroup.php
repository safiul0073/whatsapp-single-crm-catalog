<?php

namespace App\Modules\Contacts\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactGroup extends Model
{
    protected $table = 'contact_groups';

    protected $fillable = ['workspace_id', 'name', 'slug', 'description', 'type', 'rules'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_contact')
            ->withTimestamps();
    }
}
