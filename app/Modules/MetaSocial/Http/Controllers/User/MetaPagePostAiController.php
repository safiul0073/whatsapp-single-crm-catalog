<?php

namespace App\Modules\MetaSocial\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MetaSocial\Http\Requests\GenerateMetaPostCaptionRequest;
use App\Modules\MetaSocial\Http\Requests\GenerateMetaPostImageRequest;
use App\Modules\MetaSocial\Services\MetaPostAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaPagePostAiController extends Controller
{
    public function __construct(protected MetaPostAiService $ai) {}

    public function productAssets(Request $request, string $product): JsonResponse
    {
        return response()->json($this->ai->assets($request->user(), (int) $product));
    }

    public function caption(GenerateMetaPostCaptionRequest $request): JsonResponse
    {
        return response()->json(['message' => $this->ai->caption($request->user(), $request->validated())]);
    }

    public function image(GenerateMetaPostImageRequest $request): JsonResponse
    {
        $media = $this->ai->image($request->user(), $request->validated());

        return response()->json(['media_id' => $media->id, 'url' => $this->ai->mediaUrl($media)]);
    }
}
