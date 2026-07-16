<?php

namespace App\Modules\Segments\Models;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Segment extends Model
{
    protected $fillable = ['workspace_id', 'name', 'description', 'type', 'rules'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_segment')->withTimestamps();
    }
}
