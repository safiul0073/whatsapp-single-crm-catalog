<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\FrontendMenuItem;
use App\Modules\Frontend\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuTreeService
{
    public function decodePayload(?string $payload): array
    {
        if (! $payload) {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'items_payload' => __('The submitted menu structure could not be read.'),
            ]);
        }

        if ($this->looksLikeFlatPayload($decoded)) {
            $decoded = $this->inflateFlatItems($decoded);
        }

        return $this->validateNestedItems($decoded);
    }

    public function sync(FrontendMenu $menu, array $items): FrontendMenu
    {
        DB::transaction(function () use ($menu, $items) {
            $existing = $menu->items()->get()->keyBy('id');
            $keepIds = [];
            $this->syncBranch($menu, $items, $existing, $keepIds);

            $menu->items()
                ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds), fn ($query) => $query)
                ->delete();
        });

        return $menu->fresh(['items.children', 'items.linkable']);
    }

    public function serializeForEditor(FrontendMenu $menu): array
    {
        $menu->loadMissing(['items.linkable']);
        $grouped = $menu->items->sortBy('sort_order')->groupBy('parent_id');

        return $this->mapItemsForEditor($grouped, null);
    }

    public function maxLevels(array $items): int
    {
        return $this->maxDepth($items, 1);
    }

    protected function validateNestedItems(array $items, int $depth = 0, string $path = 'items_payload'): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            $validator = Validator::make($item, [
                'id' => ['nullable', 'integer'],
                'temp_key' => ['nullable', 'string', 'max:100'],
                'item_type' => ['required', Rule::in(['internal', 'external', 'group'])],
                'label' => ['required', 'string', 'max:255'],
                'linkable_type' => ['nullable', 'string', 'max:255'],
                'linkable_id' => ['nullable', 'integer'],
                'url' => ['nullable', 'string', 'max:2000'],
                'target' => ['nullable', Rule::in(['_self', '_blank'])],
                'is_visible' => ['nullable', 'boolean'],
                'children' => ['nullable', 'array'],
            ]);

            $data = $validator->validated();
            $data['temp_key'] = $data['temp_key'] ?? 'menu-item-'.$depth.'-'.$index.'-'.str()->random(6);
            $data['target'] = $data['target'] ?? '_self';
            $data['is_visible'] = (bool) ($data['is_visible'] ?? true);
            $data['children'] = is_array($data['children'] ?? null) ? $data['children'] : [];

            if ($depth > 1) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}" => __('Menu items cannot be nested deeper than 2 levels.'),
                ]);
            }

            if ($data['item_type'] === 'internal') {
                if (($data['linkable_type'] ?? null) !== Page::class || empty($data['linkable_id'])) {
                    throw ValidationException::withMessages([
                        "{$path}.{$index}" => __('Internal links must target a frontend page.'),
                    ]);
                }

                if (! Page::query()->whereKey($data['linkable_id'])->exists()) {
                    throw ValidationException::withMessages([
                        "{$path}.{$index}" => __('The selected page for one of the menu items no longer exists.'),
                    ]);
                }

                $data['url'] = null;
            }

            if ($data['item_type'] === 'external') {
                if (empty($data['url'])) {
                    throw ValidationException::withMessages([
                        "{$path}.{$index}" => __('External links must include a URL.'),
                    ]);
                }

                $data['linkable_type'] = null;
                $data['linkable_id'] = null;
            }

            if ($data['item_type'] === 'group') {
                $data['linkable_type'] = null;
                $data['linkable_id'] = null;
                $data['url'] = null;
                $data['target'] = '_self';
            }

            if ($data['children'] !== []) {
                $data['children'] = $this->validateNestedItems(
                    $data['children'],
                    $depth + 1,
                    "{$path}.{$index}.children"
                );
            }

            $normalized[] = $data;
        }

        return $normalized;
    }

    protected function looksLikeFlatPayload(array $items): bool
    {
        return collect($items)->contains(fn ($item) => is_array($item) && array_key_exists('depth', $item));
    }

    protected function inflateFlatItems(array $items): array
    {
        $tree = [];
        $lastRootIndex = null;

        foreach ($items as $item) {
            $item['children'] = [];

            if ((int) ($item['depth'] ?? 0) > 0 && $lastRootIndex !== null) {
                $tree[$lastRootIndex]['children'][] = $item;

                continue;
            }

            $item['depth'] = 0;
            $tree[] = $item;
            $lastRootIndex = array_key_last($tree);
        }

        return array_values($tree);
    }

    protected function syncBranch(FrontendMenu $menu, array $items, $existing, array &$keepIds, ?int $parentId = null): void
    {
        foreach (array_values($items) as $index => $item) {
            $payload = [
                'frontend_menu_id' => $menu->id,
                'parent_id' => $parentId,
                'item_type' => $item['item_type'],
                'label' => $item['label'],
                'linkable_type' => $item['linkable_type'] ?? null,
                'linkable_id' => $item['linkable_id'] ?? null,
                'url' => $item['url'] ?? null,
                'target' => $item['target'] ?? '_self',
                'sort_order' => $index,
                'is_visible' => (bool) ($item['is_visible'] ?? true),
            ];

            if (! empty($item['id']) && $existing->has((int) $item['id'])) {
                $menuItem = $existing[(int) $item['id']];
                $menuItem->update($payload);
            } else {
                $menuItem = FrontendMenuItem::create($payload);
            }

            $keepIds[] = $menuItem->id;

            if (! empty($item['children'])) {
                $this->syncBranch($menu, $item['children'], $existing, $keepIds, $menuItem->id);
            }
        }
    }

    protected function mapItemsForEditor($grouped, ?int $parentId): array
    {
        $groupKey = $parentId ?? '';
        $items = $grouped->get($groupKey, $parentId === null ? ($grouped->get(null, []) ?: []) : []);

        return collect($items)
            ->sortBy('sort_order')
            ->values()
            ->map(function (FrontendMenuItem $item) use ($grouped) {
                return [
                    'id' => $item->id,
                    'temp_key' => 'menu-item-'.$item->id,
                    'item_type' => $item->item_type,
                    'label' => $item->label,
                    'linkable_type' => $item->linkable_type,
                    'linkable_id' => $item->linkable_id,
                    'url' => $item->url,
                    'target' => $item->target ?: '_self',
                    'is_visible' => (bool) $item->is_visible,
                    'children' => $this->mapItemsForEditor($grouped, $item->id),
                ];
            })
            ->all();
    }

    protected function maxDepth(array $items, int $currentDepth): int
    {
        $max = $currentDepth;

        foreach ($items as $item) {
            if (! empty($item['children'])) {
                $max = max($max, $this->maxDepth($item['children'], $currentDepth + 1));
            }
        }

        return $max;
    }
}
