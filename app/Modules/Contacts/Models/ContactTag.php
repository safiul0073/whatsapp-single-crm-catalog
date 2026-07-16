<?php

namespace App\Modules\Contacts\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactTag extends Model
{
    protected $table = 'contact_tags';

    protected $fillable = ['workspace_id', 'name', 'slug', 'color'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_contact')
            ->withTimestamps();
    }
}
