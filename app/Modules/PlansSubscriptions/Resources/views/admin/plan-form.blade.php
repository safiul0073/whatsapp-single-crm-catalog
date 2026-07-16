@php
    $editing = $plan->exists;
    $limits = old('limits', $plan->limits ?? []);
    $featuresText = old('features_text', implode("\n", $plan->features ?? []));
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
                    <div class="mt-4 rounded-lg border border-neutral-100 bg-section p-4">
                        <x-forms.toggle :label="__('Premium: AI automation builder')" name="automation_ai_builder" :checked="(bool) ($limits['automation_ai_builder'] ?? false)" />
                        <p class="mt-2 text-xs text-neutral-500">{{ __('Allows users on this plan to generate automation flows with AI.') }}</p>
                    </div>
                    <div class="mt-4 rounded-lg border border-neutral-100 bg-section p-4">
                        <x-forms.toggle :label="__('Premium: AI Campaign Doctor')" name="campaign_ai_doctor" :checked="(bool) ($limits['campaign_ai_doctor'] ?? false)" />
                        <p class="mt-2 text-xs text-neutral-500">{{ __('Allows users on this plan to review campaign risk before sending.') }}</p>
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
</x-layouts.admin>
