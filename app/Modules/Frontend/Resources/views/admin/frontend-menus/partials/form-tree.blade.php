@php
    $initialItems = collect($editorItems)->map(function ($item, $index) {
        return [
            'id' => $item['id'] ?? null,
            'temp_key' => $item['temp_key'] ?? ('menu-item-'.$index),
            'item_type' => $item['item_type'] ?? 'external',
            'label' => $item['label'] ?? '',
            'linkable_type' => $item['linkable_type'] ?? null,
            'linkable_id' => $item['linkable_id'] ?? null,
            'url' => $item['url'] ?? '',
            'target' => $item['target'] ?? '_self',
            'is_visible' => (bool) ($item['is_visible'] ?? true),
            'children' => collect($item['children'] ?? [])->map(function ($child, $childIndex) {
                return [
                    'id' => $child['id'] ?? null,
                    'temp_key' => $child['temp_key'] ?? ('menu-child-'.$childIndex),
                    'item_type' => $child['item_type'] ?? 'external',
                    'label' => $child['label'] ?? '',
                    'linkable_type' => $child['linkable_type'] ?? null,
                    'linkable_id' => $child['linkable_id'] ?? null,
                    'url' => $child['url'] ?? '',
                    'target' => $child['target'] ?? '_self',
                    'is_visible' => (bool) ($child['is_visible'] ?? true),
                    'children' => [],
                ];
            })->values()->all(),
        ];
    })->values()->all();
    $bootItems = old('items_payload')
        ? (json_decode((string) old('items_payload'), true) ?: $initialItems)
        : $initialItems;
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

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="section-card space-y-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Menu Builder') }}</h4>
                                <x-ui.badge variant="primary">{{ __('Max depth: 2 levels') }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-sm text-neutral-500">{{ __('Each root item acts as a parent. Add submenu items by selecting a parent directly, then drag entire parent groups to reorder the navigation cleanly.') }}</p>
                        </div>
                        <x-ui.button type="button" variant="primary" data-modal-trigger="addMenuItemModal" class="justify-center self-start whitespace-nowrap lg:self-auto" id="openAddMenuItemModal">
                            <i class="ph ph-plus"></i> {{ __('Add Item') }}
                        </x-ui.button>
                    </div>

                    @error('items_payload')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <div id="menuBuilderCanvas" class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
                        <div id="menuBuilderEmpty" class="rounded-2xl border border-dashed border-neutral-300 px-4 py-12 text-center text-sm text-neutral-400">
                            {{ __('No navigation items yet. Add your first item to start building the menu tree.') }}
                        </div>
                        <ul id="menuBuilderList" class="space-y-4"></ul>
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
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Select a menu item, adjust its details here, then apply those changes back into the builder.') }}</p>
                    </div>

                    <div id="menuInspectorEmpty" class="rounded-2xl border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-400">
                        {{ __('Select a menu item to edit it here.') }}
                    </div>

                    <div id="menuInspectorFields" class="hidden space-y-4">
                        <x-forms.input :label="__('Label')" name="inspector_label" id="inspectorLabel" />
                        <x-forms.input :label="__('Type')" name="inspector_type" id="inspectorType" readonly />
                        <div id="inspectorParentWrap">
                            <x-forms.select :label="__('Parent Item')" name="inspector_parent_key" :options="[]" id="inspectorParentKey" />
                            <p id="inspectorParentHint" class="form-hint">{{ __('Root items with submenu children stay at the root level.') }}</p>
                        </div>
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
                        <div class="flex flex-wrap gap-3 pt-2">
                            <x-ui.button type="button" variant="primary" id="inspectorUpdate">
                                <i class="ph ph-check"></i> {{ __('Update Item') }}
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

<script type="application/json" id="frontendMenuBuilderConfig">
    {!! json_encode([
        'bootItems' => $bootItems,
        'pageOptions' => $pageOptions,
        'pageLinkableType' => \App\Modules\Frontend\Models\Page::class,
        'strings' => [
            'untitledItem' => __('Untitled item'),
            'internalPage' => __('Internal Page'),
            'customUrl' => __('Custom URL'),
            'groupParent' => __('Group Parent'),
            'linkedPage' => __('Linked page'),
            'parentGroupDescription' => __('Parent group for nested menu items'),
            'submenuItems' => __('Submenu Items'),
            'item' => __('item'),
            'items' => __('items'),
            'hidden' => __('Hidden'),
            'addChild' => __('Add Child'),
            'parent' => __('Parent'),
            'noSubmenuItems' => __('No submenu items yet. Add a child item if this should open a submenu.'),
            'rootLevel' => __('Root level'),
            'moveChildrenFirst' => __('Move or remove submenu items first if you want to change this parent item to another level.'),
            'chooseParentOrRoot' => __('Choose a parent root item to turn this into a submenu item, or keep it at the root level.'),
            'addAtRootLevel' => __('Add at root level'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

@push('modals')
    <x-ui.modal id="addMenuItemModal" :title="__('Add Menu Item')" size="lg">
        <form id="addMenuItemForm">
            <div class="space-y-4">
                <x-forms.select
                    :label="__('Item Type')"
                    name="modal_item_type"
                    id="modalItemType"
                    :selected="'internal'"
                    :options="['internal' => __('Internal Page'), 'external' => __('Custom URL'), 'group' => __('Group Parent')]"
                />

                <x-forms.select
                    :label="__('Parent Item')"
                    name="modal_parent_key"
                    id="modalParentKey"
                    :options="[]"
                    :selected="''"
                    :placeholder="__('Add at root level')"
                />

                <div id="modalPageFields" class="space-y-4">
                    <x-forms.select :label="__('Page')" name="modal_page_id" :options="$pageOptions" id="modalPageId" placeholder="{{ __('Choose a page') }}" />
                    <x-forms.input :label="__('Label')" name="modal_page_label" id="modalPageLabel" :placeholder="__('Optional custom label')" />
                </div>

                <div id="modalExternalFields" class="hidden space-y-4">
                    <x-forms.input :label="__('Label')" name="modal_external_label" id="modalExternalLabel" :placeholder="__('Documentation')" />
                    <x-forms.input :label="__('URL')" name="modal_external_url" id="modalExternalUrl" :placeholder="__('https://example.com')" />
                    <x-forms.select :label="__('Target')" name="modal_external_target" id="modalExternalTarget" :selected="'_self'" :options="['_self' => __('Same Tab'), '_blank' => __('New Tab')]" />
                </div>

                <div id="modalGroupFields" class="hidden space-y-4">
                    <x-forms.input :label="__('Group Label')" name="modal_group_label" id="modalGroupLabel" :placeholder="__('Resources')" />
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-ui.button type="button" variant="ghost" data-modal-close="addMenuItemModal">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    <i class="ph ph-plus"></i> {{ __('Add Item') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endpush

@push('scripts')
    @vite('resources/js/components/frontend-menu-builder.js')
@endpush
