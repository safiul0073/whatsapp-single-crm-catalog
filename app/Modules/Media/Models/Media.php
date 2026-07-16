<?php

namespace App\Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'name',
        'file_name',
        'original_name',
        'mime_type',
        'extension',
        'type',
        'size',
        'disk',
        'path',
        'alt',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Accessors ──

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        if ($this->disk === 'public') {
            return asset('storage/'.$this->path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->url;
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    // ── Helpers ──

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    // ── Relationships ──

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
