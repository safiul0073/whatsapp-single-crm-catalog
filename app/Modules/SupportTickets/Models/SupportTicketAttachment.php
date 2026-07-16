<?php

namespace App\Modules\SupportTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id',
        'reply_id',
        'uploaded_by_type',
        'uploaded_by_id',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'reply_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->morphTo(__FUNCTION__, 'uploaded_by_type', 'uploaded_by_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function downloadUrl(): string
    {
        return Storage::disk('support-attachments')->url($this->file_path);
    }
}
