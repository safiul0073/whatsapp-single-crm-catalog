@php
    $selectedSections = collect(old('sections', $attachedSectionIds))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->values();
    $allSectionsById = $sections->keyBy('id');
    $sectionLibraryOptions = $sections->mapWithKeys(function ($item) {
        return [
            $item->id => $item->name . ' - ' . config('frontend-sections.' . $item->type . '.label', $item->type),
        ];
    })->all();
    $composerSectionMap = $sections->mapWithKeys(function ($item) {
        return [
            $item->id => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => config('frontend-sections.' . $item->type . '.label', $item->type),
            ],
        ];
    })->all();
    $selectedSectionCount = $selectedSections->count();
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="section-card space-y-4">
                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Page Details') }}</h4>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-forms.input :label="__('Title')" name="title" :value="old('title', $page?->title)" required />
                    <x-forms.input :label="__('Slug')" name="slug" :value="old('slug', $page?->slug)" :hint="__('Leave empty to auto-generate from title')" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-forms.select
                        :label="__('Status')"
                        name="status"
                        :selected="old('status', $page?->status ?? 'draft')"
                        :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    />
                    <x-forms.select :label="__('Default Layout')" name="default_layout" :selected="old('default_layout', $page?->default_layout ?? 'default')" :options="$layoutOptions" />
                    <div class="flex h-full items-center pt-1 md:items-end md:pt-0">
                        <div class="rounded-2xl border border-neutral-100 bg-neutral-0 px-4 py-3">
                            <x-forms.toggle :label="__('Use as Home Page')" name="is_home" :checked="(bool) old('is_home', $page?->is_home)" />
                        </div>
                    </div>
                </div>

                <x-forms.textarea :label="__('Excerpt')" name="excerpt" :value="old('excerpt', $page?->excerpt)" :hint="__('Short summary used for listings and page summaries.')" rows="3" />
            </div>

            <div class="section-card space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Page Composer') }}</h4>
                            <x-ui.badge variant="primary" id="pageComposerCount">{{ trans_choice('{1} :count section selected|[2,*] :count sections selected', $selectedSectionCount, ['count' => $selectedSectionCount]) }}</x-ui.badge>
                        </div>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Drag sections to reorder them. Add shared sections from the library below to build the page flow.') }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-100 bg-neutral-50 p-4">
                    <ul id="pageComposerList" class="space-y-3" data-empty-message="{{ __('No sections added yet. Use the library below to start composing the page.') }}">
                        @forelse($selectedSections as $sectionId)
                            @php $section = $allSectionsById[$sectionId] ?? null; @endphp
                            @if($section)
                                <li class="cursor-move rounded-[28px] border border-neutral-100 bg-neutral-0 p-5 shadow-sm transition" draggable="true" data-composer-item data-section-id="{{ $section->id }}">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="pt-1 text-lg text-neutral-400">
                                                <i class="ph ph-dots-six-vertical"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate font-medium text-neutral-950">{{ $section->name }}</p>
                                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                                                        {{ config('frontend-sections.' . $section->type . '.label', $section->type) }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm text-neutral-500">{{ __('Shared library section') }}</p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-icon h-9 w-9 text-error transition hover:opacity-80" data-remove-section>
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="sections[]" value="{{ $section->id }}">
                                </li>
                            @endif
                        @empty
                            <li class="rounded-2xl border border-dashed border-neutral-200 px-4 py-6 text-center text-sm text-neutral-400" data-empty-state>
                                {{ __('No sections added yet. Use the library below to start composing the page.') }}
                            </li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-2xl border border-neutral-100 bg-neutral-0 p-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                        <x-forms.select
                            :label="__('Add From Section Library')"
                            name="section_library_picker"
                            :options="$sectionLibraryOptions"
                            placeholder="{{ __('Choose a section to add') }}"
                            id="sectionLibraryPicker"
                        />
                        <div class="flex items-end">
                            <x-ui.button type="button" variant="primary" id="addSectionToComposer">
                                <i class="ph ph-plus"></i> {{ __('Add Section') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="section-card space-y-4">
                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('SEO') }}</h4>
                <x-forms.input :label="__('Meta Title')" name="meta_title" :value="old('meta_title', $page?->meta_title)" />
                <x-forms.textarea :label="__('Meta Description')" name="meta_description" :value="old('meta_description', $page?->meta_description)" rows="4" />
                <x-media.picker
                    :label="__('Meta Image')"
                    name="meta_image_media_id"
                    :value="old('meta_image_media_id', $page?->meta_image_media_id)"
                    accept="image"
                    :hint="__('Social sharing image for this page. Recommended size: 1200 x 630 px. Use JPG, PNG, or WebP.')"
                />
            </div>

            <div class="section-card space-y-4">
                <h4 class="text-sm font-bold uppercase tracking-wider text-neutral-500">{{ __('Compatibility Snapshot') }}</h4>
                <p class="text-sm text-neutral-500">{{ __('The active theme is') }} <span class="font-semibold text-neutral-950">{{ $activeThemeLabel }}</span>.</p>
                <p class="text-sm text-neutral-500">{{ __('Pages remain shared across themes and unsupported sections fall back automatically when needed.') }}</p>
            </div>

            <div class="section-card">
                <div class="flex items-center gap-3">
                    <x-forms.submit :label="$page ? __('Save Changes') : __('Create Page')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.frontend-pages.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        (function () {
          function bootComposer() {
            const list = document.getElementById('pageComposerList');
            const picker = document.getElementById('sectionLibraryPicker');
            const addButton = document.getElementById('addSectionToComposer');
            const countBadge = document.getElementById('pageComposerCount');
            if (!list || !picker || !addButton) return;

            const sectionMap = @json($composerSectionMap);
            const emptyMessage = list.dataset.emptyMessage || @json(__('No sections added yet. Use the library below to start composing the page.'));
            const composerItemClasses = 'cursor-move rounded-[28px] border border-neutral-100 bg-neutral-0 p-5 shadow-sm transition';
            const composerEmptyClasses = 'rounded-2xl border border-dashed border-neutral-200 px-4 py-6 text-center text-sm text-neutral-400';

            let dragged = null;

            function refreshEmptyState() {
              const items = list.querySelectorAll('[data-composer-item]');
              const empty = list.querySelector('[data-empty-state]');
              if (items.length === 0 && !empty) {
                const li = document.createElement('li');
                li.className = composerEmptyClasses;
                li.dataset.emptyState = '1';
                li.textContent = emptyMessage;
                list.appendChild(li);
              }
              if (items.length > 0 && empty) {
                empty.remove();
              }

              if (countBadge) {
                countBadge.textContent = items.length === 1
                  ? @json(__('1 section selected'))
                  : items.length + ' ' + @json(__('sections selected'));
              }
            }

            function createItem(section) {
              const li = document.createElement('li');
              li.className = composerItemClasses;
              li.draggable = true;
              li.dataset.composerItem = '1';
              li.dataset.sectionId = section.id;
              li.innerHTML = `
                <div class="flex items-center justify-between gap-3">
                  <div class="flex min-w-0 items-center gap-3">
                    <div class="pt-1 text-lg text-neutral-400">
                      <i class="ph ph-dots-six-vertical"></i>
                    </div>
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate font-medium text-neutral-950">${section.name}</p>
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">${section.type}</span>
                      </div>
                      <p class="mt-1 text-sm text-neutral-500">${@json(__('Shared library section'))}</p>
                    </div>
                  </div>
                  <button type="button" class="btn-icon h-9 w-9 text-error transition hover:opacity-80" data-remove-section>
                    <i class="ph ph-trash"></i>
                  </button>
                </div>
                <input type="hidden" name="sections[]" value="${section.id}">
              `;
              bindItem(li);
              return li;
            }

            function bindItem(item) {
              item.addEventListener('dragstart', function (event) {
                dragged = item;
                item.classList.add('opacity-60', 'scale-[0.99]');
                if (event.dataTransfer) {
                  event.dataTransfer.effectAllowed = 'move';
                }
              });

              item.addEventListener('dragend', function () {
                dragged = null;
                item.classList.remove('opacity-60', 'scale-[0.99]');
              });
            }

            function getDragAfterElement(container, y) {
              const items = [...container.querySelectorAll('[data-composer-item]:not(.opacity-60)')];

              return items.reduce(function (closest, child) {
                const rect = child.getBoundingClientRect();
                const offset = y - rect.top - rect.height / 2;

                if (offset < 0 && offset > closest.offset) {
                  return { offset, element: child };
                }

                return closest;
              }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
            }

            list.querySelectorAll('[data-composer-item]').forEach(bindItem);

            list.addEventListener('dragover', function (event) {
              event.preventDefault();
              if (!dragged) return;

              const afterElement = getDragAfterElement(list, event.clientY);

              if (!afterElement) {
                list.appendChild(dragged);
                return;
              }

              if (afterElement !== dragged) {
                list.insertBefore(dragged, afterElement);
              }
            });

            addButton.addEventListener('click', function () {
              const id = String(picker.value || '');
              if (!id || !sectionMap[id]) return;
              if (list.querySelector('[data-section-id="' + id + '"]')) return;
              list.appendChild(createItem(sectionMap[id]));
              picker.value = '';
              refreshEmptyState();
            });

            list.addEventListener('click', function (event) {
              const button = event.target.closest('[data-remove-section]');
              if (!button) return;
              button.closest('[data-composer-item]')?.remove();
              refreshEmptyState();
            });

            refreshEmptyState();
          }

          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootComposer);
          } else {
            bootComposer();
          }
        })();
    </script>
@endpush
