<?php

namespace App\Modules\Languages\Services;

use App\Modules\Languages\Models\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LanguagesService
{
    public function listPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Language::query();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%")
                    ->orWhere('native_name', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): Language
    {
        return Language::findOrFail($id);
    }

    public function create(array $data): Language
    {
        if (! empty($data['is_default'])) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $language = Language::create($data);

        // Auto-create JSON translation file if it doesn't exist
        $jsonPath = lang_path("{$language->code}.json");
        if (! file_exists($jsonPath)) {
            file_put_contents($jsonPath, json_encode(new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->clearCache();

        return $language;
    }

    public function update(Language $language, array $data): Language
    {
        if (! empty($data['is_default'])) {
            Language::where('is_default', true)->where('id', '!=', $language->id)->update(['is_default' => false]);
        }

        $language->update($data);
        $this->clearCache();

        return $language->fresh();
    }

    public function delete(Language $language): bool
    {
        if ($language->is_default) {
            throw new \RuntimeException('Cannot delete the default language.');
        }

        $result = $language->delete();
        $this->clearCache();

        return $result;
    }

    public function toggleStatus(Language $language): Language
    {
        if ($language->is_default && $language->is_active) {
            throw new \RuntimeException('Cannot deactivate the default language.');
        }

        $language->update(['is_active' => ! $language->is_active]);
        $this->clearCache();

        return $language->fresh();
    }

    public function setDefault(Language $language): Language
    {
        Language::where('is_default', true)->update(['is_default' => false]);

        $language->update([
            'is_default' => true,
            'is_active' => true,
        ]);
        $this->clearCache();

        return $language->fresh();
    }

    public function getActive(): Collection
    {
        return Cache::rememberForever('languages.active', function () {
            return Language::active()->ordered()->get();
        });
    }

    public function getDefault(): ?Language
    {
        return Cache::rememberForever('languages.default', function () {
            return Language::where('is_default', true)->first();
        });
    }

    public function clearCache(): void
    {
        Cache::forget('languages.active');
        Cache::forget('languages.default');
    }

    public function getSourceKeys(): array
    {
        $path = lang_path('en.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    public function getTranslations(string $code): array
    {
        $path = lang_path("{$code}.json");

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    public function saveTranslations(string $code, array $translations): void
    {
        $path = lang_path("{$code}.json");

        // Remove empty values to keep the file clean
        $translations = array_filter($translations, fn ($value) => $value !== '' && $value !== null);

        ksort($translations);

        file_put_contents($path, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    }
}
