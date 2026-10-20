<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected WorkspaceNavigationRegistry $navigationRegistry,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $query = mb_strtolower(trim($data['q']));
        $groups = [];

        foreach ($this->visibleGroups($request) as $group) {
            $results = collect($group['items'])
                ->filter(fn (array $item): bool => str_contains(mb_strtolower($item['search']), $query))
                ->take(8)
                ->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'title' => $item['label'],
                    'subtitle' => $group['label'],
                    'url' => $item['url'],
                ])
                ->values()
                ->all();

            if ($results !== []) {
                $groups[] = [
                    'module' => $group['label'],
                    'icon' => $group['icon'],
                    'results' => $results,
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }

    /**
     * @return list<array{label: string, icon: string, items: list<array{id: string, label: string, url: string, search: string}>}>
     */
    protected function visibleGroups(Request $request): array
    {
        return $this->navigationRegistry->searchableGroups($request->user(), $request);
    }
}
