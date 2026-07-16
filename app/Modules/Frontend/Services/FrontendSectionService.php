<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class FrontendSectionService
{
    public function __construct(
        protected SectionRegistry $sections,
        protected ThemeRegistry $themes,
        protected ActiveThemeResolver $activeThemeResolver
    ) {}

    public function listPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FrontendSection::query()->withCount('pages');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): FrontendSection
    {
        $type = $data['type'];

        return FrontendSection::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'type' => $type,
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'data' => $this->normalizeData($type, $data['data'] ?? []),
            'theme_overrides' => [],
            'preview_image_media_id' => $data['preview_image_media_id'] ?? null,
        ]);
    }

    public function update(FrontendSection $section, array $data): FrontendSection
    {
        $section->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'data' => $this->normalizeData($section->type, $data['data'] ?? []),
            'preview_image_media_id' => $data['preview_image_media_id'] ?? null,
        ]);

        return $section->fresh();
    }

    public function delete(FrontendSection $section): bool
    {
        return (bool) $section->delete();
    }

    public function normalizeData(string $type, array $data): array
    {
        $definition = $this->sections->get($type);
        $fields = $definition['fields'] ?? [];
        $normalized = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? ($field['default'] ?? null);
            $normalized[$key] = $this->normalizeValue($field, $value);
        }

        return $normalized;
    }

    protected function normalizeValue(array $field, mixed $value): mixed
    {
        $type = $field['type'] ?? 'text';

        return match ($type) {
            'number' => $value === null || $value === '' ? null : (is_numeric($value) ? $value + 0 : $value),
            'boolean', 'feature' => (bool) $value,
            'checkbox', 'tags' => is_array($value) ? array_values($value) : (is_string($value) && $value !== '' ? array_values(array_filter(explode(',', $value))) : []),
            'repeater' => $this->normalizeRepeaterValue($value),
            default => $value,
        };
    }

    protected function normalizeRepeaterValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => is_array($item) && count(array_filter($item, fn ($sub) => $sub !== null && $sub !== '')) > 0));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => is_array($item) && count(array_filter($item, fn ($sub) => $sub !== null && $sub !== '')) > 0));
            }
        }

        return [];
    }

    public function annotate(FrontendSection $section): array
    {
        $activeTheme = $this->activeThemeResolver->resolve();
        $compatibleThemes = [];

        foreach ($this->themes->all() as $themeKey => $theme) {
            if ($this->themes->supportsSection($themeKey, $section->type)) {
                $compatibleThemes[] = $themeKey;
            }
        }

        return [
            'active_theme_supported' => in_array($activeTheme, $compatibleThemes, true),
            'compatible_themes' => $compatibleThemes,
        ];
    }
}
