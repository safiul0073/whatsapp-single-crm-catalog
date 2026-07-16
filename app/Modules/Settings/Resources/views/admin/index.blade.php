<x-layouts.admin :title="__('Settings')">

    <form method="POST" action="{{ route('admin.settings.update') }}" id="settingsForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="_active_tab" id="activeTab" value="{{ array_key_first($groups) }}">

        <div class="settings-layout">

            {{-- ── Sidebar Navigation ── --}}
            <nav class="settings-nav">
                <div class="settings-nav-header">
                    <h2>{{ __('Settings') }}</h2>
                    <p>{{ __('Manage your workspace') }}</p>
                </div>

                {{-- Search --}}
                <div class="settings-nav-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="{{ __('Search settings...') }}" id="settingsNavSearch" autocomplete="off">
                </div>

                {{-- Nav Items --}}
                <div class="settings-nav-group" id="settingsNavList">
                    @foreach($groups as $groupKey => $group)
                        <a href="#"
                           class="settings-nav-item{{ $loop->first ? ' active' : '' }}"
                           data-settings-nav="{{ $groupKey }}"
                           data-search-label="{{ strtolower(__($group['label'])) }}">
                            <i class="{{ $group['icon'] }}"></i>
                            {{ __($group['label']) }}
                        </a>
                    @endforeach
                </div>

                {{-- Footer --}}
                <div class="settings-nav-footer">
                    <div class="flex items-center gap-1.5 text-[11px] text-neutral-400">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-success"></span>
                        {{ config('app.env', 'production') }} v{{ ltrim((string) config('app.version', '1.0.0'), 'vV') }}
                    </div>
                </div>
            </nav>

            {{-- ── Main Content ── --}}
            <div class="settings-content">

                {{-- Scrollable Area --}}
                <div class="settings-content-scroll" id="settingsScroll">

                    {{-- Page Header --}}
                    <div class="settings-page-header">
                        <h1>{{ __('Settings') }}</h1>
                        <p>{{ __('Configure core system settings, feature flags, and email configuration for your workspace.') }}</p>
                    </div>

                    {{-- Sections (tab panels — only first visible) --}}
                    @foreach($groups as $groupKey => $group)
                        @php
                            $allSettings = collect($group['settings'] ?? []);
                            $hasCardGroups = !empty($group['card_groups']);
                            $sectionClass = ($group['layout'] ?? '') === 'full' ? 'settings-section-full' : 'settings-section';
                        @endphp
                        <div class="{{ $sectionClass }}" id="settings-{{ $groupKey }}" data-settings-group="{{ $groupKey }}"@unless($loop->first) style="display:none"@endunless>
                            @if($hasCardGroups)
                                @php
                                    $cardGroups = [];
                                    foreach ($allSettings as $key => $setting) {
                                        $cgLabel = $setting['card_group']['label'] ?? 'Other';
                                        if (!isset($cardGroups[$cgLabel])) {
                                            $cardGroups[$cgLabel] = [
                                                'label' => $cgLabel,
                                                'icon' => $setting['card_group']['icon'] ?? 'ph ph-gear',
                                                'description' => $setting['card_group']['description'] ?? '',
                                                'fields' => [],
                                            ];
                                        }
                                        $cardGroups[$cgLabel]['fields'][$key] = $setting;
                                    }

                                    $cardGroups = array_values($cardGroups);
                                    $leftColumnCount = max(1, (int) floor(count($cardGroups) / 2));
                                    $cardColumns = [
                                        array_slice($cardGroups, 0, $leftColumnCount),
                                        array_slice($cardGroups, $leftColumnCount),
                                    ];
                                @endphp

                                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                    @foreach($cardColumns as $column)
                                        <div class="space-y-6">
                                            @foreach($column as $card)
                                                <div class="section-card">
                                                    <div class="flex items-center gap-3 mb-1">
                                                        <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10 text-primary">
                                                            <i class="{{ $card['icon'] }} text-lg"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="text-sm font-semibold text-neutral-900 dark:text-neutral-800">{{ __($card['label']) }}</h5>
                                                            @if($card['description'])
                                                                <p class="text-xs text-neutral-500">{{ __($card['description']) }}</p>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="space-y-4 mt-4">
                                                        @foreach($card['fields'] as $key => $setting)
                                                            @php
                                                                $visibleIf = $setting['visible_if'] ?? null;
                                                                $visibleIfJson = $visibleIf ? json_encode($visibleIf) : null;
                                                            @endphp
                                                            <div data-setting-key="{{ $key }}" @if($visibleIfJson) data-visible-if="{{ $visibleIfJson }}" @endif>
                                                                @if(in_array($setting['type'], ['boolean', 'feature']))
                                                                    <div class="flex items-center justify-between gap-4 rounded-xl border border-neutral-100 px-4 py-3">
                                                                        <div>
                                                                            <p class="text-sm font-semibold text-neutral-900">{{ __($setting['label']) }}</p>
                                                                            @if(!empty($setting['hint']))
                                                                                <p class="mt-1 text-xs text-neutral-500">{{ __($setting['hint']) }}</p>
                                                                            @endif
                                                                        </div>
                                                                        <x-forms.toggle
                                                                            name="settings[{{ $key }}]"
                                                                            :checked="(bool) $setting['value']"
                                                                        />
                                                                    </div>
                                                                @elseif($setting['type'] === 'select')
                                                                    <x-forms.select :label="__($setting['label'])"
                                                                        name="settings[{{ $key }}]"
                                                                        :value="$setting['value']">
                                                                        @foreach($setting['options'] ?? [] as $optValue => $optLabel)
                                                                            <option value="{{ $optValue }}" @selected($setting['value'] == $optValue)>{{ __($optLabel) }}</option>
                                                                        @endforeach
                                                                    </x-forms.select>
                                                                @elseif($setting['type'] === 'tile_select')
                                                                    <div class="setting-tile-field">
                                                                        <label class="form-label">{{ __($setting['label']) }}</label>
                                                                        @if(!empty($setting['hint']))
                                                                            <p class="form-hint">{{ __($setting['hint']) }}</p>
                                                                        @endif
                                                                        <div class="setting-tile-select" data-tile-select>
                                                                            <input type="hidden" name="settings[{{ $key }}]" value="{{ $setting['value'] }}" data-tile-select-input>
                                                                            @foreach($setting['tile_options'] ?? [] as $optionValue => $tile)
                                                                                @php $selected = (string) $setting['value'] === (string) $optionValue; @endphp
                                                                                <button type="button"
                                                                                        class="setting-option-tile{{ $selected ? ' active' : '' }}"
                                                                                        data-tile-select-option
                                                                                        data-value="{{ $optionValue }}">
                                                                                    <span class="setting-option-icon">
                                                                                        <i class="{{ $tile['icon'] ?? 'ph ph-circle' }}"></i>
                                                                                    </span>
                                                                                    <span class="setting-option-copy">
                                                                                        <span class="setting-option-title">{{ __($tile['label'] ?? ($setting['options'][$optionValue] ?? $optionValue)) }}</span>
                                                                                        @if(!empty($tile['description']))
                                                                                            <span class="setting-option-desc">{{ __($tile['description']) }}</span>
                                                                                        @endif
                                                                                    </span>
                                                                                </button>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @elseif($setting['type'] === 'readonly_url')
                                                                    <div class="setting-copy-field">
                                                                        <label class="form-label">{{ __($setting['label']) }}</label>
                                                                        @if(!empty($setting['hint']))
                                                                            <p class="form-hint">{{ __($setting['hint']) }}</p>
                                                                        @endif
                                                                        <div class="setting-copy-control">
                                                                            <input type="text" value="{{ $setting['value'] }}" class="setting-copy-input" readonly data-copy-source>
                                                                            <button type="button" class="setting-copy-button" data-copy-button>
                                                                                <i class="ph ph-copy"></i>
                                                                                <span>{{ __('Copy') }}</span>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                @elseif($setting['type'] === 'textarea')
                                                                    <x-forms.textarea :label="__($setting['label'])"
                                                                        name="settings[{{ $key }}]"
                                                                        :value="$setting['value']"
                                                                        :placeholder="$setting['hint'] ?? __('Enter') . ' ' . strtolower(__($setting['label']))"
                                                                        rows="4" />
                                                                @else
                                                                    <x-forms.input :label="__($setting['label'])"
                                                                        name="settings[{{ $key }}]"
                                                                        :type="$setting['type']"
                                                                        :value="$setting['value']"
                                                                        :placeholder="$setting['hint'] ?? __('Enter') . ' ' . strtolower(__($setting['label']))" />
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @else
                            <div class="section-card">
                                <h4 class="settings-section-title">{{ __($group['label']) }}</h4>
                                @if($group['description'])
                                    <p class="settings-section-desc">{{ __($group['description']) }}</p>
                                @endif

                                @php
                                    $featureSettings = $allSettings->where('type', 'feature');
                                    $regularSettings = $allSettings->where('type', '!=', 'feature');
                                @endphp

                                {{-- Regular Settings --}}
                                @if($regularSettings->isNotEmpty())
                                    <div class="settings-section-body">
                                        @foreach($regularSettings as $key => $setting)
                                        @php
                                            $visibleIf = $setting['visible_if'] ?? null;
                                            $visibleIfJson = $visibleIf ? json_encode($visibleIf) : null;
                                        @endphp
                                        <div class="{{ in_array(($setting['type'] ?? ''), ['editor', 'tile_select']) ? 'setting-row-full' : 'setting-row' }}" data-setting-key="{{ $key }}" data-setting-label="{{ strtolower(__($setting['label'])) }}" @if($visibleIfJson) data-visible-if="{{ $visibleIfJson }}" @endif>
                                            <div class="setting-label">
                                                <span class="label">{{ __($setting['label']) }}</span>
                                                @if(!empty($setting['hint']))
                                                    <span class="hint">{{ __($setting['hint']) }}</span>
                                                @endif
                                            </div>

                                            <div>
                                                @if($setting['type'] === 'boolean')
                                                    <x-forms.toggle
                                                        name="settings[{{ $key }}]"
                                                        :checked="(bool) $setting['value']"
                                                    />
                                                @elseif($setting['type'] === 'textarea')
                                                    <x-forms.textarea
                                                        name="settings[{{ $key }}]"
                                                        :value="$setting['value']"
                                                        :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))"
                                                        rows="3"
                                                    />
                                                @elseif($setting['type'] === 'select')
                                                    @php
                                                        $options = $setting['options'] ?? [];
                                                        $searchable = !empty($setting['options_resolver']) || count($options) > 10;
                                                        if (($setting['options_resolver'] ?? null) === 'timezones') {
                                                            $options = collect(timezone_identifiers_list())
                                                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                                                ->toArray();
                                                        }
                                                    @endphp
                                                    @if($searchable)
                                                        <x-forms.tom-select name="settings[{{ $key }}]" :selected="$setting['value']">
                                                            @foreach($options as $optValue => $optLabel)
                                                                <option value="{{ $optValue }}" @selected($setting['value'] == $optValue)>{{ __($optLabel) }}</option>
                                                            @endforeach
                                                        </x-forms.tom-select>
                                                    @else
                                                        <x-forms.select name="settings[{{ $key }}]" :value="$setting['value']">
                                                            @foreach($options as $optValue => $optLabel)
                                                                <option value="{{ $optValue }}" @selected($setting['value'] == $optValue)>{{ __($optLabel) }}</option>
                                                            @endforeach
                                                        </x-forms.select>
                                                    @endif
                                                @elseif($setting['type'] === 'media')
                                                    <x-media.picker
                                                        :name="'settings[' . $key . ']'"
                                                        :value="$setting['value']"
                                                        :accept="$setting['accept'] ?? 'image'"
                                                    />
                                                @elseif($setting['type'] === 'tile_select')
                                                    <div class="setting-tile-select" data-tile-select>
                                                        <input type="hidden" name="settings[{{ $key }}]" value="{{ $setting['value'] }}" data-tile-select-input>
                                                        @foreach($setting['tile_options'] ?? [] as $optionValue => $tile)
                                                            @php $selected = (string) $setting['value'] === (string) $optionValue; @endphp
                                                            <button type="button"
                                                                    class="setting-option-tile{{ $selected ? ' active' : '' }}"
                                                                    data-tile-select-option
                                                                    data-value="{{ $optionValue }}">
                                                                <span class="setting-option-icon">
                                                                    <i class="{{ $tile['icon'] ?? 'ph ph-circle' }}"></i>
                                                                </span>
                                                                <span class="setting-option-copy">
                                                                    <span class="setting-option-title">{{ __($tile['label'] ?? ($setting['options'][$optionValue] ?? $optionValue)) }}</span>
                                                                    @if(!empty($tile['description']))
                                                                        <span class="setting-option-desc">{{ __($tile['description']) }}</span>
                                                                    @endif
                                                                </span>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @elseif($setting['type'] === 'readonly_url')
                                                    <div class="setting-copy-control">
                                                        <input type="text" value="{{ $setting['value'] }}" class="setting-copy-input" readonly data-copy-source>
                                                        <button type="button" class="setting-copy-button" data-copy-button>
                                                            <i class="ph ph-copy"></i>
                                                            <span>{{ __('Copy') }}</span>
                                                        </button>
                                                    </div>
                                                @elseif($setting['type'] === 'color')
                                                    <div class="setting-color-field">
                                                        <input type="color"
                                                               value="{{ $setting['value'] ?? '#000000' }}"
                                                               class="setting-color-swatch"
                                                               data-color-source>
                                                        <input type="text"
                                                               name="settings[{{ $key }}]"
                                                               value="{{ $setting['value'] ?? '#000000' }}"
                                                               class="setting-color-hex"
                                                               maxlength="7"
                                                               pattern="^#[0-9A-Fa-f]{6}$"
                                                               data-color-input>
                                                    </div>
                                                    @error("settings.{$key}")
                                                        <p class="form-error">{{ $message }}</p>
                                                    @enderror
                                                @elseif($setting['type'] === 'checkbox')
                                                    <x-forms.checkbox-group
                                                        name="settings[{{ $key }}]"
                                                        :options="$setting['options'] ?? []"
                                                        :selected="is_array($setting['value']) ? $setting['value'] : explode(',', $setting['value'] ?? '')"
                                                        :columns="$setting['columns'] ?? 2"
                                                    />
                                                @elseif($setting['type'] === 'tags')
                                                    <x-forms.tom-select name="settings[{{ $key }}][]" :selected="$setting['value']" multiple>
                                                        @foreach($setting['options'] ?? [] as $optValue => $optLabel)
                                                            <option value="{{ $optValue }}" @selected(in_array((string) $optValue, is_array($setting['value']) ? $setting['value'] : explode(',', $setting['value'] ?? '')))>{{ __($optLabel) }}</option>
                                                        @endforeach
                                                    </x-forms.tom-select>
                                                @elseif(in_array($setting['type'], ['date', 'date_range', 'datetime', 'time']))
                                                    @php
                                                        $pickerMode = match($setting['type']) {
                                                            'date_range' => 'range',
                                                            'datetime'   => 'datetime',
                                                            'time'       => 'time',
                                                            default      => 'date',
                                                        };
                                                    @endphp
                                                    <x-forms.datepicker
                                                        name="settings[{{ $key }}]"
                                                        :value="$setting['value']"
                                                        :mode="$pickerMode"
                                                    />
                                                @else
                                                    <x-forms.input
                                                        name="settings[{{ $key }}]"
                                                        :type="$setting['type']"
                                                        :value="$setting['value']"
                                                        :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))"
                                                    />
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif

                                {{-- Feature Switch Tiles --}}
                                @if($featureSettings->isNotEmpty())
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2 mt-6">
                                    @foreach($featureSettings as $key => $setting)
                                        @php
                                            $isOn = (bool) $setting['value'];
                                            $visibleIf = $setting['visible_if'] ?? null;
                                        @endphp
                                        <div class="config-tile group bg-neutral-0 relative flex flex-col overflow-hidden rounded-2xl border border-neutral-100 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-neutral-200 hover:shadow-xl"
                                             data-setting-key="{{ $key }}"
                                             data-state="{{ $isOn ? 'on' : 'off' }}"
                                             @if($visibleIf) data-visible-if="{{ json_encode($visibleIf) }}" @endif>

                                            {{-- Hidden input for form submission --}}
                                            <input type="hidden" name="settings[{{ $key }}]" value="{{ $isOn ? '1' : '0' }}" data-feature-input />

                                            {{-- Left State Strip --}}
                                            <div class="state-strip absolute start-0 top-3.5 bottom-3.5 w-[3.5px] rounded-e transition-all duration-500 {{ $isOn ? 'bg-success opacity-100 shadow-[0_0_8px_var(--color-success)]' : 'bg-error opacity-20' }}"></div>

                                            {{-- Title --}}
                                            <p class="mb-1 text-[15px] font-bold text-neutral-950">{{ __($setting['label']) }}</p>

                                            {{-- Description --}}
                                            @if(!empty($setting['hint']))
                                                <p class="mb-4 text-[13px] leading-relaxed text-neutral-500">{{ __($setting['hint']) }}</p>
                                            @endif

                                            {{-- Footer: Status & Toggle --}}
                                            <div class="mt-auto flex items-center justify-between border-t border-neutral-100 pt-3.5">
                                                <span class="status-text text-xs font-bold tracking-widest uppercase transition-colors duration-300 {{ $isOn ? 'text-success' : 'text-error' }}">{{ $isOn ? __('Enabled') : __('Disabled') }}</span>

                                                <button type="button"
                                                        class="relative flex h-8 w-14 shrink-0 cursor-pointer items-center rounded-full border-[1.5px] p-1 transition-colors duration-300 {{ $isOn ? 'bg-success/10 border-success/30' : 'bg-error/10 border-error/30' }}"
                                                        data-action="toggle-feature">
                                                    <div class="knob absolute flex h-6 w-6 items-center justify-center rounded-full shadow-md transition-all duration-300 {{ $isOn ? 'start-7 bg-success' : 'start-1 bg-error' }}">
                                                        <i class="ph {{ $isOn ? 'ph-check' : 'ph-x' }} text-sm text-white transition-transform duration-300"></i>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif

                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Sticky Footer Bar --}}
                <div class="settings-footer">
                    <x-forms.submit :label="__('Save Changes')" />
                    <x-ui.button type="button" variant="ghost" onclick="window.location.reload()">
                        <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Reset') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>

    {{-- ── Mobile Bottom Sheet ── --}}
    <div class="settings-sheet-overlay" id="settingsSheetOverlay"></div>

    <button type="button" class="settings-mobile-nav-trigger" id="settingsMobileNavBtn" title="{{ __('Navigate settings') }}">
        <i class="ph ph-gear"></i>
    </button>

    <div class="settings-sheet" id="settingsSheet">
        <div class="settings-sheet-handle"><span></span></div>
        <div class="settings-sheet-header">
            <h3>{{ __('Settings') }}</h3>
            <p>{{ __('Manage your workspace') }}</p>
        </div>
        <div class="settings-sheet-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" placeholder="{{ __('Search settings...') }}" id="settingsSheetSearch" autocomplete="off">
        </div>
        <div class="settings-sheet-list" id="settingsSheetList">
            @foreach($groups as $groupKey => $group)
                <a href="#"
                   class="settings-sheet-item{{ $loop->first ? ' active' : '' }}"
                   data-sheet-nav="{{ $groupKey }}"
                   data-search-label="{{ strtolower(__($group['label'])) }}">
                    <i class="{{ $group['icon'] }}"></i>
                    {{ __($group['label']) }}
                </a>
            @endforeach
        </div>
    </div>

</x-layouts.admin>
