<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendSection;
use Illuminate\Support\Str;

class FrontendTranslationService
{
    protected array $nonTranslatableKeys = [
        'accept',
        'alt',
        'background_image_media_id',
        'color',
        'fallback_src',
        'featured_image_media_id',
        'form_subject',
        'href',
        'icon',
        'id',
        'image_media_id',
        'is_visible',
        'item_type',
        'key',
        'layout',
        'link',
        'linkable_id',
        'linkable_type',
        'logo_text',
        'logo_media_id',
        'media_id',
        'number',
        'preview_image_media_id',
        'slug',
        'status',
        'target',
        'type',
        'url',
        'value',
    ];

    protected array $nonTranslatableKeySuffixes = [
        '_class',
        '_classes',
        '_color',
        '_colors',
        '_email',
        '_id',
        '_ids',
        '_image',
        '_image_media_id',
        '_link',
        '_media_id',
        '_phone',
        '_src',
        '_target',
        '_url',
    ];

    public function translateSection(FrontendSection $section): FrontendSection
    {
        $clone = clone $section;
        $clone->setRawAttributes($section->getAttributes(), true);
        $clone->setRelations($section->getRelations());
        $clone->data = $this->translateValue($section->data ?? []);

        return $clone;
    }

    public function translateArray(array $values): array
    {
        return $this->translateValue($values);
    }

    public function translateText(?string $value, ?string $key = null): ?string
    {
        if ($value === null || ! $this->shouldTranslate($value, $key)) {
            return $value;
        }

        if (str_contains($value, "\n")) {
            return collect(explode("\n", $value))
                ->map(fn (string $line) => $this->translateText($line, $key) ?? $line)
                ->implode("\n");
        }

        return __($value);
    }

    protected function translateValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $translated = [];

            foreach ($value as $itemKey => $itemValue) {
                $translated[$itemKey] = $this->translateValue($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $translated;
        }

        if (is_string($value)) {
            return $this->translateText($value, $key);
        }

        return $value;
    }

    protected function shouldTranslate(string $value, ?string $key = null): bool
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/\pL/u', $value)) {
            return false;
        }

        if ($key !== null && $this->isNonTranslatableKey($key)) {
            return false;
        }

        if (
            Str::startsWith($value, ['http://', 'https://', 'mailto:', 'tel:', '#', '/', 'assets/', 'storage/'])
            || preg_match('/^[a-z0-9_.\/-]+\.(svg|png|jpe?g|webp|gif|css|js)$/i', $value)
            || preg_match('/^[\w.+-]+@[\w.-]+\.[a-z]{2,}$/i', $value)
            || preg_match('/^ph(-|\s+ph-)/', $value)
            || preg_match('/^#[0-9a-f]{3,8}$/i', $value)
            || preg_match('/^(primary|accent|deep|success|warning|danger|error|info|neutral|blue|green|navy|white|black|left|right|center|_self|_blank)$/i', $value)
        ) {
            return false;
        }

        return true;
    }

    protected function isNonTranslatableKey(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, $this->nonTranslatableKeys, true)) {
            return true;
        }

        foreach ($this->nonTranslatableKeySuffixes as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
