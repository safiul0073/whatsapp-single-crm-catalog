<div data-group-builder="{{ $formId }}" class="space-y-5">
  <div class="grid gap-4 lg:grid-cols-[1.2fr_.8fr]">
    <div>
      <label class="form-label">Group name <span class="text-error">*</span></label>
      <input
        name="name"
        type="text"
        required
        placeholder="e.g. VIP Customers"
        class="form-input"
        data-group-name
      />
    </div>
    <div>
      <label class="form-label">Group type</label>
      <div class="mt-2 inline-flex w-full rounded-full border border-neutral-200 bg-neutral-0 p-1" data-group-type-tabs>
        <label class="seg-tab is-active flex-1 cursor-pointer text-center">
          <input type="radio" name="type" value="static" checked class="sr-only" data-group-type />
          Static
        </label>
        <label class="seg-tab flex-1 cursor-pointer text-center">
          <input type="radio" name="type" value="dynamic" class="sr-only" data-group-type />
          Dynamic
        </label>
      </div>
    </div>
  </div>

  <div>
    <label class="form-label">Description <span class="font-normal text-body">(optional)</span></label>
    <input
      name="description"
      type="text"
      placeholder="Short internal note so your team knows why this group exists"
      class="form-input"
      data-group-description
    />
  </div>

  <section data-mode-section="static" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-title">Choose contacts</p>
        <p class="text-xs text-neutral-400">Pick one or many contacts for this manual group.</p>
      </div>
      <span class="badge badge-neutral" data-selected-count>0 selected</span>
    </div>

    <div class="relative">
      <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
      <input
        type="search"
        class="form-input input-search"
        placeholder="Search contacts by name, phone, or email"
        data-contact-search
      />
    </div>

    <div class="rounded-xl border border-neutral-200">
      <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3">
        <label class="flex items-center gap-2 text-sm font-semibold text-title">
          <input type="checkbox" class="app-checkbox" data-select-visible />
          Select visible
        </label>
        <span class="text-xs text-neutral-400" data-visible-count></span>
      </div>
      <div class="max-h-72 overflow-y-auto p-2" data-contact-list>
        @forelse($contacts as $contact)
          <label
            class="check-row mb-2 flex items-start gap-3 rounded-xl border border-transparent px-3 py-3 last:mb-0"
            data-contact-option
            data-search="{{ strtolower(trim(($contact->name ?: '').' '.$contact->phone.' '.($contact->email ?: ''))) }}"
          >
            <input
              type="checkbox"
              name="contact_ids[]"
              value="{{ $contact->id }}"
              class="app-checkbox mt-1"
              data-contact-checkbox
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold text-title">{{ $contact->name ?: 'Unnamed contact' }}</span>
              <span class="mt-0.5 block truncate text-xs text-neutral-500">{{ $contact->phone }}</span>
              <span class="mt-0.5 block truncate text-xs text-neutral-400">{{ $contact->email ?: 'No email' }}</span>
            </span>
          </label>
        @empty
          <div class="px-3 py-6 text-sm text-neutral-400">No contacts yet.</div>
        @endforelse
      </div>
    </div>
  </section>

  <section data-mode-section="dynamic" class="hidden space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-title">Build rules</p>
        <p class="text-xs text-neutral-400">This group updates itself from contact data automatically.</p>
      </div>
      <button type="button" class="btn-sm btn-outline" data-add-rule>
        <i class="ph ph-plus text-base"></i>
        Add rule
      </button>
    </div>

    <div class="space-y-3" data-rules-list></div>

    <template data-rule-template>
      <div class="rounded-xl border border-neutral-200 transition-shadow hover:shadow-sm" data-rule-row>
        <div class="flex cursor-pointer items-center gap-3 px-3 py-2.5" data-rule-toggle>
          <span class="badge shrink-0 text-xs badge-neutral" data-rule-summary-join>AND</span>
          <span class="min-w-0 flex-1 truncate text-sm font-medium text-title" data-rule-summary>Choose a field</span>
          <button type="button" class="row-action shrink-0" data-remove-rule aria-label="Remove rule">
            <i class="ph ph-trash text-lg"></i>
          </button>
          <i class="ph ph-caret-down shrink-0 text-base text-neutral-400 transition-transform duration-200" data-rule-chevron></i>
        </div>
        <div class="grid transition-all duration-200" style="grid-template-rows: 0fr;" data-rule-body>
          <div class="overflow-hidden">
            <div class="border-t border-neutral-200 px-3 pb-3 pt-3">
              <div class="grid gap-3 sm:grid-cols-[.7fr_1.1fr_1fr_1.2fr]">
                <div>
                  <label class="form-label">Join</label>
                  <select class="form-input" data-rule-boolean>
                    <option value="and">And</option>
                    <option value="or">Or</option>
                  </select>
                </div>
                <div>
                  <label class="form-label">Field</label>
                  <select class="form-input" data-rule-field>
                    <option value="">Choose field</option>
                    @foreach($fieldOptions as $value => $label)
                      <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="form-label">Operator</label>
                  <select class="form-input" data-rule-operator>
                    @foreach($operatorOptions as $value => $label)
                      <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div data-rule-value-wrap>
                  <label class="form-label">Value</label>
                  <input type="text" class="form-input" placeholder="Enter value" data-rule-value />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </section>
</div>
