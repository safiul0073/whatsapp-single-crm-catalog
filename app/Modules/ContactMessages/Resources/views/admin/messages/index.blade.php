<x-layouts.admin :title="__('Contact Messages')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Contact Messages') }}</h1>
        </div>

        <div class="section-card">
            <form method="GET" action="{{ route('admin.contact-messages.index') }}"
                class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Name, email, company or message...') }}"
                        class="input-field w-full" />
                </div>
                <div class="min-w-[160px]">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="select-field w-full">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach (['new' => __('New'), 'read' => __('Read'), 'archived' => __('Archived')] as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" variant="primary">
                        <i class="ph ph-magnifying-glass"></i> {{ __('Filter') }}
                    </x-ui.button>
                    @if (request()->hasAny(['search', 'status']))
                        <x-ui.button variant="ghost" href="{{ route('admin.contact-messages.index') }}">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </div>
            </form>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$messages" />
        </div>
    </div>
</x-layouts.admin>
