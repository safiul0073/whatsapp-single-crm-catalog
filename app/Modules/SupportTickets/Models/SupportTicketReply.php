<?php

namespace App\Modules\SupportTickets\Models;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'admin_id',
        'message',
        'is_staff',
    ];

    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'reply_id');
    }

    public function authorName(): string
    {
        if ($this->is_staff) {
            return $this->admin?->name ?? __('Support Team');
        }

        return $this->user?->name ?? __('You');
    }
}
