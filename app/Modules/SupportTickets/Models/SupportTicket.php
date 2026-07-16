<?php

namespace App\Modules\SupportTickets\Models;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_admin_id',
        'subject',
        'status',
        'priority',
        'last_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'last_replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'open',
            'in_progress' => 'progress',
            'resolved' => 'resolved',
            'closed' => 'closed',
            default => 'open',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
            default => ucfirst($this->priority),
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'low',
            'medium' => 'med',
            'high' => 'high',
            'urgent' => 'urgent',
            default => 'med',
        };
    }

    public function getFormattedIdAttribute(): string
    {
        return '#'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Badge variant used by admin panel tables */
    public function getStatusBadgeVariantAttribute(): string
    {
        return match ($this->status) {
            'open' => 'info',
            'in_progress' => 'warning',
            'resolved' => 'success',
            'closed' => 'default',
            default => 'default',
        };
    }

    /** Badge variant used by admin panel tables */
    public function getPriorityBadgeVariantAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'default',
            'medium' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
            default => 'default',
        };
    }
}
