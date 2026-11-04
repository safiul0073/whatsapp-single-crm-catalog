<?php

namespace App\Modules\Media\Console;

use App\Modules\Media\Jobs\GenerateImageVariants;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\ImageVariantService;
use Illuminate\Console\Command;

class GenerateMediaVariants extends Command
{
    protected $signature = 'media:generate-variants {--sync : Generate in this process instead of queueing} {--force : Regenerate existing variants}';

    protected $description = 'Create resized WebP copies of uploaded images for faster storefront loading';

    public function handle(ImageVariantService $variants): int
    {
        $count = 0;
        Media::query()->where('type', 'image')->whereIn('extension', ['jpg', 'jpeg', 'png', 'webp'])->chunkById(200, function ($items) use ($variants, &$count): void {
            foreach ($items as $media) {
                if (! $this->option('force') && $media->hasVariant('thumb')) {
                    continue;
                }
                $this->option('sync') ? $variants->generate($media) : GenerateImageVariants::dispatch($media->id);
                $count++;
            }
        });
        $this->info(($this->option('sync') ? 'Generated' : 'Queued')." variants for {$count} images.");

        return self::SUCCESS;
    }
}
