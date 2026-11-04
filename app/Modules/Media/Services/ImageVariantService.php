<?php

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageVariantService
{
    /** @var array<string, int> */
    public const SIZES = ['thumb' => 400, 'large' => 1200];

    private const RESIZABLE = ['jpg', 'jpeg', 'png', 'webp'];

    public static function variantPath(string $path, string $size): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $path).'-'.self::SIZES[$size].'.webp';
    }

    public function supports(Media $media): bool
    {
        return $media->isImage()
            && in_array(strtolower((string) $media->extension), self::RESIZABLE, true)
            && ! str_starts_with((string) $media->path, 'https://')
            && function_exists('imagewebp');
    }

    /**
     * Writes resized WebP copies next to the original and returns the sizes created.
     *
     * @return array<int, string>
     */
    public function generate(Media $media): array
    {
        if (! $this->supports($media)) {
            return [];
        }
        $disk = Storage::disk($media->disk);
        try {
            $source = @imagecreatefromstring((string) $disk->get($media->path));
        } catch (Throwable) {
            return [];
        }
        if (! $source) {
            return [];
        }

        $created = [];
        $width = imagesx($source);
        foreach (self::SIZES as $size => $target) {
            $resized = $width > $target ? $this->resize($source, $target) : $source;
            if (! $resized) {
                continue;
            }
            imagepalettetotruecolor($resized);
            imagesavealpha($resized, true);
            ob_start();
            imagewebp($resized, null, 80);
            $disk->put(self::variantPath($media->path, $size), (string) ob_get_clean(), ['visibility' => 'public']);
            if ($resized !== $source) {
                imagedestroy($resized);
            }
            $created[] = $size;
        }
        imagedestroy($source);

        return $created;
    }

    private function resize(GdImage $source, int $width): GdImage|false
    {
        $height = max(1, (int) round(imagesy($source) * $width / imagesx($source)));
        $target = imagecreatetruecolor($width, $height);
        if (! $target) {
            return false;
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

        return imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source)) ? $target : false;
    }

    public function delete(Media $media): void
    {
        foreach (array_keys(self::SIZES) as $size) {
            Storage::disk($media->disk)->delete(self::variantPath($media->path, $size));
        }
    }
}
