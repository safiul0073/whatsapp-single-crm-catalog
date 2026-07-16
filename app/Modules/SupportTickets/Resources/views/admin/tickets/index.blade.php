<x-layouts.admin :title="__('Support Tickets')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Support Tickets') }}</h1>
        </div>

        {{-- Filters --}}
        <div class="section-card">
            <form method="GET" action="{{ route('admin.support-tickets.index') }}"
                class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Subject, user name or email…') }}"
                        class="input-field w-full" />
                </div>
                <div class="min-w-[140px]">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="select-field w-full">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach (['open' => __('Open'), 'in_progress' => __('In Progress'), 'resolved' => __('Resolved'), 'closed' => __('Closed')] as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[140px]">
                    <label class="form-label">{{ __('Priority') }}</label>
                    <select name="priority" class="select-field w-full">
                        <option value="">{{ __('All Priorities') }}</option>
                        @foreach (['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'urgent' => __('Urgent')] as $value => $label)
                            <option value="{{ $value }}" {{ request('priority') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" variant="primary">
                        <i class="ph ph-magnifying-glass"></i> {{ __('Filter') }}
                    </x-ui.button>
                    @if (request()->hasAny(['search', 'status', 'priority']))
                        <x-ui.button variant="ghost" href="{{ route('admin.support-tickets.index') }}">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </div>
            </form>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$tickets" />
        </div>
    </div>
</x-layouts.admin>
