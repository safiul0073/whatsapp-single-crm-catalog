<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Enums\CrmActivityType;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'lead_id', 'contact_id', 'conversation_id', 'type', 'title', 'description', 'created_by', 'due_at', 'completed_at', 'metadata'];

    protected function casts(): array
    {
        return ['type' => CrmActivityType::class, 'due_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
