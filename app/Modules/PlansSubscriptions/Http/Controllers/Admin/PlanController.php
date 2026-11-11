<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PlansSubscriptions\Http\Requests\PlanRequest;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(protected WorkspaceNavigationRegistry $navigation) {}

    public function index(): View
    {
        return view('plans-subscriptions::admin.plans', [
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'featureCatalog' => $this->toggleableCatalog(),
        ]);
    }

    public function create(): View
    {
        return view('plans-subscriptions::admin.plan-form', ['plan' => new Plan, 'featureCatalog' => $this->toggleableCatalog()]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::create($this->payload($request, null));

        return redirect()->route('admin.plans.index')->with('success', __('Plan created successfully.'));
    }

    public function edit(Plan $plan): View
    {
        return view('plans-subscriptions::admin.plan-form', ['plan' => $plan, 'featureCatalog' => $this->toggleableCatalog()]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->payload($request, $plan));

        return redirect()->route('admin.plans.index')->with('success', __('Plan updated successfully.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return redirect()->route('admin.plans.index')->with('success', __('This plan has subscribers, so it was deactivated instead of deleted.'));
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', __('Plan deleted successfully.'));
    }

    /**
     * Modules and menu items admins can switch on or off per plan.
     *
     * @return array<string, array{label: string, icon: string, locked: bool, items: array<string, array{label: string, group: string, icon: string, route: string}>}>
     */
    private function toggleableCatalog(): array
    {
        return array_filter($this->navigation->featureCatalog(), fn (array $module): bool => ! $module['locked']);
    }

    /**
     * Turn the checked modules and items into the plan's deny-list. A switched-off module is stored
     * as the module key alone, so its items follow it.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function blockedFeatures(array $validated, ?Plan $plan): array
    {
        if (! data_get($validated, 'features_access.submitted')) {
            return $plan?->blockedFeatures() ?? [];
        }

        $modules = (array) data_get($validated, 'features_access.modules', []);
        $items = (array) data_get($validated, 'features_access.items', []);
        $blocked = [];

        foreach ($this->toggleableCatalog() as $moduleKey => $module) {
            if (! in_array($moduleKey, $modules, true)) {
                $blocked[] = $moduleKey;

                continue;
            }

            foreach (array_keys($module['items']) as $itemKey) {
                if (! in_array($itemKey, $items, true)) {
                    $blocked[] = $itemKey;
                }
            }
        }

        return array_values(array_unique($blocked));
    }

    private function payload(PlanRequest $request, ?Plan $plan): array
    {
        $validated = $request->validated();
        $limits = [];

        foreach ([
            'messages_per_month',
            'contacts',
            'whatsapp_numbers',
            'ai_tokens',
            'campaigns_per_month',
            'chatbots',
            'team_members',
            'max_lead_generations_per_month',
            'max_ai_lead_results_per_month',
            'max_ai_credits',
        ] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $limits[$key] = (int) $validated[$key];
            }
        }

        $limits['automation_ai_builder'] = (bool) ($validated['automation_ai_builder'] ?? false);
        $limits['campaign_ai_doctor'] = (bool) ($validated['campaign_ai_doctor'] ?? false);
        $limits[Plan::BLOCKED_FEATURES] = $this->blockedFeatures($validated, $plan);

        $features = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['features_text'] ?? '')))
            ->map(fn (string $feature): string => trim($feature))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_free' => (float) $validated['price'] <= 0,
            'interval' => $validated['interval'],
            'limits' => $limits,
            'features' => $features,
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ];
    }
}
