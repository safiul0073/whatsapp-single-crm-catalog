<?php

namespace App\Modules\Media\Models;

use App\Models\User;
use App\Modules\Media\Services\ImageVariantService;
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

    /** @var array<string, bool> */
    protected array $variantCache = [];

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

        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->variantUrl('thumb');
    }

    /** URL of a resized WebP copy, falling back to the original until it has been generated. */
    public function variantUrl(string $size): string
    {
        return $this->hasVariant($size) ? Storage::disk($this->disk)->url(ImageVariantService::variantPath($this->path, $size)) : $this->url;
    }

    public function hasVariant(string $size): bool
    {
        if (str_starts_with((string) $this->path, 'https://') || ! isset(ImageVariantService::SIZES[$size]) || ! $this->isImage()) {
            return false;
        }

        return $this->variantCache[$size] ??= Storage::disk($this->disk)->exists(ImageVariantService::variantPath($this->path, $size));
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
