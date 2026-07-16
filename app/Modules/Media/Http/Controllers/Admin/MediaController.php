<?php

namespace App\Modules\Media\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Media\Services\MediaService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class MediaController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:media.view', only: ['index', 'browse']),
            new Middleware('permission:media.create', only: ['upload']),
            new Middleware('permission:media.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        protected MediaService $service
    ) {}

    public function index(): View
    {
        return view('media::admin.index');
    }

    public function browse(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'type' => $request->get('type'),
        ];

        $media = $this->service->listPaginated($filters, 24);

        $items = $media->getCollection()->map(function ($item) {
            return [
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
            ];
        });

        return $this->successResponse([
            'items' => $items,
            'has_more' => $media->hasMorePages(),
            'next_page' => $media->hasMorePages() ? $media->currentPage() + 1 : null,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

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

    public function destroy(Request $request, int $media): JsonResponse
    {
        $mediaItem = $this->service->findOrFail($media);
        $this->service->delete($mediaItem);

        return $this->successResponse(null, __('File deleted successfully.'));
    }
}
