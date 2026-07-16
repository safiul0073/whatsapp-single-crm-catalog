@php
    $sectionData = old('data', $section?->data ?? []);
    $sectionType = $selectedType ?? $section?->type;

    if ($sectionType === 'marketing_use_cases' && ! empty($sectionData['cases']) && is_array($sectionData['cases'])) {
        $sectionData['cases'] = array_map(function (array $case): array {
            $mockup = $case['mockup_data'] ?? [];

            if (is_array($case['bullets'] ?? null)) {
                $case['bullets'] = implode(PHP_EOL, $case['bullets']);
            }

            if (! empty($mockup['messages']) && is_array($mockup['messages'])) {
                $case['visual_type'] ??= 'chatbot';
                $case['bot_name'] ??= $mockup['bot_name'] ?? null;
                $case['status'] ??= $mockup['status'] ?? null;
                $case['messages'] ??= implode(PHP_EOL, $mockup['messages']);
            } elseif (! empty($mockup['delivered']) || ! empty($mockup['change'])) {
                $case['visual_type'] ??= 'performance';
                $case['delivered'] ??= $mockup['delivered'] ?? null;
                $case['change'] ??= $mockup['change'] ?? null;
            } else {
                $case['visual_type'] ??= 'campaign';
                $case['campaign_name'] ??= $mockup['campaign_name'] ?? ($mockup['title'] ?? null);
                $case['status'] ??= $mockup['status'] ?? null;

                foreach (($mockup['stats'] ?? []) as $index => $stat) {
                    $position = $index + 1;
                    $case["stat_{$position}_value"] ??= $stat['value'] ?? null;
                    $case["stat_{$position}_label"] ??= $stat['label'] ?? null;
                }
            }

            return $case;
        }, $sectionData['cases']);
    }
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="type" value="{{ $selectedType }}">

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="section-card space-y-4">
                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Section Details') }}</h4>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-forms.input :label="__('Name')" name="name" :value="old('name', $section?->name)" required />
                    <x-forms.input :label="__('Slug')" name="slug" :value="old('slug', $section?->slug)" :hint="__('Leave empty to auto-generate from name')" />
                </div>

                <div>
                    <x-media.picker
                        :label="__('Preview Image')"
                        name="preview_image_media_id"
                        :value="old('preview_image_media_id', $section?->preview_image_media_id)"
                        accept="image"
                        :hint="__('Internal thumbnail for this section in admin listings. Recommended size: 1200 x 675 px. Use JPG, PNG, or WebP.')"
                    />
                </div>

                <x-forms.textarea :label="__('Description')" name="description" :value="old('description', $section?->description)" :hint="__('Internal note for editors and developers.')" rows="3" />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-forms.select
                        :label="__('Status')"
                        name="status"
                        :selected="old('status', $section?->status ?? 'draft')"
                        :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    />
                </div>
            </div>

            <div class="section-card space-y-5">
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Section Schema') }}</h4>
                    <p class="mt-1 text-sm text-neutral-500">{{ $definition['description'] ?? __('Fill the values required by this section type.') }}</p>
                </div>

                <div class="space-y-5">
                    @php
                        $fields = $definition['fields'] ?? [];
                        $renderedGroups = [];
                    @endphp

                    @foreach($fields as $fieldKey => $field)
                        @php
                            $groupKey = $field['group'] ?? null;
                        @endphp

                        @if($groupKey)
                            @if(in_array($groupKey, $renderedGroups, true))
                                @continue
                            @endif

                            @php
                                $renderedGroups[] = $groupKey;
                                $groupFields = collect($fields)->filter(fn ($groupField) => ($groupField['group'] ?? null) === $groupKey);
                                $groupLabel = $field['group_label'] ?? $groupKey;
                                $groupHint = $field['group_hint'] ?? null;
                            @endphp

                            <div class="rounded-2xl border border-neutral-200 bg-neutral-50/70 p-4">
                                <div class="mb-4">
                                    <h5 class="text-sm font-semibold text-neutral-900">{{ __($groupLabel) }}</h5>
                                    @if($groupHint)
                                        <p class="mt-1 text-xs text-neutral-500">{{ __($groupHint) }}</p>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @foreach($groupFields as $groupFieldKey => $groupField)
                                        <x-forms.schema-field
                                            :field="$groupField"
                                            :name="'data[' . $groupFieldKey . ']'"
                                            :error-key="'data.' . $groupFieldKey"
                                            :value="$sectionData[$groupFieldKey] ?? ($groupField['default'] ?? null)"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <x-forms.schema-field
                                :field="$field"
                                :name="'data[' . $fieldKey . ']'"
                                :error-key="'data.' . $fieldKey"
                                :value="$sectionData[$fieldKey] ?? ($field['default'] ?? null)"
                            />
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="section-card space-y-4">
                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Compatibility') }}</h4>
                <p class="text-sm text-neutral-500">{{ __('This section type is currently supported by the following themes.') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(($definition['supported_themes'] ?? []) as $themeKey)
                        <x-ui.badge variant="primary">{{ $themeLabels[$themeKey] ?? ucfirst($themeKey) }}</x-ui.badge>
                    @endforeach
                </div>
            </div>

            @if($section)
                <div class="section-card space-y-3">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Usage') }}</h4>
                    <p class="text-sm text-neutral-600">{{ trans_choice(':count page currently uses this section.|:count pages currently use this section.', $section->pages()->count(), ['count' => $section->pages()->count()]) }}</p>
                </div>
            @endif

            <div class="section-card">
                <div class="flex items-center gap-3">
                    <x-forms.submit :label="$section ? __('Save Changes') : __('Create Section')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.frontend-sections.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
</form>
