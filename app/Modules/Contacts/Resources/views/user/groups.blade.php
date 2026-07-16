<x-layouts.user :title="__('Groups')">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="heading-2">Contact Groups</h2>
            <p class="m-text mt-1">Build manual or rule-based audiences from your contacts.</p>
          </div>
          <button type="button" class="btn-sm btn-primary" data-modal-open="newGroup">
            <i class="ph ph-plus text-base"></i>
            New Group
          </button>
        </div>

        <div data-table class="mt-6">
          <div class="app-card overflow-hidden">
            <div class="overflow-x-auto">
              <div class="list-table" style="--list-cols: minmax(12rem, 2fr) minmax(7rem, .8fr) minmax(8rem, .8fr) minmax(14rem, 2fr) minmax(7rem, .8fr) 9rem;">
                <div class="list-table__head">
                  <span>Group</span>
                  <span>Type</span>
                  <span>Contacts</span>
                  <span>Definition</span>
                  <span>Updated</span>
                  <span class="text-right">Actions</span>
                </div>

                @forelse($groups as $group)
                  @php
                    $groupPayload = [
                      'id' => $group->id,
                      'name' => $group->name,
                      'description' => $group->description,
                      'type' => $group->type,
                      'rules' => $group->rules ?: [],
                      'contact_ids' => $group->contacts->pluck('id')->all(),
                    ];
                  @endphp
                  <div class="list-table__row" data-group-row>
                    <div class="flex min-w-0 items-center gap-3">
                      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $group->type === 'dynamic' ? 'bg-info/10 text-info' : 'bg-primary/10 text-primary' }}">
                        <i class="ph {{ $group->type === 'dynamic' ? 'ph-arrows-clockwise' : 'ph-users-three' }} text-base"></i>
                      </span>
                      <span class="truncate font-semibold text-title">{{ $group->name }}</span>
                    </div>
                    <span><span class="badge {{ $group->type === 'dynamic' ? 'badge-info' : 'badge-neutral' }}">{{ $group->type === 'dynamic' ? 'Dynamic' : 'Static' }}</span></span>
                    <span class="font-semibold text-title">{{ number_format($group->contacts_count ?? 0) }}</span>
                    <span class="truncate text-body">{{ $group->definition }}</span>
                    <span class="text-body">{{ $group->updated_at?->diffForHumans() }}</span>
                    <span class="flex justify-end gap-1">
                      <a href="{{ route('user.groups.preview', $group->id) }}" class="row-action" aria-label="Preview contacts in {{ $group->name }}" title="Preview contacts">
                        <i class="ph ph-eye text-lg"></i>
                      </a>
                      <button
                        type="button"
                        class="row-action"
                        aria-label="Edit {{ $group->name }}"
                        title="Edit"
                        data-group='@json($groupPayload)'
                      >
                        <i class="ph ph-pencil-simple text-lg"></i>
                      </button>
                      <form method="POST" action="{{ route('user.groups.duplicate', $group->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="row-action" aria-label="Duplicate {{ $group->name }}" title="Duplicate">
                          <i class="ph ph-copy text-lg"></i>
                        </button>
                      </form>
                      <form method="POST" action="{{ route('user.groups.destroy', $group->id) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button
                          type="submit"
                          class="row-action text-error hover:bg-error/10 hover:text-error"
                          aria-label="Delete {{ $group->name }}"
                          title="Delete"
                          data-confirm
                          data-confirm-title="Delete group?"
                          data-confirm-body="This group will be permanently deleted. Contacts inside the group will not be deleted."
                          data-confirm-label="Delete"
                          data-confirm-variant="error"
                        >
                          <i class="ph ph-trash text-lg"></i>
                        </button>
                      </form>
                    </span>
                  </div>
                @empty
                  <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                      <i class="ph ph-users-three text-2xl"></i>
                    </span>
                    <h3 class="heading-4 mt-4">No groups yet</h3>
                    <p class="m-text mt-1 max-w-sm">Create a static VIP list or a dynamic rule-based group.</p>
                    <button type="button" class="btn-sm btn-primary mt-5" data-modal-open="newGroup">New Group</button>
                  </div>
                @endforelse
              </div>
            </div>
          </div>

          @if($groups->hasPages())
            <div class="mt-4">{{ $groups->links() }}</div>
          @endif
        </div>

    @push('modals')
      @php
        $fieldOptions = collect($ruleFields)->mapWithKeys(fn ($field) => [$field => str($field)->replace('_', ' ')->title()]);
        $operatorOptions = collect($ruleOperators)->mapWithKeys(fn ($operator) => [$operator => str($operator)->replace('_', ' ')]);
      @endphp

      <div class="modal modal-xl" id="newGroup" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="newGroupTitle">
          <div class="flex items-center justify-between gap-3">
            <h3 id="newGroupTitle" class="heading-4">New Group</h3>
            <button type="button" class="row-action" data-modal-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>
          <form class="mt-4 space-y-4" method="POST" action="{{ route('user.groups.store') }}">
            @csrf
            @include('contacts::user.partials.group-form', [
              'formId' => 'new',
              'contacts' => $contacts,
              'fieldOptions' => $fieldOptions,
              'operatorOptions' => $operatorOptions,
            ])
            <div class="flex items-center gap-3 pt-1">
              <button type="submit" class="btn btn-primary flex-1">Create Group</button>
              <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal modal-xl" id="editGroup" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="editGroupTitle">
          <div class="flex items-center justify-between gap-3">
            <h3 id="editGroupTitle" class="heading-4">Edit Group</h3>
            <button type="button" class="row-action" data-modal-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>
          <form class="mt-4 space-y-4" method="POST" id="editGroupForm">
            @csrf
            @method('PUT')
            @include('contacts::user.partials.group-form', [
              'formId' => 'edit',
              'contacts' => $contacts,
              'fieldOptions' => $fieldOptions,
              'operatorOptions' => $operatorOptions,
            ])
            <div class="flex items-center gap-3 pt-1">
              <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
              <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            </div>
          </form>
        </div>
      </div>
    @endpush

    @push('scripts')
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          const nullOperators = new Set(['is_null', 'is_not_null']);

          function setupBuilder(root) {
            if (!root) return;

            const staticSection = root.querySelector('[data-mode-section="static"]');
            const dynamicSection = root.querySelector('[data-mode-section="dynamic"]');
            const typeInputs = Array.from(root.querySelectorAll('[data-group-type]'));
            const typeTabs = Array.from(root.querySelectorAll('[data-group-type-tabs] .seg-tab'));
            const contactSearch = root.querySelector('[data-contact-search]');
            const contactOptions = Array.from(root.querySelectorAll('[data-contact-option]'));
            const contactCheckboxes = Array.from(root.querySelectorAll('[data-contact-checkbox]'));
            const selectVisible = root.querySelector('[data-select-visible]');
            const selectedCount = root.querySelector('[data-selected-count]');
            const visibleCount = root.querySelector('[data-visible-count]');
            const rulesList = root.querySelector('[data-rules-list]');
            const ruleTemplate = root.querySelector('[data-rule-template]');
            const addRuleButton = root.querySelector('[data-add-rule]');

            function updateMode() {
              const mode = typeInputs.find((input) => input.checked)?.value || 'static';
              staticSection.classList.toggle('hidden', mode !== 'static');
              dynamicSection.classList.toggle('hidden', mode !== 'dynamic');
              typeTabs.forEach((tab) => {
                const input = tab.querySelector('[data-group-type]');
                tab.classList.toggle('is-active', input.checked);
              });
            }

            function updateSelectedCount() {
              const checked = contactCheckboxes.filter((input) => input.checked).length;
              if (selectedCount) selectedCount.textContent = checked + ' selected';
            }

            function updateVisibleCount() {
              const visible = contactOptions.filter((option) => !option.classList.contains('hidden'));
              if (visibleCount) visibleCount.textContent = visible.length ? visible.length + ' visible' : 'No matches';
              if (selectVisible) {
                const visibleCheckboxes = visible.map((option) => option.querySelector('[data-contact-checkbox]')).filter(Boolean);
                selectVisible.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.every((input) => input.checked);
              }
            }

            function filterContacts() {
              const query = (contactSearch?.value || '').trim().toLowerCase();
              contactOptions.forEach((option) => {
                const haystack = option.dataset.search || '';
                option.classList.toggle('hidden', query !== '' && !haystack.includes(query));
              });
              updateVisibleCount();
            }

            function renameRuleInputs() {
              Array.from(rulesList.querySelectorAll('[data-rule-row]')).forEach((row, index) => {
                row.querySelector('[data-rule-boolean]').setAttribute('name', 'rules[' + index + '][boolean]');
                row.querySelector('[data-rule-field]').setAttribute('name', 'rules[' + index + '][field]');
                row.querySelector('[data-rule-operator]').setAttribute('name', 'rules[' + index + '][operator]');
                row.querySelector('[data-rule-value]').setAttribute('name', 'rules[' + index + '][value]');
              });
            }

            function buildSummary(row) {
              const boolean = row.querySelector('[data-rule-boolean]')?.value || 'and';
              const field = row.querySelector('[data-rule-field]')?.value || '';
              const operator = row.querySelector('[data-rule-operator]')?.value || '=';
              const value = row.querySelector('[data-rule-value]')?.value || '';
              const summaryJoin = row.querySelector('[data-rule-summary-join]');
              const summaryText = row.querySelector('[data-rule-summary]');

              if (summaryJoin) {
                summaryJoin.textContent = boolean.toUpperCase();
                summaryJoin.className = 'badge shrink-0 text-xs ' + (boolean === 'or' ? 'badge-warning' : 'badge-neutral');
              }

              if (!field) {
                if (summaryText) summaryText.textContent = 'Choose a field';
                return;
              }

              const fieldLabel = field.replace(/_/g, ' ');
              const operatorLabel = operator.replace(/_/g, ' ');

              let summary;
              if (nullOperators.has(operator)) {
                summary = fieldLabel + ' ' + operatorLabel;
              } else if (value !== '') {
                summary = fieldLabel + ' ' + operatorLabel + ' "' + value + '"';
              } else {
                summary = fieldLabel + ' ' + operatorLabel + ' ...';
              }

              if (summaryText) summaryText.textContent = summary;
            }

            function collapseRule(row) {
              const body = row.querySelector('[data-rule-body]');
              const chevron = row.querySelector('[data-rule-chevron]');
              if (body) body.style.gridTemplateRows = '0fr';
              if (chevron) chevron.classList.remove('rotate-180');
            }

            function expandRule(row) {
              Array.from(rulesList.querySelectorAll('[data-rule-row]')).forEach(function (r) {
                if (r !== row) collapseRule(r);
              });
              const body = row.querySelector('[data-rule-body]');
              const chevron = row.querySelector('[data-rule-chevron]');
              if (body) body.style.gridTemplateRows = '1fr';
              if (chevron) chevron.classList.add('rotate-180');
            }

            function toggleRule(row) {
              const body = row.querySelector('[data-rule-body]');
              if (body && body.style.gridTemplateRows !== '0fr') {
                collapseRule(row);
              } else {
                expandRule(row);
              }
            }

            function syncRuleValueVisibility(row) {
              const operator = row.querySelector('[data-rule-operator]')?.value || '=';
              const valueWrap = row.querySelector('[data-rule-value-wrap]');
              const valueInput = row.querySelector('[data-rule-value]');
              const hideValue = nullOperators.has(operator);
              valueWrap.classList.toggle('hidden', hideValue);
              if (hideValue && valueInput) valueInput.value = '';
            }

            function addRule(rule = {}) {
              if (!ruleTemplate) return;
              const fragment = ruleTemplate.content.cloneNode(true);
              const row = fragment.querySelector('[data-rule-row]');
              row.querySelector('[data-rule-boolean]').value = rule.boolean || 'and';
              row.querySelector('[data-rule-field]').value = rule.field || '';
              row.querySelector('[data-rule-operator]').value = rule.operator || '=';
              row.querySelector('[data-rule-value]').value = rule.value ?? '';
              rulesList.appendChild(fragment);
              const addedRow = rulesList.lastElementChild;
              syncRuleValueVisibility(addedRow);
              collapseRule(addedRow);
              buildSummary(addedRow);
              renameRuleInputs();
            }

            function resetRules(rules = []) {
              rulesList.innerHTML = '';
              const source = rules.length ? rules : [{}];
              source.forEach((rule) => addRule(rule));
            }

            function resetBuilder() {
              root.querySelector('[data-group-name]').value = '';
              root.querySelector('[data-group-description]').value = '';
              typeInputs.forEach((input) => input.checked = input.value === 'static');
              contactCheckboxes.forEach((input) => input.checked = false);
              if (contactSearch) contactSearch.value = '';
              resetRules([]);
              filterContacts();
              updateSelectedCount();
              updateMode();
            }

            typeInputs.forEach((input) => input.addEventListener('change', updateMode));
            contactCheckboxes.forEach((input) => input.addEventListener('change', () => {
              updateSelectedCount();
              updateVisibleCount();
            }));
            if (contactSearch) contactSearch.addEventListener('input', filterContacts);
            if (selectVisible) {
              selectVisible.addEventListener('change', () => {
                contactOptions
                  .filter((option) => !option.classList.contains('hidden'))
                  .forEach((option) => {
                    const checkbox = option.querySelector('[data-contact-checkbox]');
                    if (checkbox) checkbox.checked = selectVisible.checked;
                  });
                updateSelectedCount();
                updateVisibleCount();
              });
            }
            if (addRuleButton) addRuleButton.addEventListener('click', () => addRule({}));
            rulesList.addEventListener('click', (event) => {
              const removeButton = event.target.closest('[data-remove-rule]');
              if (removeButton) {
                const rows = rulesList.querySelectorAll('[data-rule-row]');
                if (rows.length === 1) {
                  const row = rows[0];
                  row.querySelector('[data-rule-boolean]').value = 'and';
                  row.querySelector('[data-rule-field]').value = '';
                  row.querySelector('[data-rule-operator]').value = '=';
                  row.querySelector('[data-rule-value]').value = '';
                  syncRuleValueVisibility(row);
                  collapseRule(row);
                  buildSummary(row);
                  return;
                }
                removeButton.closest('[data-rule-row]')?.remove();
                renameRuleInputs();
                return;
              }

              const toggle = event.target.closest('[data-rule-toggle]');
              if (toggle) {
                toggleRule(toggle.closest('[data-rule-row]'));
                return;
              }
            });
            rulesList.addEventListener('change', (event) => {
              const operatorInput = event.target.closest('[data-rule-operator]');
              if (operatorInput) {
                syncRuleValueVisibility(operatorInput.closest('[data-rule-row]'));
              }
              const row = event.target.closest('[data-rule-row]');
              if (row) buildSummary(row);
            });
            rulesList.addEventListener('input', (event) => {
              const row = event.target.closest('[data-rule-row]');
              if (row) buildSummary(row);
            });

            root.__groupBuilder = { resetBuilder, resetRules, updateMode, filterContacts, updateSelectedCount, updateVisibleCount };
            resetBuilder();
          }

          const builders = Array.from(document.querySelectorAll('[data-group-builder]'));
          builders.forEach(setupBuilder);

          function openInlineModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.remove('hidden');
            modal.style.display = 'flex';

            requestAnimationFrame(() => {
              requestAnimationFrame(() => {
                modal.classList.add('active', 'is-open');
                document.body.classList.add('overflow-hidden', 'is-locked');
                modal.querySelector('input, textarea, select, button')?.focus();
              });
            });
          }

          document.addEventListener('click', function (event) {
            const openNew = event.target.closest('[data-modal-open="newGroup"]');
            if (openNew) {
              document.querySelector('[data-group-builder="new"]')?.__groupBuilder?.resetBuilder();
            }

            const button = event.target.closest('[data-group]');
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();

            const group = JSON.parse(button.getAttribute('data-group'));
            const form = document.getElementById('editGroupForm');
            const builder = form.querySelector('[data-group-builder="edit"]')?.__groupBuilder;
            form.action = '{{ url("dashboard/groups") }}/' + group.id;
            form.querySelector('[data-group-name]').value = group.name || '';
            form.querySelector('[data-group-description]').value = group.description || '';
            form.querySelectorAll('[data-group-type]').forEach((input) => {
              input.checked = input.value === group.type;
            });
            form.querySelectorAll('[data-contact-checkbox]').forEach((input) => {
              input.checked = (group.contact_ids || []).includes(Number(input.value));
            });

            if (builder) {
              builder.resetRules(group.rules || []);
              builder.updateMode();
              builder.filterContacts();
              builder.updateSelectedCount();
              builder.updateVisibleCount();
            }

            openInlineModal('editGroup');
          });
        });
      </script>
    @endpush
</x-layouts.user>
