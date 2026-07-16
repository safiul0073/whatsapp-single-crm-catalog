<?php

namespace App\Modules\ContactMessages\Models;

use App\Modules\ContactMessages\Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'company',
        'interest',
        'message',
        'status',
        'ip_address',
        'user_agent',
        'source_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_READ => __('Read'),
            self::STATUS_ARCHIVED => __('Archived'),
            default => __('New'),
        };
    }

    public function getStatusBadgeVariantAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_READ => 'info',
            self::STATUS_ARCHIVED => 'neutral',
            default => 'success',
        };
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class);
    }
}
