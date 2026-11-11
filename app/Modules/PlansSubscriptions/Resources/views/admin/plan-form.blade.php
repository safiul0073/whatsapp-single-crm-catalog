@php
    $editing = $plan->exists;
    $limits = old('limits', $plan->limits ?? []);
    $featuresText = old('features_text', implode("\n", $plan->features ?? []));
    $hasOldAccess = (bool) old('features_access.submitted');
    $oldModules = (array) old('features_access.modules', []);
    $oldItems = (array) old('features_access.items', []);
    $moduleIncluded = fn (string $module): bool => $hasOldAccess ? in_array($module, $oldModules, true) : $plan->includesFeature($module);
    $itemIncluded = fn (string $module, string $item): bool => $hasOldAccess ? in_array($item, $oldItems, true) : $plan->includesFeature($module, $item) || ! $plan->includesFeature($module);
@endphp

<x-layouts.admin :title="$editing ? __('Edit Plan') : __('Add Plan')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ $editing ? __('Edit Plan') : __('Add Plan') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Manage the plan customers can subscribe to from billing and checkout flows.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.plans.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="section-card space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-forms.input :label="__('Plan Name')" name="name" required :value="$plan->name" :placeholder="__('e.g. Growth Monthly')" />
                    <x-forms.input :label="__('Slug')" name="slug" :value="$plan->slug" :placeholder="__('Auto-generated from name')" />
                    <x-forms.input :label="__('Price')" name="price" type="number" step="0.01" min="0" required :value="$plan->price ?? 0" :placeholder="__('29')" />
                    <x-forms.select
                        :label="__('Billing Interval')"
                        name="interval"
                        required
                        :selected="$plan->interval ?? 'month'"
                        :options="['month' => __('Monthly'), 'year' => __('Yearly'), 'lifetime' => __('Lifetime')]"
                        :placeholder="''"
                    />
                </div>

                <x-forms.textarea :label="__('Description')" name="description" :value="$plan->description" :placeholder="__('Short plan summary shown to admins and users.')" />

                <div class="border-t border-neutral-100 pt-5">
                    <h2 class="mb-4 font-semibold text-neutral-950">{{ __('Quota Limits') }}</h2>
                    <p class="form-hint mb-3">{{ __('Leave a limit empty for unlimited. Enter 0 to block the feature.') }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-forms.input :label="__('Messages / Month')" name="messages_per_month" type="number" min="0" :value="$limits['messages_per_month'] ?? null" />
                        <x-forms.input :label="__('Contacts')" name="contacts" type="number" min="0" :value="$limits['contacts'] ?? null" />
                        <x-forms.input :label="__('WhatsApp Numbers')" name="whatsapp_numbers" type="number" min="0" :value="$limits['whatsapp_numbers'] ?? null" />
                        <x-forms.input :label="__('AI Tokens')" name="ai_tokens" type="number" min="0" :value="$limits['ai_tokens'] ?? null" />
                        <x-forms.input :label="__('Campaigns / Month')" name="campaigns_per_month" type="number" min="0" :value="$limits['campaigns_per_month'] ?? null" />
                        <x-forms.input :label="__('Chatbots')" name="chatbots" type="number" min="0" :value="$limits['chatbots'] ?? null" />
                        <x-forms.input :label="__('Team Members')" name="team_members" type="number" min="0" :value="$limits['team_members'] ?? null" />
                        <x-forms.input :label="__('Lead Generations / Month')" name="max_lead_generations_per_month" type="number" min="0" :value="$limits['max_lead_generations_per_month'] ?? null" />
                        <x-forms.input :label="__('Generated Leads / Month')" name="max_ai_lead_results_per_month" type="number" min="0" :value="$limits['max_ai_lead_results_per_month'] ?? null" />
                        <x-forms.input :label="__('Platform AI Credits')" name="max_ai_credits" type="number" min="0" :value="$limits['max_ai_credits'] ?? null" />
                    </div>
                    <div class="mt-4 rounded-md border border-neutral-100 bg-section p-4">
                        <x-forms.toggle :label="__('Premium: AI automation builder')" name="automation_ai_builder" :checked="(bool) ($limits['automation_ai_builder'] ?? false)" />
                        <p class="mt-2 text-xs text-neutral-500">{{ __('Allows users on this plan to generate automation flows with AI.') }}</p>
                    </div>
                    <div class="mt-4 rounded-md border border-neutral-100 bg-section p-4">
                        <x-forms.toggle :label="__('Premium: AI Campaign Doctor')" name="campaign_ai_doctor" :checked="(bool) ($limits['campaign_ai_doctor'] ?? false)" />
                        <p class="mt-2 text-xs text-neutral-500">{{ __('Allows users on this plan to review campaign risk before sending.') }}</p>
                    </div>
                </div>

                <div class="border-t border-neutral-100 pt-5">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-neutral-950">{{ __('Feature Access') }}</h2>
                            <p class="form-hint mt-1">{{ __('Choose the modules and menu items workspaces on this plan can use. Anything switched off is hidden from their menu and blocked by URL. Dashboard, profile and billing are always included.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-outline btn-sm" data-feature-access-toggle="all">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-outline btn-sm" data-feature-access-toggle="none">{{ __('Clear') }}</button>
                        </div>
                    </div>
                    <input type="hidden" name="features_access[submitted]" value="1">
                    @error('features_access.modules.*')
                        <p class="form-error mb-3">{{ $message }}</p>
                    @enderror
                    @error('features_access.items.*')
                        <p class="form-error mb-3">{{ $message }}</p>
                    @enderror

                    <div class="space-y-3" data-feature-access>
                        @foreach ($featureCatalog as $moduleKey => $module)
                            @php
                                $moduleOn = $moduleIncluded($moduleKey);
                                $groups = collect($module['items'])->groupBy('group', true);
                            @endphp
                            <div class="rounded-md border border-neutral-100 bg-section" x-data="{ enabled: {{ $moduleOn ? 'true' : 'false' }}, open: false }">
                                <div class="flex items-center justify-between gap-3 p-4">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="features_access[modules][]" value="{{ $moduleKey }}" class="checkbox-field" x-model="enabled" @checked($moduleOn)>
                                        <span class="checkbox-text flex items-center gap-2 font-semibold text-neutral-950">
                                            <i class="ph {{ $module['icon'] }}"></i>
                                            {{ $module['label'] }}
                                        </span>
                                    </label>
                                    <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()">
                                        {{ __(':count items', ['count' => count($module['items'])]) }}
                                        <i class="ph ph-caret-down transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
                                    </button>
                                </div>
                                <div class="border-t border-neutral-100 p-4" x-show="open" x-cloak x-bind:class="enabled ? '' : 'opacity-50'">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        @foreach ($groups as $groupLabel => $items)
                                            <div>
                                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ $groupLabel }}</p>
                                                <div class="space-y-2">
                                                    @foreach ($items as $itemKey => $item)
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" name="features_access[items][]" value="{{ $itemKey }}" class="checkbox-field" data-feature-access-item @checked($itemIncluded($moduleKey, $itemKey)) x-bind:disabled="! enabled">
                                                            <span class="checkbox-text">{{ $item['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-neutral-500" x-show="! enabled">{{ __('Switch the module on to choose its menu items.') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-forms.textarea
                    :label="__('Features')"
                    name="features_text"
                    :value="$featuresText"
                    :rows="7"
                    :placeholder="__('One feature per line')"
                    :hint="__('Shown as the plan feature list. Enter one feature per line.')"
                />
            </div>

            <div class="section-card h-fit space-y-5">
                <x-forms.input :label="__('Sort Order')" name="sort_order" type="number" min="0" required :value="$plan->sort_order ?? 0" />
                <x-forms.toggle :label="__('Plan is active')" name="is_active" :checked="$plan->exists ? $plan->is_active : true" />

                <div class="flex items-center gap-3 border-t border-neutral-100 pt-5">
                    <x-forms.submit :label="$editing ? __('Update Plan') : __('Create Plan')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.plans.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                'use strict';

                document.querySelectorAll('[data-feature-access-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var checked = button.dataset.featureAccessToggle === 'all';

                        document.querySelectorAll('[data-feature-access] input[type="checkbox"]').forEach(function (input) {
                            input.checked = checked;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                });
            });
        </script>
    @endpush
</x-layouts.admin>
