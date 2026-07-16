<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PlansSubscriptions\Http\Requests\PlanRequest;
use App\Modules\PlansSubscriptions\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('plans-subscriptions::admin.plans', ['plans' => Plan::query()->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('plans-subscriptions::admin.plan-form', ['plan' => new Plan]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::create($this->payload($request));

        return redirect()->route('admin.plans.index')->with('success', __('Plan created successfully.'));
    }

    public function edit(Plan $plan): View
    {
        return view('plans-subscriptions::admin.plan-form', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->payload($request));

        return redirect()->route('admin.plans.index')->with('success', __('Plan updated successfully.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', __('Plan deleted successfully.'));
    }

    private function payload(PlanRequest $request): array
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
            'interval' => $validated['interval'],
            'limits' => $limits,
            'features' => $features,
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ];
    }
}
