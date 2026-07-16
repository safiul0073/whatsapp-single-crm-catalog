@php
    $initialItems = collect($editorItems)->map(function ($item, $index) {
        return [
            'id' => $item['id'] ?? null,
            'temp_key' => $item['temp_key'] ?? ('menu-item-'.$index),
            'depth' => (int) ($item['depth'] ?? 0),
            'item_type' => $item['item_type'] ?? 'external',
            'label' => $item['label'] ?? '',
            'linkable_type' => $item['linkable_type'] ?? null,
            'linkable_id' => $item['linkable_id'] ?? null,
            'url' => $item['url'] ?? '',
            'target' => $item['target'] ?? '_self',
            'is_visible' => (bool) ($item['is_visible'] ?? true),
        ];
    })->values()->all();
@endphp

<form method="POST" action="{{ $action }}" id="frontendMenuForm">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="items_payload" id="menuItemsPayload" value="{{ old('items_payload', json_encode($initialItems)) }}">

    <div class="space-y-6">
        <div class="section-card space-y-4">
            <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Menu Details') }}</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-forms.input :label="__('Name')" name="name" :value="old('name', $menu?->name)" required />
                <x-forms.input :label="__('Slug')" name="slug" :value="old('slug', $menu?->slug)" :hint="__('Leave empty to auto-generate from the menu name')" />
                <x-forms.select
                    :label="__('Status')"
                    name="status"
                    :selected="old('status', $menu?->status ?? 'draft')"
                    :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[320px_minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <div class="section-card space-y-4">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Add Internal Page') }}</h4>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Link to a managed frontend page. The URL will stay in sync when the page slug changes.') }}</p>
                    </div>

                    <x-forms.select :label="__('Page')" name="builder_page_id" :options="$pageOptions" placeholder="{{ __('Choose a page') }}" id="builderPageId" />
                    <x-forms.input :label="__('Label')" name="builder_page_label" :placeholder="__('Optional custom label')" id="builderPageLabel" />
                    <x-ui.button type="button" variant="primary" id="addInternalMenuItem" class="w-full justify-center">
                        <i class="ph ph-plus"></i> {{ __('Add Page Link') }}
                    </x-ui.button>
                </div>

                <div class="section-card space-y-4">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Add Custom URL') }}</h4>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Use this for external resources, campaign links, or destinations outside the managed page library.') }}</p>
                    </div>

                    <x-forms.input :label="__('Label')" name="builder_external_label" :placeholder="__('Documentation')" id="builderExternalLabel" />
                    <x-forms.input :label="__('URL')" name="builder_external_url" :placeholder="__('https://example.com')" id="builderExternalUrl" />
                    <x-forms.select :label="__('Target')" name="builder_external_target" :selected="'_self'" :options="['_self' => __('Same Tab'), '_blank' => __('New Tab')]" id="builderExternalTarget" />
                    <x-ui.button type="button" variant="primary" id="addExternalMenuItem" class="w-full justify-center">
                        <i class="ph ph-plus"></i> {{ __('Add Custom Link') }}
                    </x-ui.button>
                </div>

                <div class="section-card space-y-4">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Add Group Parent') }}</h4>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Group items create accessible dropdown or expandable headings without linking anywhere directly.') }}</p>
                    </div>

                    <x-forms.input :label="__('Group Label')" name="builder_group_label" :placeholder="__('Resources')" id="builderGroupLabel" />
                    <x-ui.button type="button" variant="primary" id="addGroupMenuItem" class="w-full justify-center">
                        <i class="ph ph-plus"></i> {{ __('Add Group') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="space-y-6">
                <div class="section-card space-y-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Menu Builder') }}</h4>
                            <p class="mt-1 text-sm text-neutral-500">{{ __('Drag to reorder. Drop slightly to the right to make an item a child. Use promote and nest controls for quick adjustments.') }}</p>
                        </div>
                        <x-ui.badge variant="primary">{{ __('Max builder depth: 2 levels') }}</x-ui.badge>
                    </div>

                    @error('items_payload')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <div id="menuBuilderCanvas" class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
                        <div id="menuBuilderEmpty" class="rounded-2xl border border-dashed border-neutral-300 px-4 py-12 text-center text-sm text-neutral-400">
                            {{ __('No navigation items yet. Add a page link, custom URL, or group from the side panel.') }}
                        </div>
                        <ul id="menuBuilderList" class="space-y-3"></ul>
                    </div>
                </div>

                <div class="section-card">
                    <div class="flex items-center gap-3">
                        <x-forms.submit :label="$menu ? __('Save Changes') : __('Create Menu')" />
                        <x-ui.button variant="ghost" href="{{ route('admin.frontend-menus.index') }}">{{ __('Cancel') }}</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="section-card space-y-4">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Item Inspector') }}</h4>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Select a row to adjust its label, destination, target, and visibility before saving the menu.') }}</p>
                    </div>

                    <div id="menuInspectorEmpty" class="rounded-2xl border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-400">
                        {{ __('Select a menu item to edit it here.') }}
                    </div>

                    <div id="menuInspectorFields" class="hidden space-y-4">
                        <x-forms.input :label="__('Label')" name="inspector_label" id="inspectorLabel" />
                        <x-forms.input :label="__('Type')" name="inspector_type" id="inspectorType" readonly />
                        <div id="inspectorPageWrap" class="hidden">
                            <x-forms.select :label="__('Linked Page')" name="inspector_page_id" :options="$pageOptions" id="inspectorPageId" />
                        </div>
                        <div id="inspectorUrlWrap" class="hidden">
                            <x-forms.input :label="__('URL')" name="inspector_url" id="inspectorUrl" />
                        </div>
                        <div id="inspectorTargetWrap" class="hidden">
                            <x-forms.select :label="__('Target')" name="inspector_target" :options="['_self' => __('Same Tab'), '_blank' => __('New Tab')]" id="inspectorTarget" />
                        </div>
                        <label class="flex items-center gap-3 text-sm text-neutral-600">
                            <input type="checkbox" id="inspectorVisible" class="h-4 w-4 rounded border-neutral-300 text-primary">
                            {{ __('Visible in navigation') }}
                        </label>
                        <div class="flex gap-3">
                            <x-ui.button type="button" variant="ghost" id="inspectorPromote">
                                <i class="ph ph-arrow-fat-line-left"></i> {{ __('Promote') }}
                            </x-ui.button>
                            <x-ui.button type="button" variant="ghost" id="inspectorNest">
                                <i class="ph ph-arrow-fat-line-right"></i> {{ __('Nest') }}
                            </x-ui.button>
                            <x-ui.button type="button" variant="danger" id="inspectorRemove">
                                <i class="ph ph-trash"></i> {{ __('Remove') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>

                <div class="section-card space-y-4">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Slot Constraints') }}</h4>
                    <div class="space-y-3 text-sm text-neutral-500">
                        @foreach($slotDefinitions as $slot)
                            <div class="rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="font-semibold text-neutral-900">{{ $slot['label'] }}</p>
                                <p class="mt-1">{{ $slot['description'] }}</p>
                                <p class="mt-2 text-xs uppercase tracking-wide text-neutral-400">{{ __('Max depth') }}: {{ $slot['max_depth'] }} {{ \Illuminate\Support\Str::plural('level', $slot['max_depth']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="section-card space-y-4">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Theme Usage') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        @forelse($usage as $assignment)
                            <x-ui.badge variant="primary">{{ $assignment['theme_label'] }} / {{ $assignment['slot_label'] }}</x-ui.badge>
                        @empty
                            <p class="text-sm text-neutral-500">{{ __('This menu is not assigned to any theme slot yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        (function () {
          function bootMenuBuilder() {
            const form = document.getElementById('frontendMenuForm');
            if (!form) return;
            const within = function (id, name) {
              return document.getElementById(id) || form.querySelector('[name="' + name + '"]');
            };

            const payloadInput = document.getElementById('menuItemsPayload');
            const builderList = document.getElementById('menuBuilderList');
            const emptyState = document.getElementById('menuBuilderEmpty');
            const inspectorEmpty = document.getElementById('menuInspectorEmpty');
            const inspectorFields = document.getElementById('menuInspectorFields');
            const labelInput = within('inspectorLabel', 'inspector_label');
            const typeInput = within('inspectorType', 'inspector_type');
            const pageWrap = document.getElementById('inspectorPageWrap');
            const pageInput = within('inspectorPageId', 'inspector_page_id');
            const urlWrap = document.getElementById('inspectorUrlWrap');
            const urlInput = within('inspectorUrl', 'inspector_url');
            const targetWrap = document.getElementById('inspectorTargetWrap');
            const targetInput = within('inspectorTarget', 'inspector_target');
            const visibleInput = document.getElementById('inspectorVisible');
            const promoteButton = document.getElementById('inspectorPromote');
            const nestButton = document.getElementById('inspectorNest');
            const removeButton = document.getElementById('inspectorRemove');
            const internalPageInput = within('builderPageId', 'builder_page_id');
            const internalLabelInput = within('builderPageLabel', 'builder_page_label');
            const externalLabelInput = within('builderExternalLabel', 'builder_external_label');
            const externalUrlInput = within('builderExternalUrl', 'builder_external_url');
            const externalTargetInput = within('builderExternalTarget', 'builder_external_target');
            const groupLabelInput = within('builderGroupLabel', 'builder_group_label');
            const pageOptions = @json($pageOptions);
            const pageLinkableType = @json(\App\Modules\Frontend\Models\Page::class);

            if (!payloadInput || !builderList || !emptyState || !inspectorEmpty || !inspectorFields || !labelInput || !typeInput || !pageInput || !urlInput || !targetInput || !visibleInput || !promoteButton || !nestButton || !removeButton || !internalPageInput || !internalLabelInput || !externalLabelInput || !externalUrlInput || !externalTargetInput || !groupLabelInput) {
              return;
            }

            let items = [];
            let selectedKey = null;
            let draggedKey = null;

            try {
              items = JSON.parse(payloadInput.value || '[]') || [];
            } catch (error) {
              items = [];
            }

            function normalizeItems() {
              let seenRoot = false;

              items = items.map(function (item, index) {
                const depth = Math.min(1, Math.max(0, Number(item.depth || 0)));
                item.depth = seenRoot ? depth : 0;
                item.temp_key = item.temp_key || ('menu-item-' + index + '-' + Date.now());
                item.target = item.target || '_self';
                item.is_visible = item.is_visible !== false;

                if (item.depth === 0) {
                  seenRoot = true;
                }

                return item;
              });

              if (selectedKey && !items.some(function (item) { return item.temp_key === selectedKey; })) {
                selectedKey = items[0] ? items[0].temp_key : null;
              }
            }

            function persist() {
              payloadInput.value = JSON.stringify(items);
            }

            function typeLabel(type) {
              return ({
                internal: @json(__('Internal Page')),
                external: @json(__('Custom URL')),
                group: @json(__('Group Parent')),
              })[type] || type;
            }

            function itemDescription(item) {
              if (item.item_type === 'internal') {
                return pageOptions[item.linkable_id] || @json(__('Linked page'));
              }
              if (item.item_type === 'external') {
                return item.url || @json(__('Custom URL'));
              }
              return @json(__('Dropdown / expandable group'));
            }

            function renderBuilder() {
              normalizeItems();
              persist();
              builderList.innerHTML = '';
              emptyState.style.display = items.length ? 'none' : 'block';

              items.forEach(function (item) {
                const li = document.createElement('li');
                const isSelected = item.temp_key === selectedKey;
                li.className = 'rounded-2xl border bg-white p-4 shadow-sm transition cursor-move ' + (isSelected ? 'border-primary ring-2 ring-primary/10' : 'border-neutral-200');
                li.draggable = true;
                li.dataset.itemKey = item.temp_key;
                li.style.marginLeft = item.depth > 0 ? '48px' : '0';
                li.innerHTML = `
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                      <div class="pt-1 text-lg text-neutral-400"><i class="ph ph-dots-six-vertical"></i></div>
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="truncate font-medium text-neutral-900">${item.label || @json(__('Untitled item'))}</p>
                          <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">${typeLabel(item.item_type)}</span>
                          ${item.depth > 0 ? '<span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary">'+@json(__('Child'))+'</span>' : ''}
                          ${item.is_visible ? '' : '<span class="rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-danger">'+@json(__('Hidden'))+'</span>'}
                        </div>
                        <p class="mt-1 truncate text-sm text-neutral-500">${itemDescription(item)}</p>
                      </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                      <button type="button" class="rounded-xl border border-neutral-200 px-3 py-2 text-xs font-semibold text-neutral-500 hover:border-primary hover:text-primary" data-row-promote>${@json(__('Promote'))}</button>
                      <button type="button" class="rounded-xl border border-neutral-200 px-3 py-2 text-xs font-semibold text-neutral-500 hover:border-primary hover:text-primary" data-row-nest>${@json(__('Nest'))}</button>
                      <button type="button" class="text-danger" data-row-remove><i class="ph ph-trash"></i></button>
                    </div>
                  </div>
                `;

                li.addEventListener('click', function (event) {
                  if (event.target.closest('[data-row-remove],[data-row-promote],[data-row-nest]')) {
                    return;
                  }
                  selectedKey = item.temp_key;
                  renderBuilder();
                  renderInspector();
                });

                li.addEventListener('dragstart', function (event) {
                  draggedKey = item.temp_key;
                  li.classList.add('opacity-60');
                  if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                  }
                });

                li.addEventListener('dragend', function () {
                  draggedKey = null;
                  li.classList.remove('opacity-60');
                });

                li.addEventListener('dragover', function (event) {
                  event.preventDefault();
                });

                li.addEventListener('drop', function (event) {
                  event.preventDefault();
                  if (!draggedKey || draggedKey === item.temp_key) return;

                  const draggedIndex = items.findIndex(function (entry) { return entry.temp_key === draggedKey; });
                  const targetIndex = items.findIndex(function (entry) { return entry.temp_key === item.temp_key; });
                  if (draggedIndex === -1 || targetIndex === -1) return;

                  const moved = items.splice(draggedIndex, 1)[0];
                  const targetRect = li.getBoundingClientRect();
                  const insertAfter = event.clientY > targetRect.top + (targetRect.height / 2);
                  moved.depth = Math.max(0, Math.min(1, event.clientX - targetRect.left > 140 ? 1 : 0));

                  const adjustedTarget = draggedIndex < targetIndex ? targetIndex - 1 : targetIndex;
                  items.splice(insertAfter ? adjustedTarget + 1 : adjustedTarget, 0, moved);
                  selectedKey = moved.temp_key;
                  renderBuilder();
                  renderInspector();
                });

                li.querySelector('[data-row-remove]').addEventListener('click', function () {
                  removeItem(item.temp_key);
                });

                li.querySelector('[data-row-promote]').addEventListener('click', function () {
                  item.depth = 0;
                  renderBuilder();
                  renderInspector();
                });

                li.querySelector('[data-row-nest]').addEventListener('click', function () {
                  const currentIndex = items.findIndex(function (entry) { return entry.temp_key === item.temp_key; });
                  item.depth = currentIndex > 0 ? 1 : 0;
                  renderBuilder();
                  renderInspector();
                });

                builderList.appendChild(li);
              });
            }

            function renderInspector() {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              const hasItem = Boolean(selectedItem);

              inspectorEmpty.classList.toggle('hidden', hasItem);
              inspectorFields.classList.toggle('hidden', !hasItem);

              if (!selectedItem) {
                return;
              }

              labelInput.value = selectedItem.label || '';
              typeInput.value = typeLabel(selectedItem.item_type);
              visibleInput.checked = selectedItem.is_visible !== false;
              pageWrap.classList.toggle('hidden', selectedItem.item_type !== 'internal');
              urlWrap.classList.toggle('hidden', selectedItem.item_type !== 'external');
              targetWrap.classList.toggle('hidden', selectedItem.item_type === 'group');

              pageInput.value = selectedItem.linkable_id || '';
              urlInput.value = selectedItem.item_type === 'external' ? (selectedItem.url || '') : '';
              targetInput.value = selectedItem.target || '_self';
            }

            function removeItem(tempKey) {
              const index = items.findIndex(function (item) { return item.temp_key === tempKey; });
              if (index === -1) return;

              const target = items[index];
              let removeCount = 1;

              if ((target.depth || 0) === 0) {
                for (let cursor = index + 1; cursor < items.length; cursor += 1) {
                  if ((items[cursor].depth || 0) === 0) break;
                  removeCount += 1;
                }
              }

              items.splice(index, removeCount);
              selectedKey = items[0] ? items[0].temp_key : null;
              renderBuilder();
              renderInspector();
            }

            function addItem(item) {
              items.push(item);
              selectedKey = item.temp_key;
              renderBuilder();
              renderInspector();
            }

            document.getElementById('addInternalMenuItem').addEventListener('click', function () {
              const pageId = internalPageInput.value;
              if (!pageId) return;

              const fallbackLabel = (pageOptions[pageId] || '').replace(/\s+\(.+\)$/, '');
              addItem({
                temp_key: 'menu-item-' + Date.now(),
                depth: 0,
                item_type: 'internal',
                label: internalLabelInput.value || fallbackLabel,
                linkable_type: pageLinkableType,
                linkable_id: Number(pageId),
                url: '',
                target: '_self',
                is_visible: true,
              });

              internalLabelInput.value = '';
              internalPageInput.value = '';
            });

            document.getElementById('addExternalMenuItem').addEventListener('click', function () {
              const label = externalLabelInput.value.trim();
              const url = externalUrlInput.value.trim();
              const target = externalTargetInput.value || '_self';
              if (!label || !url) return;

              addItem({
                temp_key: 'menu-item-' + Date.now(),
                depth: 0,
                item_type: 'external',
                label: label,
                linkable_type: null,
                linkable_id: null,
                url: url,
                target: target,
                is_visible: true,
              });

              externalLabelInput.value = '';
              externalUrlInput.value = '';
              externalTargetInput.value = '_self';
            });

            document.getElementById('addGroupMenuItem').addEventListener('click', function () {
              const label = groupLabelInput.value.trim();
              if (!label) return;

              addItem({
                temp_key: 'menu-item-' + Date.now(),
                depth: 0,
                item_type: 'group',
                label: label,
                linkable_type: null,
                linkable_id: null,
                url: '',
                target: '_self',
                is_visible: true,
              });

              groupLabelInput.value = '';
            });

            labelInput.addEventListener('input', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.label = this.value;
              renderBuilder();
            });

            pageInput.addEventListener('change', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.linkable_id = this.value ? Number(this.value) : null;
              selectedItem.linkable_type = this.value ? pageLinkableType : null;
              renderBuilder();
            });

            urlInput.addEventListener('input', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.url = this.value;
              renderBuilder();
            });

            targetInput.addEventListener('change', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.target = this.value;
            });

            visibleInput.addEventListener('change', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.is_visible = this.checked;
              renderBuilder();
            });

            promoteButton.addEventListener('click', function () {
              const selectedItem = items.find(function (item) { return item.temp_key === selectedKey; });
              if (!selectedItem) return;
              selectedItem.depth = 0;
              renderBuilder();
              renderInspector();
            });

            nestButton.addEventListener('click', function () {
              const selectedIndex = items.findIndex(function (item) { return item.temp_key === selectedKey; });
              if (selectedIndex === -1) return;
              items[selectedIndex].depth = selectedIndex > 0 ? 1 : 0;
              renderBuilder();
              renderInspector();
            });

            removeButton.addEventListener('click', function () {
              if (!selectedKey) return;
              removeItem(selectedKey);
            });

            form.addEventListener('submit', function () {
              normalizeItems();
              persist();
            });

            selectedKey = items[0] ? items[0].temp_key : null;
            renderBuilder();
            renderInspector();
          }

          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootMenuBuilder);
          } else {
            bootMenuBuilder();
          }
        })();
    </script>
@endpush
