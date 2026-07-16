<x-layouts.admin :title="__('Payment Gateway Settings')">

    <form method="POST" action="{{ route('admin.payment-gateway-settings.update') }}" id="settingsForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="_active_tab" id="activeTab" value="{{ array_key_first($groups) }}">

        <div class="settings-layout">

            {{-- ── Sidebar Navigation ── --}}
            <nav class="settings-nav">
                <div class="settings-nav-header">
                    <h2>{{ __('Payment Gateways') }}</h2>
                    <p>{{ __('Configure gateway credentials') }}</p>
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
                        <a href="#" class="settings-nav-item{{ $loop->first ? ' active' : '' }}"
                            data-settings-nav="{{ $groupKey }}"
                            data-search-label="{{ strtolower(__($group['label'])) }}">
                            <i class="{{ $group['icon'] }}"></i>
                            {{ __($group['label']) }}
                        </a>
                    @endforeach
                </div>

                {{-- Add Manual Payment Gateway --}}
                <div class="px-4 py-3 border-t border-neutral-100">
                    <button type="button" data-modal-open="addManualGateway"
                       class="btn btn-outline-primary w-full justify-center">
                        <i class="ph ph-plus-circle"></i>
                        {{ __('Add Manual Gateway') }}
                    </button>
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
                        <h1>{{ __('Payment Gateway Settings') }}</h1>
                        <p>{{ __('Configure credentials and settings for each payment gateway.') }}</p>
                    </div>

                    {{-- Sections (tab panels — only first visible) --}}
                    @foreach ($groups as $groupKey => $group)
                        @php
                            $allSettings = collect($group['settings'] ?? []);
                            $mainSettings = $allSettings->filter(fn ($s) => ($s['layout'] ?? 'main') !== 'sidebar');
                            $sidebarSettings = $allSettings->filter(fn ($s) => ($s['layout'] ?? 'main') === 'sidebar');
                            $hasSidebar = $sidebarSettings->isNotEmpty();
                            $isFullWidth = ($group['layout'] ?? '') === 'full';
                        @endphp

                        <div class="{{ $isFullWidth ? 'settings-section-full' : 'settings-section' }}" id="settings-{{ $groupKey }}"
                            data-settings-group="{{ $groupKey }}"@unless ($loop->first) style="display:none"@endunless>

                            <div class="grid grid-cols-1 gap-6 {{ $hasSidebar ? 'xl:grid-cols-3' : '' }}">

                                {{-- ── Left Column (credentials + main settings) ── --}}
                                <div class="{{ $hasSidebar ? 'xl:col-span-2' : '' }}">
                                    <div class="section-card">
                                        <div class="flex items-center justify-between">
                                            <h4 class="settings-section-title">{{ __($group['label']) }}</h4>
                                            @if (!empty($group['is_manual']) && !empty($group['manual_method_id']))
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                    data-modal-trigger="confirmDeleteGateway-{{ $group['manual_method_id'] }}">
                                                    <i class="ph ph-trash"></i> {{ __('Remove Gateway') }}
                                                </button>
                                            @endif
                                        </div>
                                        @if ($group['description'])
                                            <p class="settings-section-desc">{{ __($group['description']) }}</p>
                                        @endif

                                        @if ($mainSettings->isNotEmpty())
                                            <div class="settings-section-body">
                                                @foreach ($mainSettings as $key => $setting)
                                                    <div class="{{ $setting['type'] === 'editor' ? 'setting-row-full' : 'setting-row' }}"
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
                                                            @elseif($setting['type'] === 'textarea')
                                                                <x-forms.textarea name="settings[{{ $key }}]"
                                                                    :value="$setting['value']"
                                                                    :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))"
                                                                    rows="3" />
                                                            @elseif($setting['type'] === 'select')
                                                                @php
                                                                    $options = $setting['options'] ?? [];
                                                                    $searchable = count($options) > 10;
                                                                @endphp
                                                                @if ($searchable)
                                                                    <x-forms.tom-select name="settings[{{ $key }}]"
                                                                        :selected="$setting['value']"
                                                                        data-dropdown-parent="body">
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
                                                            @elseif($setting['type'] === 'tags')
                                                                @php
                                                                    $tagValue = is_array($setting['value']) ? $setting['value'] : (is_string($setting['value']) && $setting['value'] ? explode(',', $setting['value']) : []);
                                                                    $currencyOptions = \App\Modules\Currencies\Models\Currency::getActiveForSelect();
                                                                @endphp
                                                                <x-forms.tom-select name="settings[{{ $key }}][]"
                                                                    :selected="$tagValue" multiple
                                                                    data-dropdown-parent="body">
                                                                    @foreach ($currencyOptions as $code => $label)
                                                                        <option value="{{ $code }}"
                                                                            @selected(in_array($code, $tagValue))>
                                                                            {{ $label }}</option>
                                                                    @endforeach
                                                                </x-forms.tom-select>
                                                            @elseif($setting['type'] === 'user_fields')
                                                                @php $fieldItems = is_array($setting['value']) ? $setting['value'] : []; @endphp
                                                                <div x-data="userFieldsManager({{ Js::from($fieldItems) }}, '{{ $key }}')" class="space-y-3">
                                                                    {{-- Field list --}}
                                                                    <template x-if="fields.length === 0">
                                                                        <p class="text-sm text-neutral-400 italic">{{ __('No fields defined yet.') }}</p>
                                                                    </template>

                                                                    <template x-for="(field, index) in fields" :key="index">
                                                                        <div class="flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700">
                                                                            <div class="flex-1 min-w-0">
                                                                                <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100" x-text="field.label"></span>
                                                                            </div>
                                                                            <span class="badge badge-neutral text-xs" x-text="field.type"></span>
                                                                            <template x-if="field.required">
                                                                                <span class="badge badge-primary text-xs">{{ __('Required') }}</span>
                                                                            </template>
                                                                            <button type="button" @click="editField(index)"
                                                                                class="btn-icon h-8 w-8 text-neutral-500 hover:text-primary" title="{{ __('Edit') }}">
                                                                                <i class="ph ph-pencil-simple"></i>
                                                                            </button>
                                                                            <button type="button" @click="removeField(index)"
                                                                                class="btn-icon h-8 w-8 text-neutral-500 hover:text-danger" title="{{ __('Remove') }}">
                                                                                <i class="ph ph-trash"></i>
                                                                            </button>
                                                                        </div>
                                                                    </template>

                                                                    {{-- Add Field button --}}
                                                                    <button type="button" @click="openAddModal()"
                                                                        class="btn btn-outline-primary btn-sm">
                                                                        <i class="ph ph-plus"></i> {{ __('Add Field') }}
                                                                    </button>

                                                                    {{-- Hidden input for form submission --}}
                                                                    <input type="hidden" :name="'settings[' + inputKey + ']'" :value="JSON.stringify(fields)">
                                                                </div>
                                                            @elseif($setting['type'] === 'editor')
                                                                <x-forms.editor name="settings[{{ $key }}]"
                                                                    :value="$setting['value']" />
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

                                        {{-- Webhook URL --}}
                                        @if (!empty($group['webhook_url']))
                                            @php $webhookUrl = url("webhooks/{$groupKey}"); @endphp
                                            <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 mt-6">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <i class="ph ph-webhooks-logo text-primary text-lg"></i>
                                                    <span class="text-sm font-semibold text-neutral-900">{{ __('Webhook URL') }}</span>
                                                </div>
                                                <p class="text-xs text-neutral-500 mb-3">
                                                    {{ __('Copy this URL and paste it in your :gateway dashboard webhook settings.', ['gateway' => $group['label']]) }}
                                                </p>
                                                <div class="flex items-center gap-2" x-data="{ copied: false }">
                                                    <input type="text" value="{{ $webhookUrl }}" readonly
                                                        class="flex-1 rounded-lg border border-neutral-200 bg-white dark:bg-neutral-900 px-3 py-2 text-sm font-mono text-neutral-700 dark:text-neutral-300 select-all"
                                                        onclick="this.select()">
                                                    <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                                                        <i class="ph" :class="copied ? 'ph-check text-success' : 'ph-copy'"></i>
                                                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- ── Right Column (logo + charges/config) ── --}}
                                @if ($hasSidebar)
                                    <div class="space-y-6">
                                        {{-- Logo Section --}}
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

                                        {{-- Charges / Config Section --}}
                                        @php
                                            $configSettings = $sidebarSettings->where('type', '!=', 'media');
                                        @endphp
                                        @if ($configSettings->isNotEmpty())
                                            <div class="section-card space-y-4">
                                                <h5 class="text-sm font-semibold text-neutral-900">{{ __('Charges & Limits') }}</h5>

                                                @foreach ($configSettings as $key => $setting)
                                                    <div>
                                                        @if ($setting['type'] === 'tags')
                                                            @php
                                                                $tagValue = is_array($setting['value']) ? $setting['value'] : (is_string($setting['value']) && $setting['value'] ? explode(',', $setting['value']) : []);
                                                                $currencyOptions = \App\Modules\Currencies\Models\Currency::getActiveForSelect();
                                                            @endphp
                                                            <x-forms.tom-select :label="__($setting['label'])"
                                                                name="settings[{{ $key }}][]"
                                                                :selected="$tagValue" multiple
                                                                data-dropdown-parent="body">
                                                                @foreach ($currencyOptions as $code => $label)
                                                                    <option value="{{ $code }}"
                                                                        @selected(in_array($code, $tagValue))>
                                                                        {{ $label }}</option>
                                                                @endforeach
                                                            </x-forms.tom-select>
                                                        @else
                                                            <x-forms.input :label="__($setting['label'])"
                                                                name="settings[{{ $key }}]"
                                                                :type="$setting['type']"
                                                                :value="$setting['value']"
                                                                :placeholder="__('Enter') . ' ' . strtolower(__($setting['label']))" />
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                            </div>
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

    {{-- ── Delete Manual Gateway Forms + Confirm Modals (outside main form) ── --}}
    @foreach ($groups as $groupKey => $group)
        @if (!empty($group['is_manual']) && !empty($group['manual_method_id']))
            <form id="delete-gateway-{{ $group['manual_method_id'] }}" method="POST"
                action="{{ route('admin.manual-payment-methods.destroy', $group['manual_method_id']) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            <x-ui.confirm
                :id="'confirmDeleteGateway-' . $group['manual_method_id']"
                :title="__('Remove Gateway?')"
                :message="__('Are you sure you want to remove :name? All associated settings will be deleted.', ['name' => $group['label']])"
                :confirmText="__('Yes, Remove')"
                :formId="'delete-gateway-' . $group['manual_method_id']" />
        @endif
    @endforeach

    {{-- ── User Input Fields Modal (shared, outside main form) ── --}}
    <x-ui.modal id="userFieldModal" :title="__('Add Field')">
        <div id="userFieldModalBody" class="space-y-4">
            <div>
                <label for="ufm-label" class="form-label">{{ __('Label') }}</label>
                <input type="text" id="ufm-label" class="input-field"
                    placeholder="{{ __('e.g. Transaction ID') }}">
            </div>

            <div>
                <label for="ufm-type" class="form-label">{{ __('Type') }}</label>
                <select id="ufm-type" class="select-field">
                    <option value="text">{{ __('Text') }}</option>
                    <option value="textarea">{{ __('Textarea') }}</option>
                    <option value="number">{{ __('Number') }}</option>
                    <option value="file">{{ __('File Upload') }}</option>
                </select>
            </div>

            <div class="checkbox-wrapper">
                <label class="checkbox-label">
                    <input type="checkbox" id="ufm-required" class="checkbox-field">
                    <span class="checkbox-text">{{ __('Required') }}</span>
                </label>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" id="ufm-save" variant="primary">{{ __('Add') }}</x-ui.button>
            <x-ui.button type="button" variant="ghost" data-modal-close="userFieldModal">{{ __('Cancel') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ── Mobile Bottom Sheet ── --}}
    <div class="settings-sheet-overlay" id="settingsSheetOverlay"></div>

    <button type="button" class="settings-mobile-nav-trigger" id="settingsMobileNavBtn"
        title="{{ __('Navigate settings') }}">
        <i class="ph ph-credit-card"></i>
    </button>

    <div class="settings-sheet" id="settingsSheet">
        <div class="settings-sheet-handle"><span></span></div>
        <div class="settings-sheet-header">
            <h3>{{ __('Payment Gateways') }}</h3>
            <p>{{ __('Configure gateway credentials') }}</p>
        </div>
        <div class="settings-sheet-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" placeholder="{{ __('Search settings...') }}" id="settingsSheetSearch"
                autocomplete="off">
        </div>
        <div class="settings-sheet-list" id="settingsSheetList">
            @foreach ($groups as $groupKey => $group)
                <a href="#" class="settings-sheet-item{{ $loop->first ? ' active' : '' }}"
                    data-sheet-nav="{{ $groupKey }}" data-search-label="{{ strtolower(__($group['label'])) }}">
                    <i class="{{ $group['icon'] }}"></i>
                    {{ __($group['label']) }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── Add Manual Gateway Modal ── --}}
    <x-ui.modal id="addManualGateway" :title="__('Add Manual Gateway')">
        <form method="POST" action="{{ route('admin.manual-payment-methods.store') }}" id="addManualGatewayForm" class="space-y-4">
            @csrf
            <p class="text-sm text-neutral-500">{{ __('Enter a name for your manual payment gateway. A URL-friendly slug will be generated automatically.') }}</p>
            <x-forms.input :label="__('Gateway Name')" name="name" :value="old('name')" required
                :placeholder="__('e.g. Bank Transfer, bKash, Nagad')" />
        </form>
        <x-slot:footer>
            <x-forms.submit :label="__('Create Gateway')" form="addManualGatewayForm" />
            <x-ui.button type="button" variant="ghost" data-modal-close="addManualGateway">{{ __('Cancel') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @if (session('open_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelector('[data-modal-open="addManualGateway"]')?.click();
            });
        </script>
    @endif

    {{-- User Input Fields Alpine Component --}}
    <script>
        document.addEventListener('alpine:init', () => {
            // Store reference to the currently active userFieldsManager instance
            window._activeFieldsManager = null;

            Alpine.data('userFieldsManager', (initialFields, inputKey) => ({
                fields: Array.isArray(initialFields) ? initialFields : [],
                inputKey: inputKey,
                editingIndex: null,

                openAddModal() {
                    this.editingIndex = null;
                    window._activeFieldsManager = this;
                    // Reset modal form
                    document.getElementById('ufm-label').value = '';
                    document.getElementById('ufm-type').value = 'text';
                    document.getElementById('ufm-required').checked = false;
                    // Update modal title and button
                    document.querySelector('#userFieldModal .modal-header h3').textContent = '{{ __('Add Field') }}';
                    document.getElementById('ufm-save').textContent = '{{ __('Add') }}';
                    this._openModal();
                },

                editField(index) {
                    this.editingIndex = index;
                    window._activeFieldsManager = this;
                    const field = this.fields[index];
                    document.getElementById('ufm-label').value = field.label;
                    document.getElementById('ufm-type').value = field.type;
                    document.getElementById('ufm-required').checked = field.required;
                    document.querySelector('#userFieldModal .modal-header h3').textContent = '{{ __('Edit Field') }}';
                    document.getElementById('ufm-save').textContent = '{{ __('Update') }}';
                    this._openModal();
                },

                removeField(index) {
                    this.fields.splice(index, 1);
                },

                _openModal() {
                    const modal = document.getElementById('userFieldModal');
                    if (!modal) return;
                    modal.style.display = 'flex';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            modal.classList.add('active');
                            document.body.classList.add('overflow-hidden');
                        });
                    });
                },

                _closeModal() {
                    const modal = document.getElementById('userFieldModal');
                    if (!modal) return;
                    modal.classList.remove('active');
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }, 300);
                }
            }));
        });

        // Save button handler for user fields modal
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('ufm-save')?.addEventListener('click', () => {
                const manager = window._activeFieldsManager;
                if (!manager) return;

                const label = document.getElementById('ufm-label').value.trim();
                if (!label) return;

                const fieldData = {
                    label: label,
                    type: document.getElementById('ufm-type').value,
                    required: document.getElementById('ufm-required').checked
                };

                if (manager.editingIndex !== null) {
                    manager.fields[manager.editingIndex] = fieldData;
                } else {
                    manager.fields.push(fieldData);
                }

                // Close modal via the active manager's helper
                if (manager._closeModal) {
                    manager._closeModal();
                }
            });
        });
    </script>

</x-layouts.admin>
