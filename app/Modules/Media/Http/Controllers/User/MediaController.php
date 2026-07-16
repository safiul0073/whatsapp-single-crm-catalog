<?php

namespace App\Modules\Media\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Media\Http\Requests\UploadUserMediaRequest;
use App\Modules\Media\Services\MediaService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    use ApiResponse;

    public function __construct(protected MediaService $service) {}

    public function browse(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'type' => $request->get('type'),
            'uploaded_by' => auth()->id(),
        ];

        $media = $this->service->listPaginated($filters, 24);

        $items = $media->getCollection()->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'original_name' => $item->original_name,
            'url' => $item->url,
            'thumbnail_url' => $item->thumbnail_url,
            'type' => $item->type,
            'mime_type' => $item->mime_type,
            'extension' => $item->extension,
            'human_size' => $item->human_size,
            'created_at' => $item->created_at->diffForHumans(),
        ]);

        return $this->successResponse([
            'items' => $items,
            'has_more' => $media->hasMorePages(),
            'next_page' => $media->hasMorePages() ? $media->currentPage() + 1 : null,
        ]);
    }

    public function upload(UploadUserMediaRequest $request): JsonResponse
    {
        $media = $this->service->upload($request->file('file'));

        return $this->successResponse([
            'id' => $media->id,
            'name' => $media->name,
            'original_name' => $media->original_name,
            'url' => $media->url,
            'thumbnail_url' => $media->thumbnail_url,
            'type' => $media->type,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'human_size' => $media->human_size,
            'created_at' => $media->created_at->diffForHumans(),
        ], __('File uploaded successfully.'), 201);
    }
}
