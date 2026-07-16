<?php

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function listPaginated(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $query = Media::query();

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    public function upload(UploadedFile $file, string $disk = 'public'): Media
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        $fileName = Str::ulid().'.'.$extension;
        $directory = 'media/'.now()->format('Y/m');
        $path = $file->storeAs($directory, $fileName, $disk);

        return Media::create([
            'name' => $name,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'type' => $this->determineType($mimeType),
            'size' => $size,
            'disk' => $disk,
            'path' => $path,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function delete(Media $media): bool
    {
        Storage::disk($media->disk)->delete($media->path);

        return $media->delete();
    }

    public function findOrFail(int $id): Media
    {
        return Media::findOrFail($id);
    }

    protected function determineType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_contains($mimeType, 'pdf'),
            str_contains($mimeType, 'word'),
            str_contains($mimeType, 'spreadsheet'),
            str_contains($mimeType, 'presentation'),
            str_contains($mimeType, 'text/') => 'document',
            str_contains($mimeType, 'zip'),
            str_contains($mimeType, 'rar'),
            str_contains($mimeType, 'tar') => 'archive',
            default => 'other',
        };
    }
}
