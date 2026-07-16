<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FrontendPageService
{
    public function __construct(
        protected ThemeRegistry $themes,
        protected PageComposerService $composer,
        protected MenuRenderService $menus
    ) {}

    public function listPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Page::query()->with(['sections']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Page
    {
        DB::beginTransaction();

        try {
            $this->syncHomePageFlag((bool) ($data['is_home'] ?? false));
            $page = Page::create($this->pagePayload($data));
            $this->composer->syncSections($page, $data['sections'] ?? []);
            $this->menus->clearCache();

            DB::commit();

            return $page->fresh(['sections']);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function update(Page $page, array $data): Page
    {
        DB::beginTransaction();

        try {
            $this->syncHomePageFlag((bool) ($data['is_home'] ?? false), $page->id);
            $page->update($this->pagePayload($data, $page));
            $this->composer->syncSections($page, $data['sections'] ?? []);
            $this->menus->clearCache();

            DB::commit();

            return $page->fresh(['sections']);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function delete(Page $page): bool
    {
        if ($page->is_system || $page->is_home) {
            return false;
        }

        $deleted = (bool) $page->delete();

        if ($deleted) {
            $this->menus->clearCache();
        }

        return $deleted;
    }

    public function findBySlug(string $slug): ?Page
    {
        return Page::published()
            ->where('slug', $slug)
            ->with(['sections', 'pageSections'])
            ->first();
    }

    public function homePage(): ?Page
    {
        return Page::published()
            ->where('is_home', true)
            ->with(['sections', 'pageSections'])
            ->first();
    }

    public function pagePayload(array $data, ?Page $page = null): array
    {
        $status = $data['status'];
        $publishedAt = $page?->published_at;

        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        if ($status !== 'published') {
            $publishedAt = null;
        }

        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?: Str::slug($data['title']),
            'status' => $status,
            'excerpt' => $data['excerpt'] ?? null,
            'default_layout' => $data['default_layout'] ?: $this->themes->defaultLayoutKey($this->themes->defaultThemeKey()),
            'theme_overrides' => $data['theme_overrides'] ?? [],
            'is_system' => (bool) ($page?->is_system ?? false),
            'is_home' => (bool) ($data['is_home'] ?? false),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_image_media_id' => $data['meta_image_media_id'] ?? null,
            'published_at' => $publishedAt,
        ];
    }

    protected function syncHomePageFlag(bool $isHome, ?int $ignorePageId = null): void
    {
        if (! $isHome) {
            return;
        }

        Page::query()
            ->when($ignorePageId, fn ($query) => $query->where('id', '!=', $ignorePageId))
            ->where('is_home', true)
            ->update(['is_home' => false]);
    }
}
