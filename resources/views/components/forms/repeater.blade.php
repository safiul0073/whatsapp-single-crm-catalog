@props([
    'label' => '',
    'name',
    'items' => [],
    'schema' => [],
    'hint' => '',
    'errorKey' => null,
])

@php
    $componentId = 'repeater-' . md5($name . json_encode($schema));
    $schemaCollection = collect($schema);
    $hasEditorField = $schemaCollection->contains(fn($field) => ($field['type'] ?? 'text') === 'editor');
    $initialItems = old($name, $items);
    if (is_string($initialItems)) {
        $decoded = json_decode($initialItems, true);
        $initialItems = is_array($decoded) ? $decoded : [];
    }
    $errorName = $errorKey ?? $name;
@endphp

<div id="{{ $componentId }}" data-repeater-field data-name="{{ $name }}"
    data-schema='@json($schema)' data-initial-items='@json($initialItems)' class="space-y-3">
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}" value='@json($initialItems)' data-repeater-input>

    <div class="space-y-3" data-repeater-items></div>

    <button type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-dashed border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-primary hover:text-primary"
        data-repeater-add>
        <i class="ph ph-plus"></i>
        {{ __('Add Item') }}
    </button>

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif

    @error($errorName)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@if ($hasEditorField)
    @once
        @push('styles')
            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
        @endpush
    @endonce

    @once
        @push('scripts')
            <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
        @endpush
    @endonce
@endif

