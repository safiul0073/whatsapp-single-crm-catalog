<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Enums\CrmTaskPriority;
use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmTask extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'lead_id', 'contact_id', 'assigned_to', 'title', 'description', 'status', 'priority', 'due_at', 'completed_at', 'reminded_at'];

    protected function casts(): array
    {
        return ['status' => CrmTaskStatus::class, 'priority' => CrmTaskPriority::class, 'due_at' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime'];
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
