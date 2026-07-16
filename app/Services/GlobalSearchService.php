<?php

namespace App\Services;

use App\Models\Admin;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;

class GlobalSearchService
{
    /**
     * Searchable modules per guard.
     *
     * Each entry: model, label, icon, columns (LIKE search), route (named), subtitle (optional column).
     *
     * @var array<string, list<array{model: class-string, label: string, icon: string, columns: list<string>, route: string, subtitle?: string}>>
     */
    protected array $searchableModules = [
        'admin' => [
            [
                'model' => Admin::class,
                'label' => 'Users',
                'icon' => 'ph-users',
                'columns' => ['name', 'email'],
                'route' => 'admin.users.show',
                'subtitle' => 'email',
            ],
            [
                'model' => NotificationTemplate::class,
                'label' => 'Notification Templates',
                'icon' => 'ph-bell',
                'columns' => ['name', 'slug'],
                'route' => 'admin.notification-templates.edit',
            ],
            [
                'model' => Payment::class,
                'label' => 'Payments',
                'icon' => 'ph-credit-card',
                'columns' => ['uuid', 'gateway_payment_id', 'description'],
                'route' => 'admin.payments.show',
            ],
        ],
    ];

    /**
     * Search across multiple models for the given guard.
     *
     * @return list<array{module: string, icon: string, results: list<array{id: int, title: string, subtitle: string|null, url: string}>}>
     */
    public function search(string $query, string $guard, int $limit = 5): array
    {
        $modules = $this->searchableModules[$guard] ?? [];
        $grouped = [];

        // 1. Search pages first
        $pageResults = $this->searchPages($query, $guard, $limit);
        if ($pageResults !== []) {
            $grouped[] = [
                'module' => __('Pages'),
                'icon' => 'ph-compass',
                'results' => $pageResults,
            ];
        }

        // 2. Search database records
        foreach ($modules as $module) {
            if (! Route::has($module['route'])) {
                continue;
            }

            $results = $this->searchModel($query, $module, $limit);

            if ($results !== []) {
                $grouped[] = [
                    'module' => $module['label'],
                    'icon' => $module['icon'],
                    'results' => $results,
                ];
            }
        }

        return $grouped;
    }

    protected function searchPages(string $query, string $guard, int $limit): array
    {
        if ($guard !== 'admin') {
            return [];
        }

        $user = auth($guard)->user();
        if (! $user) {
            return [];
        }

        $registry = app(ModuleRegistry::class);
        $navigation = $registry->buildNavigation($guard);

        $pages = [];
        foreach ($navigation as $item) {
            // Check parent permission
            if (! empty($item['permission']) && ! $user->can($item['permission'])) {
                continue;
            }

            if (! empty($item['children'])) {
                foreach ($item['children'] as $child) {
                    // Check child permission
                    if (! empty($child['permission']) && ! $user->can($child['permission'])) {
                        continue;
                    }

                    $pages[] = [
                        'title' => $child['label'],
                        'subtitle' => $item['label'],
                        'route' => $child['route'],
                    ];
                }
            } else {
                $pages[] = [
                    'title' => $item['label'],
                    'subtitle' => $item['group'] ?? 'Main Menu',
                    'route' => $item['route'],
                ];
            }
        }

        // Search the pages array
        $queryLower = mb_strtolower($query);
        $results = [];

        foreach ($pages as $page) {
            $titleLower = mb_strtolower($page['title']);
            $subtitleLower = mb_strtolower($page['subtitle']);

            if (str_contains($titleLower, $queryLower) || str_contains($subtitleLower, $queryLower)) {
                $routeBase = explode('.*', $page['route'])[0];
                $routeName = str_contains($page['route'], '.*') ? $routeBase.'.index' : $routeBase;

                if (Route::has($routeName)) {
                    $results[] = [
                        'id' => $page['route'],
                        'title' => $page['title'],
                        'subtitle' => $page['subtitle'],
                        'url' => route($routeName),
                    ];

                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Search a single model's columns.
     *
     * @return list<array{id: int, title: string, subtitle: string|null, url: string}>
     */
    protected function searchModel(string $query, array $module, int $limit): array
    {
        $modelClass = $module['model'];
        $builder = $modelClass::query();

        $builder->where(function ($q) use ($query, $module) {
            foreach ($module['columns'] as $column) {
                $q->orWhere($column, 'like', "%{$query}%");
            }
        });

        $records = $builder->limit($limit)->get();

        return $records->map(function ($record) use ($module) {
            $titleColumn = $module['columns'][0];
            $subtitleColumn = $module['subtitle'] ?? null;

            return [
                'id' => $record->getKey(),
                'title' => $record->{$titleColumn},
                'subtitle' => $subtitleColumn ? $record->{$subtitleColumn} : null,
                'url' => route($module['route'], $record->getKey()),
            ];
        })->all();
    }
}