@once
    @push('scripts')
        <script>
            (function() {
                if (window.__frontendRepeaterInitialized) return;
                window.__frontendRepeaterInitialized = true;

                let repeaterSequence = 0;

                function escapeHtml(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function normalizeText(value) {
                    if (value === null || value === undefined || value === '') {
                        return '';
                    }

                    if (Array.isArray(value)) {
                        return value.map(normalizeText).filter(Boolean).join(', ');
                    }

                    if (typeof value === 'object') {
                        if (value.key) {
                            return String(value.key);
                        }

                        return Object.values(value).map(normalizeText).filter(Boolean).join(', ');
                    }

                    return String(value);
                }

                function renderHint(fieldDef) {
                    const hint = normalizeText(fieldDef.hint || '');
                    if (!hint) {
                        return '';
                    }

                    return `<p class="form-hint">${escapeHtml(hint)}</p>`;
                }

                function renderField(fieldKey, fieldDef, rowData, index, rowKey) {
                    const value = rowData?.[fieldKey] ?? '';
                    const label = escapeHtml(normalizeText(fieldDef.label || fieldKey));
                    const fieldType = fieldDef.type || 'text';
                    const fieldName = escapeHtml(fieldKey);
                    const hintMarkup = renderHint(fieldDef);
                    const inputType = ['email', 'url', 'password', 'tel', 'search', 'number', 'color'].includes(fieldType) ?
                        fieldType : 'text';

                    if (fieldType === 'editor') {
                        const editorId = `repeater-editor-${rowKey}-${fieldName}-${index}`;

                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <div class="editor-wrapper" data-repeater-editor-wrapper>
                        <div id="${editorId}" data-repeater-editor="${fieldName}">${value ?? ''}</div>
                        <textarea id="${editorId}-html" class="editor-html-source"></textarea>
                      </div>
                      <input type="hidden" value="${escapeHtml(value)}" data-repeater-prop="${fieldName}" data-repeater-editor-input="${fieldName}">
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'textarea') {
                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <textarea class="textarea-field" rows="4" data-repeater-prop="${fieldName}">${escapeHtml(value)}</textarea>
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'select') {
                        const options = Object.entries(fieldDef.options || {}).map(function([optionValue, optionLabel]) {
                            const selected = String(value ?? '') === String(optionValue) ? ' selected' : '';
                            return `<option value="${escapeHtml(optionValue)}"${selected}>${escapeHtml(normalizeText(optionLabel))}</option>`;
                        }).join('');

                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <select class="select-field" data-repeater-prop="${fieldName}">
                        ${options}
                      </select>
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'feature' || fieldType === 'boolean') {
                        const checked = value ? ' checked' : '';

                        return `
                    <div class="space-y-2">
                      <label class="checkbox-label">
                        <input type="checkbox" class="checkbox-field" data-repeater-prop="${fieldName}" value="1"${checked}>
                        <span class="checkbox-text">${label}</span>
                      </label>
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'number') {
                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <input type="number" value="${escapeHtml(value)}" class="input-field" data-repeater-prop="${fieldName}">
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'color') {
                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <input type="color" value="${escapeHtml(value || '#000000')}" class="input-field h-12 p-2" data-repeater-prop="${fieldName}">
                      ${hintMarkup}
                    </div>
                  `;
                    }

                    if (fieldType === 'media') {
                        const accept = fieldDef.accept || 'image';
                        const mediaId = String(value ?? '');
                        const hasMedia = mediaId !== '';
                        const previewHtml = hasMedia
                            ? `<img src="" alt="" data-repeater-media-preview-img style="max-height:64px;border-radius:6px;">`
                            : '';

                        const html = `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <div class="media-picker" data-media-picker data-media-accept="${escapeHtml(accept)}" data-repeater-media-picker>
                        <div class="media-picker-preview" data-media-picker-preview>${previewHtml}</div>
                        <input type="hidden" value="${escapeHtml(mediaId)}" data-media-picker-input data-repeater-prop="${fieldName}">
                        <div class="media-picker-actions">
                          <button type="button" class="media-picker-browse" data-media-picker-trigger>
                            <i class="ph ph-folder-open"></i>
                            ${hasMedia ? 'Change' : 'Browse Media'}
                          </button>
                          ${hasMedia ? `<button type="button" class="media-picker-remove" data-media-picker-remove><i class="ph ph-x"></i> Remove</button>` : ''}
                        </div>
                        ${hintMarkup}
                      </div>
                    </div>
                  `;

                        if (hasMedia) {
                            setTimeout(function() {
                                document.querySelectorAll('[data-repeater-media-preview-img]').forEach(function(img) {
                                    if (img.dataset.resolved) return;
                                    const picker = img.closest('[data-media-picker]');
                                    const input = picker?.querySelector('[data-media-picker-input]');
                                    if (!input?.value) return;
                                    img.dataset.resolved = '1';
                                    const browseUrl = document.getElementById('mediaLibraryModal')?.dataset.browseUrl;
                                    if (!browseUrl) return;
                                    fetch(browseUrl + '?page=1&search=' + encodeURIComponent(input.value), {
                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                    })
                                    .then(function(res) { return res.ok ? res.json() : null; })
                                    .then(function(json) {
                                        const item = json?.data?.items?.find(function(i) { return String(i.id) === String(input.value); });
                                        if (item?.thumbnail_url) img.src = item.thumbnail_url;
                                    })
                                    .catch(function() {});
                                });
                            }, 0);
                        }

                        return html;
                    }

                    if (fieldType === 'icon-picker') {
                        const iconsUrl =
                            {{ Js::from(Route::has('admin.icon-picker.icons') ? route('admin.icon-picker.icons') : null) }};
                        const safeValue = escapeHtml(value);
                        return `
                    <div class="space-y-2">
                      <label class="form-label">${label}</label>
                      <div
                        x-data="{
                          open: false,
                          query: '',
                          value: '${safeValue}',
                          icons: [],
                          loaded: false,
                          loading: false,
                          iconsUrl: '${iconsUrl}',
                          get filteredIcons() {
                            if (!this.query) return this.icons;
                            return this.icons.filter(i => i.toLowerCase().includes(this.query.toLowerCase()));
                          },
                          async load() {
                            if (this.loaded || this.loading || !this.iconsUrl) return;
                            this.loading = true;
                            try { const r = await fetch(this.iconsUrl); this.icons = await r.json(); this.loaded = true; }
                            finally { this.loading = false; }
                          },
                          toggle() { this.open = !this.open; if (this.open) this.load(); },
                          select(icon) {
                            this.value = icon;
                            this.open = false;
                            const inp = this.$el.querySelector('[data-repeater-prop]');
                            inp.value = icon;
                            const container = this.$el.closest('[data-repeater-field]');
                            if (container && window.__repeaterSync) window.__repeaterSync(container);
                          },
                          clear() {
                            this.value = ''; this.query = '';
                            const inp = this.$el.querySelector('[data-repeater-prop]');
                            inp.value = '';
                            const container = this.$el.closest('[data-repeater-field]');
                            if (container && window.__repeaterSync) window.__repeaterSync(container);
                          }
                        }"
                        data-icon-picker
                      >
                        <input type="hidden" data-repeater-prop="${fieldName}" value="${safeValue}">
                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px]">
                          <button type="button" @click="toggle()"
                            class="flex w-full items-center justify-between rounded-xl border border-neutral-100 bg-neutral-0 px-4 py-3 text-left transition hover:border-primary dark:border-neutral-100 dark:bg-neutral-10">
                            <span class="flex items-center gap-3">
                              <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700 dark:bg-neutral-100/40 dark:text-neutral-900">
                                <i class="ph text-xl" :class="value || 'ph-shapes'"></i>
                              </span>
                              <span class="space-y-1">
                                <span class="block text-sm font-semibold text-neutral-900 dark:text-neutral-950" x-text="value ? '{{ __('Selected icon') }}' : '{{ __('Choose an icon') }}'"></span>
                                <span class="block text-xs text-neutral-500" x-text="value || '{{ __('No icon selected yet') }}'"></span>
                              </span>
                            </span>
                            <i class="ph ph-caret-down text-sm text-neutral-400 transition" :class="{ 'rotate-180': open }"></i>
                          </button>
                          <div class="flex items-center gap-2">
                            <div class="input-group flex-1">
                              <i class="ph ph-magnifying-glass input-icon-left"></i>
                              <input type="text" x-model="query" class="input-field has-icon-left" placeholder="{{ __('Search icons') }}" />
                            </div>
                            <button type="button" @click="clear()"
                              class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-100 text-neutral-500 transition hover:border-error hover:text-error dark:border-neutral-100"
                              aria-label="{{ __('Clear icon') }}">
                              <i class="ph ph-x"></i>
                            </button>
                          </div>
                        </div>
                        <div x-show="open" x-cloak class="mt-3 rounded-2xl border border-neutral-100 bg-neutral-0 p-4 shadow-sm dark:border-neutral-100 dark:bg-neutral-10">
                          <div x-show="loading" class="flex justify-center py-8">
                            <i class="ph ph-spinner animate-spin text-2xl text-neutral-400"></i>
                          </div>
                          <div x-show="!loading" class="max-h-72 overflow-y-auto pe-1 sm:max-h-80 lg:max-h-96">
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                              <template x-for="icon in filteredIcons" :key="icon">
                                <button type="button" @click="select(icon)"
                                  class="flex flex-col items-center gap-2 rounded-xl border px-3 py-3 text-center transition"
                                  :class="value === icon ? 'border-primary bg-primary/10 text-primary' : 'border-neutral-100 text-neutral-600 hover:border-primary hover:text-primary dark:border-neutral-100 dark:text-neutral-700 dark:hover:text-primary'">
                                  <i class="ph text-2xl" :class="icon"></i>
                                  <span class="text-xs font-medium leading-tight" x-text="icon"></span>
                                </button>
                              </template>
                            </div>
                            <p x-show="filteredIcons.length === 0 && loaded" x-cloak
                              class="rounded-xl border border-dashed border-neutral-100 px-4 py-5 text-center text-sm text-neutral-500 dark:border-neutral-100">
                              {{ __('No icons matched your search.') }}
                            </p>
                          </div>
                        </div>
                        ${hintMarkup}
                      </div>
                    </div>
                  `;
                    }

                    return `
                  <div class="space-y-2">
                    <label class="form-label">${label}</label>
                    <input type="${inputType}" value="${escapeHtml(value)}" class="input-field" data-repeater-prop="${fieldName}">
                    ${hintMarkup}
                  </div>
                `;
                }

                window.__repeaterSync = sync;

                function sync(container) {
                    const hidden = container.querySelector('[data-repeater-input]');
                    const rows = Array.from(container.querySelectorAll('[data-repeater-row]')).map(function(row) {
                        const rowData = {};

                        row.querySelectorAll('[data-repeater-prop]').forEach(function(field) {
                            if (field.type === 'checkbox') {
                                rowData[field.dataset.repeaterProp] = field.checked;
                                return;
                            }

                            rowData[field.dataset.repeaterProp] = field.value ?? '';
                        });

                        return rowData;
                    }).filter(function(row) {
                        return Object.values(row).some(function(value) {
                            return String(value ?? '').trim() !== '';
                        });
                    });

                    hidden.value = JSON.stringify(rows);
                }

                function initEditorField(row, editorElement) {
                    if (!editorElement || editorElement.dataset.editorReady === '1' || typeof Quill === 'undefined') {
                        return;
                    }

                    const prop = editorElement.dataset.repeaterEditor;
                    const hiddenInput = row.querySelector('[data-repeater-editor-input="' + prop + '"]');
                    const wrapper = editorElement.closest('[data-repeater-editor-wrapper]');
                    const htmlTextarea = wrapper?.querySelector('.editor-html-source');

                    if (!hiddenInput) {
                        return;
                    }

                    editorElement.dataset.editorReady = '1';

                    const quill = new Quill(editorElement, {
                        theme: 'snow',
                        placeholder: '{{ __('Type your content here...') }}',
                        modules: {
                            toolbar: {
                                container: [
                                    [{
                                        'header': [1, 2, 3, 4, 5, 6, false]
                                    }],
                                    ['bold', 'italic', 'underline', 'strike'],
                                    [{
                                        'color': []
                                    }, {
                                        'background': []
                                    }],
                                    ['blockquote', 'code-block'],
                                    [{
                                        'list': 'ordered'
                                    }, {
                                        'list': 'bullet'
                                    }],
                                    [{
                                        'direction': 'rtl'
                                    }],
                                    [{
                                        'align': []
                                    }],
                                    ['link', 'image', 'video'],
                                    ['clean'],
                                ],
                            },
                        },
                    });

                    let htmlMode = false;

                    hiddenInput.value = quill.root.innerHTML;

                    quill.on('text-change', function() {
                        if (!htmlMode) {
                            hiddenInput.value = quill.root.innerHTML;
                            sync(row.closest('[data-repeater-field]'));
                        }
                    });

                    if (wrapper) {
                        const toolbar = wrapper.querySelector('.ql-toolbar');

                        if (toolbar && htmlTextarea) {
                            const htmlBtnGroup = document.createElement('span');
                            htmlBtnGroup.className = 'ql-formats';
                            const htmlBtn = document.createElement('button');
                            htmlBtn.type = 'button';
                            htmlBtn.className = 'ql-html-btn';
                            htmlBtn.innerHTML = '&lt;/&gt;';
                            htmlBtn.title = 'HTML Source';
                            htmlBtnGroup.appendChild(htmlBtn);
                            toolbar.appendChild(htmlBtnGroup);

                            htmlBtn.addEventListener('click', function() {
                                htmlMode = !htmlMode;
                                htmlBtn.classList.toggle('ql-active', htmlMode);

                                const editorSurface = wrapper.querySelector('.ql-editor');
                                if (htmlMode) {
                                    htmlTextarea.value = quill.root.innerHTML;
                                    htmlTextarea.classList.add('active');
                                    if (editorSurface) {
                                        editorSurface.style.display = 'none';
                                    }
                                } else {
                                    quill.root.innerHTML = htmlTextarea.value;
                                    hiddenInput.value = htmlTextarea.value;
                                    htmlTextarea.classList.remove('active');
                                    if (editorSurface) {
                                        editorSurface.style.display = '';
                                    }
                                    sync(row.closest('[data-repeater-field]'));
                                }
                            });

                            htmlTextarea.addEventListener('input', function() {
                                hiddenInput.value = htmlTextarea.value;
                                sync(row.closest('[data-repeater-field]'));
                            });
                        }
                    }
                }

                function initEditors(row) {
                    row.querySelectorAll('[data-repeater-editor]').forEach(function(editorElement) {
                        initEditorField(row, editorElement);
                    });
                }

                function addRow(container, rowData = {}) {
                    const schema = JSON.parse(container.dataset.schema || '{}');
                    const itemsRoot = container.querySelector('[data-repeater-items]');
                    const index = itemsRoot.querySelectorAll('[data-repeater-row]').length;
                    const rowKey = repeaterSequence++;
                    const row = document.createElement('div');

                    row.className = 'rounded-2xl border border-neutral-100 bg-neutral-0 p-4';
                    row.dataset.repeaterRow = '1';
                    row.innerHTML = `
                  <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-neutral-950">{{ __('Item') }} <span>${index + 1}</span></p>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-repeater-remove>
                      <i class="ph ph-trash"></i>
                      {{ __('Remove') }}
                    </button>
                  </div>
                  <div class="space-y-4">
                    ${Object.entries(schema).map(([fieldKey, fieldDef]) => renderField(fieldKey, fieldDef, rowData, index, rowKey)).join('')}
                  </div>
                `;

                    itemsRoot.appendChild(row);
                    initEditors(row);
                    sync(container);
                }

                function initRepeater(container) {
                    if (container.dataset.bound === '1') return;
                    container.dataset.bound = '1';

                    let initialItems = [];
                    try {
                        initialItems = JSON.parse(container.dataset.initialItems || '[]');
                    } catch (error) {
                        initialItems = [];
                    }

                    if (Array.isArray(initialItems) && initialItems.length) {
                        initialItems.forEach(function(item) {
                            addRow(container, item);
                        });
                    }

                    container.querySelector('[data-repeater-add]')?.addEventListener('click', function() {
                        addRow(container);
                    });

                    container.addEventListener('click', function(event) {
                        const removeBtn = event.target.closest('[data-repeater-remove]');
                        if (!removeBtn) return;

                        removeBtn.closest('[data-repeater-row]')?.remove();
                        sync(container);
                    });

                    container.addEventListener('input', function(event) {
                        if (!event.target.matches('[data-repeater-prop]')) return;
                        sync(container);
                    });
                }

                function boot() {
                    document.querySelectorAll('[data-repeater-field]').forEach(initRepeater);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })
            ();
        </script>
    @endpush
@endonce
