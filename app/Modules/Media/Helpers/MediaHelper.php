<?php

use App\Modules\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('media_url')) {
    /**
     * Get the public URL for a media record by ID.
     *
     * Resolved URLs are memoized for the duration of the request so repeated
     * lookups of the same media id (e.g. across rendered sections) do not issue
     * a database query each time.
     */
    function media_url(mixed $mediaId): ?string
    {
        if (! $mediaId) {
            return null;
        }

        static $cache = [];

        if (! array_key_exists($mediaId, $cache)) {
            $cache[$mediaId] = Media::find($mediaId)?->url;
        }

        return $cache[$mediaId];
    }
}

if (! function_exists('avatar_url')) {
    function avatar_url(mixed $avatar): ?string
    {
        if (! $avatar) {
            return null;
        }

        $avatar = (string) $avatar;

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        if (is_numeric($avatar)) {
            return media_url($avatar);
        }

        return Storage::url($avatar);
    }
}
