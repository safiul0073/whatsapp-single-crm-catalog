<?php

namespace App\Modules\Leads\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'workspace_id',
        'contact_id',
        'name',
        'company',
        'phone',
        'email',
        'country',
        'city',
        'place',
        'category',
        'stage',
        'source',
        'external_source',
        'external_id',
        'score',
        'contact_status',
        'verification_status',
        'ai_prompt',
        'criteria',
        'value',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'metadata' => 'array',
            'score' => 'integer',
            'value' => 'decimal:2',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isConverted(): bool
    {
        return $this->contact_id !== null || $this->contact_status === 'converted';
    }
}
