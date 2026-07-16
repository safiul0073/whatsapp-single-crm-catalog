<?php

namespace App\Modules\ContactMessages\Models;

use App\Models\Admin;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessageReply extends Model
{
    protected $fillable = [
        'contact_message_id',
        'admin_id',
        'notification_log_id',
        'source',
        'template_slug',
        'recipient_email',
        'subject',
        'body',
        'template_variables',
        'queued_at',
    ];

    protected function casts(): array
    {
        return [
            'template_variables' => 'array',
            'queued_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class);
    }
}
