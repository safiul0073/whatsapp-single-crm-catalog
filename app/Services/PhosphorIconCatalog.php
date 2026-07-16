<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class PhosphorIconCatalog
{
    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        $stylesheetPath = public_path('vendor/phosphor/regular/style.css');

        if (! File::exists($stylesheetPath)) {
            return [];
        }

        $cacheKey = 'phosphor-icons.'.md5($stylesheetPath.'|'.File::lastModified($stylesheetPath));

        return Cache::rememberForever($cacheKey, function () use ($stylesheetPath): array {
            preg_match_all('/\.ph\.ph-([a-z0-9-]+):before\s*\{/i', File::get($stylesheetPath), $matches);

            return collect($matches[1] ?? [])
                ->map(fn (string $icon): string => 'ph-'.$icon)
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }
}
