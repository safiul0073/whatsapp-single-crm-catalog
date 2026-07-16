<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendMenu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuService
{
    public function __construct(
        protected MenuTreeService $trees,
        protected MenuAssignmentService $assignments,
        protected MenuRenderService $render
    ) {}

    public function listPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FrontendMenu::query()->withCount('items');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): FrontendMenu
    {
        return DB::transaction(function () use ($data) {
            $menu = FrontendMenu::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']),
                'status' => $data['status'],
            ]);

            $items = $this->trees->decodePayload($data['items_payload'] ?? '[]');
            $this->trees->sync($menu, $items);
            $this->render->clearCache();

            return $menu->fresh(['items']);
        });
    }

    public function update(FrontendMenu $menu, array $data): FrontendMenu
    {
        return DB::transaction(function () use ($menu, $data) {
            $menu->update([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']),
                'status' => $data['status'],
            ]);

            $items = $this->trees->decodePayload($data['items_payload'] ?? '[]');
            $this->trees->sync($menu, $items);
            $this->render->clearCache();

            return $menu->fresh(['items']);
        });
    }

    public function delete(FrontendMenu $menu): void
    {
        $this->assignments->ensureNotAssigned($menu);

        DB::transaction(function () use ($menu) {
            $menu->items()->delete();
            $menu->delete();
        });

        $this->render->clearCache();
    }

    public function togglePublished(FrontendMenu $menu): FrontendMenu
    {
        $nextStatus = $menu->status === 'published' ? 'draft' : 'published';

        $menu->update(['status' => $nextStatus]);
        $this->render->clearCache();

        return $menu->fresh();
    }

    public function publishedOptions(): array
    {
        return FrontendMenu::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (FrontendMenu $menu) => [$menu->id => $menu->name])
            ->all();
    }

    public function initialEditorState(?FrontendMenu $menu = null): array
    {
        if (! $menu) {
            return [];
        }

        return $this->trees->serializeForEditor($menu);
    }
}
