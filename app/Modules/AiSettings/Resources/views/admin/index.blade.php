<x-layouts.admin :title="__('AI Settings')">

    <form method="POST" action="{{ route('admin.ai-settings.update') }}" id="settingsForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="_active_tab" id="activeTab" value="{{ $activeGroup ?? array_key_first($groups) }}">

        <div class="settings-layout">

            {{-- ── Sidebar Navigation ── --}}
            <nav class="settings-nav">
                <div class="settings-nav-header">
                    <h2>{{ __('AI Providers') }}</h2>
                    <p>{{ __('Configure AI provider credentials') }}</p>
                </div>

                {{-- Search --}}
                <div class="settings-nav-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="{{ __('Search settings...') }}" id="settingsNavSearch"
                        autocomplete="off">
                </div>

                {{-- Nav Items --}}
                <div class="settings-nav-group" id="settingsNavList">
                    @foreach ($groups as $groupKey => $group)
                        <a href="#" class="settings-nav-item{{ ($activeGroup ?? array_key_first($groups)) === $groupKey ? ' active' : '' }}"
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
                        <h1>{{ __('AI Settings') }}</h1>
                        <p>{{ __('Configure credentials and defaults for each AI provider.') }}</p>
                    </div>

                    {{-- Sections (tab panels — only first visible) --}}
                    @foreach ($groups as $groupKey => $group)
                        @php
                            $allSettings = collect($group['settings'] ?? []);
                            $mainSettings = $allSettings->filter(fn ($s) => ($s['layout'] ?? 'main') !== 'sidebar');
                            $sidebarSettings = $allSettings->filter(fn ($s) => ($s['layout'] ?? 'main') === 'sidebar');
                            $hasSidebar = $sidebarSettings->isNotEmpty();
                            $isFullWidth = ($group['layout'] ?? '') === 'full';
                            $hasCardGroups = !empty($group['card_groups']);
                        @endphp

                        <div class="{{ $isFullWidth ? 'settings-section-full' : 'settings-section' }}" id="settings-{{ $groupKey }}"
                            data-settings-group="{{ $groupKey }}"@unless (($activeGroup ?? array_key_first($groups)) === $groupKey) style="display:none"@endunless>

                            @if ($hasCardGroups)
                                {{-- ── Card Groups Layout (General tab) ── --}}
                                @php
                                    // Group settings by their card_group label
                                    $cardGroups = [];
                                    foreach ($mainSettings as $key => $setting) {
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
                                    @foreach ($cardColumns as $column)
                                        <div class="space-y-6">
                                            @foreach ($column as $card)
                                                <div class="section-card">
                                                    <div class="flex items-center gap-3 mb-1">
                                                        <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10 text-primary">
                                                            <i class="{{ $card['icon'] }} text-lg"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="text-sm font-semibold text-neutral-900 dark:text-neutral-800">{{ __($card['label']) }}</h5>
                                                            @if ($card['description'])
                                                                <p class="text-xs text-neutral-500">{{ __($card['description']) }}</p>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="space-y-4 mt-4">
                                                        @foreach ($card['fields'] as $key => $setting)
                                                            <div>
                                                                @if ($setting['type'] === 'select')
                                                                    <x-forms.select :label="__($setting['label'])"
                                                                        name="settings[{{ $key }}]"
                                                                        :value="$setting['value']">
                                                                        @foreach ($setting['options'] ?? [] as $optValue => $optLabel)
                                                                            <option value="{{ $optValue }}"
                                                                                @selected($setting['value'] == $optValue)>
                                                                                {{ __($optLabel) }}</option>
                                                                        @endforeach
                                                                    </x-forms.select>
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
                                {{-- ── Standard Settings Layout (provider tabs) ── --}}
                                <div class="grid grid-cols-1 gap-6 {{ $hasSidebar ? 'xl:grid-cols-3' : '' }}">

                                    {{-- ── Left Column (credentials + main settings) ── --}}
                                    <div class="{{ $hasSidebar ? 'xl:col-span-2' : '' }}">
                                        <div class="section-card">
                                            <h4 class="settings-section-title">{{ __($group['label']) }}</h4>
                                            @if ($group['description'])
                                                <p class="settings-section-desc">{{ __($group['description']) }}</p>
                                            @endif

                                            @if ($mainSettings->isNotEmpty())
                                                <div class="settings-section-body">
                                                    @foreach ($mainSettings as $key => $setting)
                                                        <div class="setting-row"
                                                            data-setting-label="{{ strtolower(__($setting['label'])) }}">
                                                            <div class="setting-label">
                                                                <span class="label">{{ __($setting['label']) }}</span>
                                                                @if (!empty($setting['hint']))
                                                                    <span class="hint">{{ __($setting['hint']) }}</span>
                                                                @endif
                                                            </div>

                                                            <div>
                                                                @if (in_array($setting['type'], ['boolean', 'feature']))
                                                                    <x-forms.toggle name="settings[{{ $key }}]"
                                                                        :checked="(bool) $setting['value']" />
                                                                @elseif($setting['type'] === 'select')
                                                                    @php
                                                                        $options = $setting['options'] ?? [];
                                                                        $searchable = count($options) > 10;
                                                                    @endphp
                                                                    @if ($searchable)
                                                                        <x-forms.tom-select name="settings[{{ $key }}]"
                                                                            :selected="$setting['value']">
                                                                            @foreach ($options as $optValue => $optLabel)
                                                                                <option value="{{ $optValue }}"
                                                                                    @selected($setting['value'] == $optValue)>
                                                                                    {{ __($optLabel) }}</option>
                                                                            @endforeach
                                                                        </x-forms.tom-select>
                                                                    @else
                                                                        <x-forms.select name="settings[{{ $key }}]"
                                                                            :value="$setting['value']">
                                                                            @foreach ($options as $optValue => $optLabel)
                                                                                <option value="{{ $optValue }}"
                                                                                    @selected($setting['value'] == $optValue)>
                                                                                    {{ __($optLabel) }}</option>
                                                                            @endforeach
                                                                        </x-forms.select>
                                                                    @endif
                                                                @elseif($setting['type'] === 'media')
                                                                    <x-media.picker
                                                                        :name="'settings[' . $key . ']'"
                                                                        :value="$setting['value']"
                                                                        :accept="$setting['accept'] ?? 'image'" />
                                                                @else
                                                                    <x-forms.input name="settings[{{ $key }}]"
                                                                        :type="$setting['type']"
                                                                        :value="$setting['value']"
                                                                        :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))" />
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ── Right Column (logo) ── --}}
                                    @if ($hasSidebar)
                                        <div class="space-y-6">
                                            @php
                                                $logoSettings = $sidebarSettings->where('type', 'media');
                                            @endphp
                                            @foreach ($logoSettings as $logoKey => $logoSetting)
                                                <div class="section-card">
                                                    <h5 class="text-sm font-semibold text-neutral-900 mb-4">{{ __($logoSetting['label'] ?? 'Logo') }}</h5>
                                                    <x-media.picker
                                                        :name="'settings[' . $logoKey . ']'"
                                                        :value="$logoSetting['value']"
                                                        :accept="$logoSetting['accept'] ?? 'image'" />
                                                </div>
                                            @endforeach

                                            {{-- Non-media sidebar settings --}}
                                            @php
                                                $configSettings = $sidebarSettings->where('type', '!=', 'media');
                                            @endphp
                                            @if ($configSettings->isNotEmpty())
                                                <div class="section-card space-y-4">
                                                    <h5 class="text-sm font-semibold text-neutral-900">{{ __('Configuration') }}</h5>

                                                    @foreach ($configSettings as $key => $setting)
                                                        <div>
                                                            <x-forms.input :label="__($setting['label'])"
                                                                name="settings[{{ $key }}]"
                                                                :type="$setting['type']"
                                                                :value="$setting['value']"
                                                                :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
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
                    <button type="submit" formaction="{{ route('admin.ai-settings.vector-database.test') }}" formmethod="POST" onclick="document.getElementById('activeTab').value = 'vector-database'" class="btn btn-outline">
                        <i class="ph ph-plugs-connected"></i> {{ __('Test Qdrant') }}
                    </button>
                    <x-ui.button type="button" variant="ghost" onclick="window.location.reload()">
                        <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Reset') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>

    {{-- ── Mobile Bottom Sheet ── --}}
    <div class="settings-sheet-overlay" id="settingsSheetOverlay"></div>

    <button type="button" class="settings-mobile-nav-trigger" id="settingsMobileNavBtn"
        title="{{ __('Navigate settings') }}">
        <i class="ph ph-brain"></i>
    </button>

    <div class="settings-sheet" id="settingsSheet">
        <div class="settings-sheet-handle"><span></span></div>
        <div class="settings-sheet-header">
            <h3>{{ __('AI Providers') }}</h3>
            <p>{{ __('Configure AI provider credentials') }}</p>
        </div>
        <div class="settings-sheet-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" placeholder="{{ __('Search settings...') }}" id="settingsSheetSearch"
                autocomplete="off">
        </div>
        <div class="settings-sheet-list" id="settingsSheetList">
            @foreach ($groups as $groupKey => $group)
                <a href="#" class="settings-sheet-item{{ ($activeGroup ?? array_key_first($groups)) === $groupKey ? ' active' : '' }}"
                    data-sheet-nav="{{ $groupKey }}" data-search-label="{{ strtolower(__($group['label'])) }}">
                    <i class="{{ $group['icon'] }}"></i>
                    {{ __($group['label']) }}
                </a>
            @endforeach
        </div>
    </div>

</x-layouts.admin>
