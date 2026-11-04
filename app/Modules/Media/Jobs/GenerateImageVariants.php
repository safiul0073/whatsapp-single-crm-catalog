<?php

namespace App\Modules\Media\Jobs;

use App\Modules\Media\Events\ImageVariantsGenerated;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\ImageVariantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $mediaId) {}

    public function handle(ImageVariantService $variants): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media && $variants->generate($media) !== []) {
            ImageVariantsGenerated::dispatch($media->id);
        }
    }
}
