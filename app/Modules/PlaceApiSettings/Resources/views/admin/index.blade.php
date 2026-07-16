<x-layouts.admin :title="__('Place API Settings')">
    <form method="POST" action="{{ route('admin.place-api-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="heading-2">{{ __('Place API Settings') }}</h1>
                <p class="m-text mt-1">{{ __('Configure Google Places as the trusted source for lead generation.') }}</p>
            </div>
            <span class="badge {{ $status['configured'] ? 'badge-success' : 'badge-warning' }}">
                {{ $status['configured'] ? __('Configured') : __('Not configured') }}
            </span>
        </div>

        @foreach ($groups as $group)
            <div class="app-card p-5">
                <div class="mb-5 flex items-start gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="{{ $group['icon'] }} text-lg"></i>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-title">{{ __($group['label']) }}</h2>
                        <p class="mt-1 text-sm text-body">{{ __($group['description']) }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($group['settings'] as $key => $setting)
                        <div class="grid gap-3 border-t border-neutral-100 pt-4 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-center">
                            <div>
                                <p class="text-sm font-semibold text-title">{{ __($setting['label']) }}</p>
                                @if (! empty($setting['hint']))
                                    <p class="mt-1 text-xs text-body">{{ __($setting['hint']) }}</p>
                                @endif
                                @if (($setting['encrypted'] ?? false) && ($setting['has_value'] ?? false))
                                    <p class="mt-1 text-xs text-success">{{ __('A key is currently saved.') }}</p>
                                @endif
                            </div>
                            <div>
                                @if (in_array($setting['type'], ['boolean', 'feature'], true))
                                    <x-forms.toggle name="settings[{{ $key }}]" :checked="(bool) $setting['value']" />
                                @elseif ($setting['type'] === 'integer')
                                    <x-forms.input
                                        name="settings[{{ $key }}]"
                                        type="number"
                                        :value="$setting['value']"
                                        :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))" />
                                @else
                                    <x-forms.input
                                        name="settings[{{ $key }}]"
                                        :type="$setting['type']"
                                        :value="$setting['value']"
                                        :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))" />
                                @endif
                                @error('settings.'.$key)
                                    <p class="mt-2 text-xs text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex justify-end gap-2">
            <x-ui.button type="button" variant="ghost" onclick="window.location.reload()">
                <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Reset') }}
            </x-ui.button>
            <x-forms.submit :label="__('Save Changes')" />
        </div>
    </form>
</x-layouts.admin>
