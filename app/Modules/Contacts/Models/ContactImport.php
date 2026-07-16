<?php

namespace App\Modules\Contacts\Models;

use App\Models\User;
use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImport extends Model
{
    protected $table = 'contact_imports';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'file_name',
        'file_path',
        'source',
        'total_rows',
        'created_rows',
        'updated_rows',
        'imported_rows',
        'skipped_rows',
        'failed_rows',
        'column_mapping',
        'options',
        'errors',
        'summary',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'options' => 'array',
            'errors' => 'array',
            'summary' => 'array',
            'status' => ContactImportStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
