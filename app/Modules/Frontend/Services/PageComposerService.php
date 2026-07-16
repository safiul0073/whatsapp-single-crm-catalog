<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\Page;

class PageComposerService
{
    public function __construct(
        protected ThemeRegistry $themes
    ) {}

    public function syncSections(Page $page, array $sections): void
    {
        $ids = array_values(array_filter($sections, fn ($id) => ! empty($id)));

        $pivot = [];

        foreach ($ids as $index => $sectionId) {
            $pivot[(int) $sectionId] = [
                'sort_order' => $index,
                'visibility_rules' => null,
            ];
        }

        $page->sections()->sync($pivot);
    }

    public function compatibleThemes(Page $page): array
    {
        $sectionTypes = $page->sections->pluck('type')->filter()->unique()->values()->all();

        return collect($this->themes->all())
            ->filter(fn (array $theme) => collect($sectionTypes)->every(
                fn (string $type) => $this->themes->supportsSection($theme['key'], $type)
            ))
            ->keys()
            ->all();
    }
}
