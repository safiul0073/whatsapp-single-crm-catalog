<x-layouts.user :title="__('Contacts')">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="heading-2">Contacts</h2>
            <p class="m-text mt-1">Manage everyone you reach on WhatsApp.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="btn-sm btn-outline"
              data-modal-open="importCsv"
            >
              <i class="ph ph-upload-simple text-base"></i>
              Import
            </button>
            <a
              href="{{ route('user.contacts.export') }}"
              class="btn-sm btn-outline"
              data-contact-export
              data-export-url="{{ route('user.contacts.export') }}"
            >
              <i class="ph ph-download-simple text-base"></i>
              Export CSV
            </a>
            <a href="{{ route('user.groups.index') }}" class="btn-sm btn-outline">
              <i class="ph ph-rows text-base"></i>
              Groups
            </a>
            <a href="{{ route('user.tags.index') }}" class="btn-sm btn-outline">
              <i class="ph ph-tag text-base"></i>
              Tags
            </a>
            <button
              type="button"
              class="btn-sm btn-primary"
              data-modal-open="editContact"
            >
              <i class="ph ph-plus text-base"></i>
              Add Contact
            </button>
          </div>
        </div>

        <div data-filter-root>
          <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <form class="relative w-full min-w-0 sm:flex-1" role="search">
              <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
              <input
                type="search"
                name="q"
                placeholder="Search by name, phone, email…"
                class="form-input input-search"
                data-filter-search
              />
            </form>

            <div class="flex flex-wrap items-center gap-3">
              <div
                data-dropdown
                data-dropdown-select
                data-select-name="tag"
                class="relative"
              >
                <button
                  type="button"
                  data-dropdown-toggle
                  class="btn-sm btn-outline"
                >
                  <i class="ph ph-funnel text-base"></i>
                  <span data-select-label>All tags</span>
                  <i class="ph ph-caret-down text-sm text-neutral-400"></i>
                </button>
                <div data-dropdown-menu class="dropdown-menu">
                  <button
                    type="button"
                    class="dropdown-item is-active"
                    data-select-option
                    data-value="all"
                  >
                    All tags
                  </button>
                  @foreach($tags as $tag)
                  <button
                    type="button"
                    class="dropdown-item"
                    data-select-option
                    data-value="{{ $tag->name }}"
                  >
                    {{ $tag->name }}
                  </button>
                  @endforeach
                </div>
              </div>

              <div
                data-dropdown
                data-dropdown-select
                data-select-name="optin"
                class="relative"
              >
                <button
                  type="button"
                  data-dropdown-toggle
                  class="btn-sm btn-outline"
                >
                  <i class="ph ph-sliders-horizontal text-base"></i>
                  <span data-select-label>All contacts</span>
                  <i class="ph ph-caret-down text-sm text-neutral-400"></i>
                </button>
                <div data-dropdown-menu class="dropdown-menu">
                  <button
                    type="button"
                    class="dropdown-item is-active"
                    data-select-option
                    data-value="all"
                  >
                    All contacts
                  </button>
                  <button
                    type="button"
                    class="dropdown-item"
                    data-select-option
                    data-value="opted-in"
                  >
                    Opted in
                  </button>
                  <button
                    type="button"
                    class="dropdown-item"
                    data-select-option
                    data-value="not-opted-in"
                  >
                    Not opted in
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div data-table class="mt-4">
            <form method="POST" action="{{ route('user.contacts.bulk.delete') }}" id="bulkForm">
              @csrf
              @method('DELETE')
              <div data-bulk-bar class="bulk-bar mb-3">
                <p class="text-sm font-semibold text-title">
                  <span data-selected-count>0</span> selected
                </p>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="btn-sm btn-outline"
                    data-modal-open="bulkTag"
                  >
                    Add tag
                  </button>
                  <button
                    type="button"
                    class="btn-sm btn-outline"
                    data-modal-open="bulkGroup"
                  >
                    Add to group
                  </button>
                  <button
                    type="submit"
                    class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                    data-confirm
                    data-confirm-title="Delete selected contacts?"
                    data-confirm-body="The selected contacts and their conversation history will be permanently deleted. This can't be undone."
                    data-confirm-label="Delete"
                    data-confirm-variant="error"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </form>

            <div class="app-card overflow-hidden">
              <div class="overflow-x-auto">
                <div data-filter-list class="list-table" style="--list-cols: 2.5rem minmax(10rem, 1.6fr) minmax(9rem, 1.2fr) minmax(12rem, 1.6fr) minmax(9rem, 1.2fr) minmax(7rem, 1fr) 7rem;">
                  <div class="list-table__head">
                    <span>
                      <input type="checkbox" data-select-all class="app-checkbox" aria-label="Select all" form="bulkForm" />
                    </span>
                    <span>Name</span>
                    <span>Phone</span>
                    <span>Email</span>
                    <span>Tags</span>
                    <span>WhatsApp opt-in</span>
                    <span class="text-right">Actions</span>
                  </div>

                  @forelse($contacts as $contact)
                  <div
                    data-filter-item
                    data-name="{{ $contact->name }} {{ $contact->phone }} {{ $contact->email }} {{ $contact->tags->pluck('name')->implode(' ') }}"
                    class="list-table__row"
                  >
                    <span>
                      <input
                        type="checkbox"
                        data-select-row
                        class="app-checkbox"
                        aria-label="Select {{ $contact->name }}"
                        name="contact_ids[]"
                        value="{{ $contact->id }}"
                        form="bulkForm"
                      />
                    </span>
                    <div class="flex min-w-0 items-center gap-3">
                      <span class="avatar">{{ strtoupper(substr($contact->name, 0, 2)) }}</span>
                      <span class="truncate font-semibold text-title">{{ $contact->name }}</span>
                    </div>
                    <span>{{ $contact->phone }}</span>
                    <span class="truncate">{{ $contact->email ?? '—' }}</span>
                    <span class="flex flex-wrap gap-1.5">
                      @forelse($contact->tags as $tag)
                      <span class="badge badge-soft" @if($tag->color) style="--badge-color: {{ $tag->color }}" @endif>{{ $tag->name }}</span>
                      @empty
                      <span class="text-neutral-400">—</span>
                      @endforelse
                    </span>
                    <span>
                      @if($contact->opt_in_status === 'subscribed')
                      <span class="badge badge-success">Opted in</span>
                      @else
                      <span class="badge badge-neutral">Not opted in</span>
                      @endif
                    </span>
                    <span class="flex justify-end gap-1">
                      <button
                        type="button"
                        class="row-action"
                        aria-label="Edit {{ $contact->name }}"
                        title="Edit"
                        data-contact="{{ json_encode([
                          'id' => $contact->id,
                          'name' => $contact->name,
                          'phone' => $contact->phone,
                          'email' => $contact->email,
                          'city' => $contact->city,
                          'country' => $contact->country,
                          'custom_fields' => $contact->custom_fields ?? [],
                          'opt_in_status' => $contact->opt_in_status?->value,
                          'tag_ids' => $contact->tags->pluck('id')->toArray(),
                          'group_ids' => $contact->groups->pluck('id')->toArray(),
                        ]) }}"
                      >
                        <i class="ph ph-pencil-simple text-lg"></i>
                      </button>
                      <form method="POST" action="{{ route('user.inbox.contacts.conversation', $contact->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="row-action" aria-label="Start chat with {{ $contact->name }}" title="Start chat">
                          <i class="ph ph-chat-circle-text text-lg"></i>
                        </button>
                      </form>
                      <button
                        type="button"
                        class="row-action"
                        aria-label="Send Telegram invite to {{ $contact->name }}"
                        title="Send Telegram invite"
                        data-telegram-invite="{{ json_encode([
                          'id' => $contact->id,
                          'name' => $contact->name,
                          'phone' => $contact->phone,
                          'email' => $contact->email,
                          'url' => route('user.telegram.contacts.invite', $contact),
                        ]) }}"
                      >
                        <i class="ph ph-paper-plane-tilt text-lg"></i>
                      </button>
                      <form method="POST" action="{{ route('user.contacts.destroy', $contact->id) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button
                          type="submit"
                          class="row-action text-error hover:bg-error/10 hover:text-error"
                          aria-label="Delete {{ $contact->name }}"
                          title="Delete"
                          data-confirm
                          data-confirm-title="Delete contact?"
                          data-confirm-body="This contact and their conversation history will be permanently deleted. This can't be undone."
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
                      <i class="ph ph-users text-2xl"></i>
                    </span>
                    <h3 class="heading-4 mt-4">No contacts yet</h3>
                    <p class="m-text mt-1 max-w-sm">
                      Add your first contact manually or import a CSV file.
                    </p>
                    <button
                      type="button"
                      class="btn-sm btn-primary mt-5"
                      data-modal-open="editContact"
                    >
                      Add Contact
                    </button>
                  </div>
                  @endforelse
                </div>
              </div>

              <div
                class="hidden flex-col items-center justify-center px-6 py-16 text-center"
                data-empty-state
                data-filter-empty
              >
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                  <i class="ph ph-magnifying-glass text-2xl"></i>
                </span>
                <h3 class="heading-4 mt-4">No contacts match</h3>
                <p class="m-text mt-1 max-w-sm">
                  Try a different search term, or add a new contact.
                </p>
                <button
                  type="button"
                  class="btn-sm btn-primary mt-5"
                  data-modal-open="editContact"
                >
                  Add Contact
                </button>
              </div>
            </div>

            @if($contacts->hasPages())
            <div class="mt-4">
              {{ $contacts->links() }}
            </div>
            @endif
          </div>
        </div>

    @push('modals')
    <div class="modal" id="editContact" data-modal
      x-data="contactForm()"
      @modal-open.window="if ($event.detail.id === 'editContact') initForm($event.detail.contact)"
    >
      <div class="modal__backdrop" data-modal-close></div>
      <div
        class="modal__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="editContactTitle"
      >
        <div class="flex items-center justify-between gap-3">
          <h3 id="editContactTitle" class="heading-4" x-text="editing ? 'Edit Contact' : 'New Contact'">Contact</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
        <form class="mt-4 space-y-4" x-ref="form" :action="action" method="POST" @submit="applyPhoneCode()">
          @csrf
          <input type="hidden" name="_method" x-model="method" />
          <input type="hidden" name="id" x-model="contactId" />
          <div>
            <div>
              <label for="contactName" class="form-label">Name <span class="text-error">*</span></label>
              <input id="contactName" name="name" type="text" required x-model="form.name" class="form-input" />
            </div>
          </div>
          <div>
            <div>
              <label for="contactPhone" class="form-label">WhatsApp number <span class="text-error">*</span></label>
              <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <select x-model="form.phone_code" class="form-input min-w-0" aria-label="Phone country code" @change="applyPhoneCode()">
                  <template x-for="country in phoneCountries" :key="country.code">
                    <option :value="country.dial" x-text="country.label"></option>
                  </template>
                </select>
                <input id="contactPhone" name="phone" type="tel" required x-model="form.phone" placeholder="+8801712345678" class="form-input min-w-0" @blur="applyPhoneCode()" />
              </div>
              <p class="form-hint">Choose a country code, then enter the full WhatsApp number.</p>
            </div>
          </div>
          <div>
            <label for="contactEmail" class="form-label">Email</label>
            <input id="contactEmail" name="email" type="email" x-model="form.email" class="form-input" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="contactCity" class="form-label">City</label>
              <input id="contactCity" name="city" type="text" x-model="form.city" class="form-input" />
            </div>
            <div>
              <label for="contactCountry" class="form-label">Country</label>
              <select id="contactCountry" name="country" x-model="form.country" class="form-input">
                <option value="">Select country…</option>
                <option value="BD">Bangladesh (+880)</option>
                <option value="US">United States (+1)</option>
                <option value="GB">United Kingdom (+44)</option>
                <option value="IN">India (+91)</option>
                <option value="PK">Pakistan (+92)</option>
                <option value="LK">Sri Lanka (+94)</option>
                <option value="NP">Nepal (+977)</option>
                <option value="MY">Malaysia (+60)</option>
                <option value="SG">Singapore (+65)</option>
                <option value="PH">Philippines (+63)</option>
                <option value="ID">Indonesia (+62)</option>
                <option value="TH">Thailand (+66)</option>
                <option value="VN">Vietnam (+84)</option>
                <option value="AE">UAE (+971)</option>
                <option value="SA">Saudi Arabia (+966)</option>
                <option value="KW">Kuwait (+965)</option>
                <option value="QA">Qatar (+974)</option>
                <option value="OM">Oman (+968)</option>
                <option value="BH">Bahrain (+973)</option>
                <option value="TR">Turkey (+90)</option>
                <option value="EG">Egypt (+20)</option>
                <option value="NG">Nigeria (+234)</option>
                <option value="KE">Kenya (+254)</option>
                <option value="ZA">South Africa (+27)</option>
                <option value="MA">Morocco (+212)</option>
                <option value="DE">Germany (+49)</option>
                <option value="FR">France (+33)</option>
                <option value="IT">Italy (+39)</option>
                <option value="ES">Spain (+34)</option>
                <option value="NL">Netherlands (+31)</option>
                <option value="SE">Sweden (+46)</option>
                <option value="NO">Norway (+47)</option>
                <option value="AU">Australia (+61)</option>
                <option value="CN">China (+86)</option>
                <option value="JP">Japan (+81)</option>
                <option value="KR">South Korea (+82)</option>
                <option value="RU">Russia (+7)</option>
                <option value="BR">Brazil (+55)</option>
                <option value="MX">Mexico (+52)</option>
                <option value="AR">Argentina (+54)</option>
                <option value="CL">Chile (+56)</option>
                <option value="CO">Colombia (+57)</option>
              </select>
            </div>
          </div>
          <section class="rounded-xl border border-neutral-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h4 class="text-sm font-semibold text-title">Custom fields</h4>
                <p class="form-hint">These values power shortcodes like @{{ website }} and @{{ custom.order_id }}.</p>
              </div>
              <button type="button" class="btn-sm btn-outline" @click="addCustomField()">
                <i class="ph ph-plus text-base"></i>
                Add field
              </button>
            </div>

            <div class="mt-3">
              <label for="contactWebsite" class="form-label">Website</label>
              <input id="contactWebsite" name="custom_fields[website]" type="text" x-model="form.custom_fields.website" placeholder="example.com" class="form-input" />
            </div>

            <div class="mt-3 space-y-2">
              <template x-for="(field, index) in form.custom_field_rows" :key="field.id">
                <div class="grid gap-2 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.2fr)_2.5rem]">
                  <input type="text" class="form-input" x-model="field.key" placeholder="order_id" />
                  <input type="text" class="form-input" :name="field.key ? `custom_fields[${field.key}]` : ''" x-model="field.value" placeholder="A-100" />
                  <button type="button" class="row-action" aria-label="Remove custom field" @click="removeCustomField(index)">
                    <i class="ph ph-trash text-base"></i>
                  </button>
                </div>
              </template>
            </div>
          </section>
          <div>
            <label class="form-label">Tags</label>
            <div class="mt-2 flex flex-wrap gap-2">
              <template x-for="tag in availableTags" :key="tag.id">
                <button
                  type="button"
                  @click="toggleTag(tag.id)"
                  class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-150"
                  :class="form.tag_ids.includes(tag.id) ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-neutral-0 border-neutral-200 text-body hover:border-neutral-300 hover:text-title'"
                >
                  <span x-show="form.tag_ids.includes(tag.id)">
                    <i class="ph ph-check text-xs"></i>
                  </span>
                  <span
                    x-show="tag.color"
                    class="inline-block h-2 w-2 rounded-full shrink-0"
                    :style="'background-color: ' + tag.color"
                  ></span>
                  <span x-text="tag.name"></span>
                </button>
              </template>
              <p x-show="availableTags.length === 0" class="text-xs text-neutral-400">No tags yet. Create one in the Tags page.</p>
            </div>
            <template x-for="tagId in form.tag_ids" :key="tagId">
              <input type="hidden" name="tag_ids[]" :value="tagId" />
            </template>
          </div>
          <div>
            <label class="form-label">Groups</label>
            <div class="mt-2 flex flex-wrap gap-2">
              <template x-for="group in availableGroups" :key="group.id">
                <button
                  type="button"
                  @click="toggleGroup(group.id)"
                  class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-150"
                  :class="form.group_ids.includes(group.id) ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-neutral-0 border-neutral-200 text-body hover:border-neutral-300 hover:text-title'"
                >
                  <span x-show="form.group_ids.includes(group.id)">
                    <i class="ph ph-check text-xs"></i>
                  </span>
                  <i class="ph ph-rows text-xs shrink-0"></i>
                  <span x-text="group.name"></span>
                </button>
              </template>
              <p x-show="availableGroups.length === 0" class="text-xs text-neutral-400">No groups yet. Create one in the Groups page.</p>
            </div>
            <template x-for="groupId in form.group_ids" :key="groupId">
              <input type="hidden" name="group_ids[]" :value="groupId" />
            </template>
          </div>
          <label class="flex items-center gap-2.5">
            <input type="checkbox" name="opt_in_status" value="subscribed" class="app-checkbox" x-bind:checked="form.opt_in_status === 'subscribed'"
              @change="form.opt_in_status = $event.target.checked ? 'subscribed' : 'unknown'" />
            <span class="text-sm text-body">Contact has opted in to WhatsApp</span>
          </label>
          <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="btn btn-primary flex-1" x-text="editing ? 'Save Changes' : 'Save Contact'">Save Contact</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal" id="telegramInvite" data-modal
      x-data="telegramInviteModal()"
      @modal-open.window="if ($event.detail.id === 'telegramInvite') init($event.detail.contact)"
    >
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="telegramInviteTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="telegramInviteTitle" class="heading-4">{{ __('Send Telegram invite') }}</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>

        <div class="mt-4 space-y-4">
          <div class="rounded-xl border border-neutral-200 bg-section p-3">
            <p class="text-sm font-semibold text-title" x-text="contact.name || '{{ __('Selected contact') }}'"></p>
            <p class="text-xs text-body">
              <span x-text="contact.phone || '{{ __('No phone') }}'"></span>
              <span class="mx-1 text-neutral-300">/</span>
              <span x-text="contact.email || '{{ __('No email') }}'"></span>
            </p>
          </div>

          <div>
            <span class="form-label">{{ __('Delivery channel') }}</span>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
              <label class="check-row cursor-pointer" :class="channel === 'copy' ? 'border-primary bg-primary/5' : ''">
                <input type="radio" value="copy" x-model="channel" class="app-radio">
                <i class="ph ph-copy text-lg text-primary"></i>
                <span>{{ __('Copy link') }}</span>
              </label>
              <label class="check-row cursor-pointer" :class="channel === 'whatsapp' ? 'border-primary bg-primary/5' : ''">
                <input type="radio" value="whatsapp" x-model="channel" class="app-radio">
                <i class="ph ph-whatsapp-logo text-lg text-success"></i>
                <span>{{ __('WhatsApp') }}</span>
              </label>
              <label class="check-row cursor-pointer" :class="channel === 'sms' ? 'border-primary bg-primary/5' : ''">
                <input type="radio" value="sms" x-model="channel" class="app-radio">
                <i class="ph ph-chat-text text-lg text-primary"></i>
                <span>{{ __('SMS') }}</span>
              </label>
              <label class="check-row cursor-pointer" :class="channel === 'email' ? 'border-primary bg-primary/5' : ''">
                <input type="radio" value="email" x-model="channel" class="app-radio">
                <i class="ph ph-envelope-simple text-lg text-primary"></i>
                <span>{{ __('Email') }}</span>
              </label>
            </div>
          </div>

          <div>
            <label for="telegramInviteMessage" class="form-label">{{ __('Message') }}</label>
            <textarea id="telegramInviteMessage" rows="5" class="form-input" x-model="message"></textarea>
            <p class="form-hint">{{ __('Use') }} <code>@{{ name }}</code> {{ __('and') }} <code>@{{ telegram_link }}</code> {{ __('placeholders.') }}</p>
          </div>

          <template x-if="inviteUrl">
            <div>
              <label class="form-label">{{ __('Contact-specific link') }}</label>
              <div class="flex gap-2">
                <input type="text" readonly class="form-input" :value="inviteUrl">
                <button type="button" class="btn-sm btn-outline shrink-0" @click="copyInvite()">
                  <i class="ph ph-copy"></i>
                  <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                </button>
              </div>
            </div>
          </template>

          <p x-show="error" class="text-sm font-medium text-error" x-text="error"></p>
          <p x-show="success" class="text-sm font-medium text-success" x-text="success"></p>

          <div class="flex items-center gap-3 pt-1">
            <button type="button" class="btn btn-primary flex-1" @click="send()" :disabled="loading">
              <span x-show="!loading" x-text="channel === 'copy' ? '{{ __('Create link') }}' : '{{ __('Send invite') }}'">{{ __('Send invite') }}</span>
              <span x-show="loading"><i class="ph ph-spinner animate-spin"></i> {{ __('Working...') }}</span>
            </button>
            <button type="button" class="btn btn-outline" data-modal-close>{{ __('Close') }}</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="bulkTag" data-modal>
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="bulkTagTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="bulkTagTitle" class="heading-4">Add tag to selected</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
        <form class="mt-4 space-y-4" method="POST" action="{{ route('user.contacts.bulk.tag') }}">
          @csrf
          <div>
            <label for="bulkTagSelect" class="form-label">Tag <span class="text-error">*</span></label>
            <select id="bulkTagSelect" name="tag_id" required class="form-input">
              <option value="">Choose a tag…</option>
              @foreach($tags as $tag)
              <option value="{{ $tag->id }}">{{ $tag->name }}</option>
              @endforeach
            </select>
          </div>
          <div id="bulkTagContactIds"></div>
          <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="btn btn-primary flex-1">Add Tag</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal" id="bulkGroup" data-modal>
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="bulkGroupTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="bulkGroupTitle" class="heading-4">Add to group</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
        <form class="mt-4 space-y-4" method="POST" action="{{ route('user.contacts.bulk.group') }}">
          @csrf
          <div>
            <label for="bulkGroupSelect" class="form-label">Group <span class="text-error">*</span></label>
            <select id="bulkGroupSelect" name="group_id" required class="form-input">
              <option value="">Choose a group…</option>
              @foreach($groups as $group)
              <option value="{{ $group->id }}">{{ $group->name }}</option>
              @endforeach
            </select>
          </div>
          <div id="bulkGroupContactIds"></div>
          <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="btn btn-primary flex-1">Add to Group</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal modal-xl" id="importCsv" data-modal
      x-data="importWizard()"
      @modal-open.window="if ($event.detail.id === 'importCsv') reset()"
    >
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="importCsvTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="importCsvTitle" class="heading-4">Import Contacts</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>

        <div class="mt-5">
          <ol class="wizard-steps">
            <li class="wizard-step" :class="{ 'is-active': step === 1, 'is-done': step > 1 }">
              <span class="wizard-step__dot" x-text="step > 1 ? '✓' : '1'">1</span>
              <span class="wizard-step__label">Upload file</span>
            </li>
            <li class="wizard-step" :class="{ 'is-active': step === 2, 'is-done': step > 2 }">
              <span class="wizard-step__dot" x-text="step > 2 ? '✓' : '2'">2</span>
              <span class="wizard-step__label">Map columns</span>
            </li>
            <li class="wizard-step" :class="{ 'is-active': step === 3 }">
              <span class="wizard-step__dot">3</span>
              <span class="wizard-step__label">Review &amp; import</span>
            </li>
          </ol>

          <form class="mt-5" @submit.prevent="submitImport">
            <section x-show="step === 1" x-cloak class="wizard-panel" :class="{ 'is-active': step === 1 }">
              <div class="upload-drop cursor-pointer rounded-2xl border-2 border-dashed border-neutral-300 p-8 text-center transition-colors hover:border-primary/60 hover:bg-primary/5"
                :class="{ 'border-primary bg-primary/5': dragging }"
                @click="$refs.fileInput.click()"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="dragging = false; handleDrop($event)"
              >
                <template x-if="!fileName">
                  <div>
                    <i class="ph ph-file-arrow-up text-3xl text-neutral-400"></i>
                    <p class="mt-2 text-sm font-semibold text-title">Drop your file here or click to browse</p>
                    <p class="mt-1 text-xs text-neutral-400">CSV, XLSX, XLS — max 10MB</p>
                  </div>
                </template>
                <template x-if="fileName">
                  <div>
                    <i class="text-3xl" :class="fileIcon()"></i>
                    <p class="mt-2 text-sm font-semibold text-title" x-text="fileName"></p>
                    <p class="mt-1 text-xs text-neutral-400" x-text="formatSize()"></p>
                  </div>
                </template>
                <input type="file" x-ref="fileInput" accept=".csv,.xlsx,.xls,.txt" class="sr-only" @change="fileSelected($event)" />
              </div>

              <div class="info-banner mt-4">
                <i class="ph ph-info text-lg text-primary"></i>
                <p class="text-sm text-body">
                  Your file needs a header row with column labels in the first row. The phone column is required for every row.
                </p>
              </div>

              <div class="mt-3 flex items-center gap-3">
                <a href="{{ asset('files/contact_import_template.csv') }}" download class="text-sm font-medium text-primary hover:underline">
                  <i class="ph ph-download-simple text-base"></i> Download sample template
                </a>
                <span class="text-xs text-neutral-400">Fill in your contacts and upload below</span>
              </div>

              <div class="mt-4 rounded-xl border border-neutral-200" x-data="{ guideOpen: false }">
                <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-title hover:bg-neutral-50" @click="guideOpen = !guideOpen">
                  <span><i class="ph ph-book-open-text mr-1.5 text-base text-neutral-400"></i> How to prepare your file</span>
                  <i class="ph text-base text-neutral-400 transition-transform duration-200" :class="guideOpen ? 'ph-caret-up' : 'ph-caret-down'"></i>
                </button>

                <div x-show="guideOpen" x-collapse>
                  <div class="border-t border-neutral-200 px-4 py-4 space-y-4">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2">Column Reference</p>
                      <div class="overflow-x-auto rounded-lg border border-neutral-200">
                        <table class="w-full text-left text-xs">
                          <thead>
                            <tr class="border-b border-neutral-200 bg-neutral-50">
                              <th class="px-3 py-2 font-semibold text-neutral-500">Column</th>
                              <th class="px-3 py-2 font-semibold text-neutral-500">Required</th>
                              <th class="px-3 py-2 font-semibold text-neutral-500">Example</th>
                              <th class="px-3 py-2 font-semibold text-neutral-500">Notes</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-neutral-100">
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">name</td>
                              <td class="px-3 py-2 text-neutral-400">No</td>
                              <td class="px-3 py-2 text-neutral-600">John Smith</td>
                              <td class="px-3 py-2 text-neutral-400">Full name of the contact</td>
                            </tr>
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">phone</td>
                              <td class="px-3 py-2"><span class="badge badge-error text-xs">Yes</span></td>
                              <td class="px-3 py-2 text-neutral-600">+12125551234</td>
                              <td class="px-3 py-2 text-neutral-400">Country code + number. Invalid phones are skipped.</td>
                            </tr>
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">email</td>
                              <td class="px-3 py-2 text-neutral-400">No</td>
                              <td class="px-3 py-2 text-neutral-600">john@example.com</td>
                              <td class="px-3 py-2 text-neutral-400"></td>
                            </tr>
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">city</td>
                              <td class="px-3 py-2 text-neutral-400">No</td>
                              <td class="px-3 py-2 text-neutral-600">New York</td>
                              <td class="px-3 py-2 text-neutral-400"></td>
                            </tr>
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">country</td>
                              <td class="px-3 py-2 text-neutral-400">No</td>
                              <td class="px-3 py-2 text-neutral-600">US</td>
                              <td class="px-3 py-2 text-neutral-400"></td>
                            </tr>
                            <tr>
                              <td class="px-3 py-2 font-mono font-medium text-title">tags</td>
                              <td class="px-3 py-2 text-neutral-400">No</td>
                              <td class="px-3 py-2 text-neutral-600">vip, lead</td>
                              <td class="px-3 py-2 text-neutral-400">Comma-separated. Created automatically if new.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div>
                      <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2">Step by step</p>
                      <ol class="space-y-2 text-sm text-body">
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">1</span>
                          <span>Download the sample template above — or create your own file with the columns listed.</span>
                        </li>
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">2</span>
                          <span>Fill in your contacts — <strong>one contact per row</strong>. The first row must be the column headers.</span>
                        </li>
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">3</span>
                          <span>Save your file as <strong>.csv</strong>, <strong>.xlsx</strong>, or <strong>.xls</strong> (max 10MB).</span>
                        </li>
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">4</span>
                          <span>Upload the file in the drop zone above — drag &amp; drop or click to browse.</span>
                        </li>
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">5</span>
                          <span>On the next step, <strong>map each file column</strong> to a contact field (name, phone, email, etc). Unmapped columns are skipped.</span>
                        </li>
                        <li class="flex gap-2">
                          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">6</span>
                          <span>Review the summary — confirm the new vs skipped counts, then click <strong>Import</strong>.</span>
                        </li>
                      </ol>
                    </div>

                    <div class="rounded-lg border border-warning/30 bg-warning/5 p-3">
                      <p class="text-xs font-semibold text-warning-dark mb-1"><i class="ph ph-warning mr-1 text-xs"></i> Phone format matters</p>
                      <p class="text-xs text-body">
                        Numbers must include the country code with a <code class="rounded bg-neutral-200 px-1 text-xs">+</code> prefix.<br>
                        <span class="text-success">+12125551234</span>
                        <span class="mx-2 text-neutral-300">|</span>
                        <span class="text-error line-through">2125551234</span>
                        <span class="mx-2 text-neutral-300">|</span>
                        <span class="text-error line-through">0012125551234</span>
                        <br>Rows with invalid or missing phone numbers will be skipped.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div x-show="sheets.length > 1" class="mt-4">
                <label class="form-label">Choose a sheet</label>
                <select x-model="selectedSheet" class="form-input">
                  <template x-for="s in sheets" :key="s">
                    <option :value="s" x-text="s"></option>
                  </template>
                </select>
                <p class="mt-1 text-xs text-neutral-400">This file contains multiple sheets. Select the one to import.</p>
              </div>

              <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-primary" @click="uploadFile" :disabled="!file || uploading">
                  <span x-show="!uploading">Continue</span>
                  <span x-show="uploading" class="flex items-center gap-1.5">
                    <i class="ph ph-spinner animate-spin"></i> Reading file…
                  </span>
                </button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
              </div>
              <p x-show="error" class="mt-2 text-sm text-error" x-text="error"></p>
            </section>

            <section x-show="step === 2" x-cloak class="wizard-panel" :class="{ 'is-active': step === 2 }">
              <p class="form-hint">Match each column in your file to a contact field. Unmapped columns are skipped.</p>

              <div class="mt-4 overflow-x-auto">
                <div class="list-table" style="--list-cols: minmax(9rem, 1fr) minmax(11rem, 1.1fr) minmax(10rem, 1.3fr);">
                  <div class="list-table__head">
                    <span>CSV column</span>
                    <span>Sample value</span>
                    <span>Maps to</span>
                  </div>

                  <template x-for="(col, index) in columns" :key="index">
                    <div class="list-table__row">
                      <span class="font-semibold text-title" x-text="col.name"></span>
                      <span class="truncate text-xs text-neutral-400" x-text="col.sample"></span>
                      <span>
                        <select :name="'map['+col.name+']'" x-model="col.map" class="form-input form-input-sm">
                          <option value="name">Full name</option>
                          <option value="phone">Phone number</option>
                          <option value="email">Email</option>
                          <option value="city">City</option>
                          <option value="country">Country</option>
                          <option value="tag">Add as tag</option>
                          <option value="">Skip column</option>
                        </select>
                      </span>
                    </div>
                  </template>
                </div>
              </div>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="flex items-center gap-2.5">
                  <input type="checkbox" x-model="updateExisting" class="app-checkbox" checked />
                  <span class="text-sm text-body">Update existing contacts on phone match</span>
                </label>
                <label class="flex items-center gap-2.5">
                  <input type="checkbox" x-model="markOptin" class="app-checkbox" />
                  <span class="text-sm text-body">Mark all as opted-in to WhatsApp</span>
                </label>
              </div>

              <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-primary" @click="step = 3">Continue</button>
                <button type="button" class="btn btn-outline" @click="step = 1">Back</button>
              </div>
            </section>

            <section x-show="step === 3" x-cloak class="wizard-panel" :class="{ 'is-active': step === 3 }">
              <p class="form-hint">Confirm the summary, then start the import.</p>

              <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 p-4">
                  <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">New</p>
                  <p class="mt-1.5 font-title text-2xl font-extrabold text-success" x-text="result.valid_rows || 0"></p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-4">
                  <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Skipped</p>
                  <p class="mt-1.5 font-title text-2xl font-extrabold text-error" x-text="result.invalid_rows || 0"></p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-4">
                  <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Total</p>
                  <p class="mt-1.5 font-title text-2xl font-extrabold text-title" x-text="result.total_rows || 0"></p>
                </div>
              </div>

              <div class="info-banner mt-4" x-show="result.invalid_rows > 0">
                <i class="ph ph-warning text-lg text-warning"></i>
                <p class="text-sm text-body">
                  <span class="font-semibold text-title" x-text="result.invalid_rows"></span> rows have an invalid or missing phone number and will be skipped.
                </p>
              </div>

              <p class="form-hint mt-5">Preview of the first rows:</p>
              <div class="mt-3 overflow-x-auto">
                <div class="list-table" style="--list-cols: minmax(9rem, 1.4fr) minmax(9rem, 1.1fr) minmax(10rem, 1.4fr) minmax(8rem, 1fr);">
                  <div class="list-table__head">
                    <span>Name</span>
                    <span>Phone</span>
                    <span>Email</span>
                    <span>Tags</span>
                  </div>
                  <template x-for="(row, index) in result.preview" :key="index">
                    <div class="list-table__row">
                      <span class="truncate font-semibold text-title" x-text="row.name || '—'"></span>
                      <span class="text-xs" x-text="row.phone || '—'"></span>
                      <span class="truncate text-xs" x-text="row.email || '—'"></span>
                      <span x-show="row.tags.length">
                        <template x-for="tag in row.tags" :key="tag">
                          <span class="badge badge-soft mr-1" x-text="tag"></span>
                        </template>
                      </span>
                      <span x-show="!row.tags.length" class="text-xs text-neutral-400">—</span>
                    </div>
                  </template>
                </div>
              </div>

              <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary" :disabled="importing">
                  <i class="ph ph-upload-simple text-base"></i>
                  <span x-show="!importing" x-text="'Import ' + result.valid_rows + ' contacts'">Import</span>
                  <span x-show="importing" class="flex items-center gap-1.5">
                    <i class="ph ph-spinner animate-spin"></i> Importing…
                  </span>
                </button>
                <button type="button" class="btn btn-outline" @click="step = 2">Back</button>
              </div>
              <p x-show="importError" class="mt-2 text-sm text-error" x-text="importError"></p>
            </section>
          </form>
        </div>

        <div class="mt-6 border-t border-neutral-200 pt-5" x-data="{ openHistory: false }">
          <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-title" @click="openHistory = !openHistory; if (openHistory) loadHistory()">
            <span><i class="ph ph-clock-counter-clockwise mr-1.5 text-base text-neutral-400"></i> Past imports</span>
            <i class="ph text-base text-neutral-400 transition-transform duration-200" :class="openHistory ? 'ph-caret-up' : 'ph-caret-down'"></i>
          </button>

          <div x-show="openHistory" x-collapse class="mt-3">
            <template x-if="historyLoading">
              <p class="py-4 text-center text-sm text-neutral-400">
                <i class="ph ph-spinner animate-spin mr-1"></i> Loading…
              </p>
            </template>
            <template x-if="!historyLoading && imports.length === 0">
              <p class="py-4 text-center text-sm text-neutral-400">No past imports yet.</p>
            </template>
            <template x-if="imports.length > 0">
              <div class="overflow-x-auto rounded-lg border border-neutral-200">
                <table class="w-full text-left text-sm">
                  <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50">
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">File</th>
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">Date</th>
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">Status</th>
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">Created</th>
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">Updated</th>
                      <th class="px-3 py-2 text-xs font-semibold uppercase text-neutral-500">Failed</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template x-for="imp in imports" :key="imp.id">
                      <tr class="border-b border-neutral-100 last:border-0">
                        <td class="px-3 py-2 font-medium text-title" x-text="imp.file_name"></td>
                        <td class="px-3 py-2 text-neutral-400" x-text="imp.created_at_diff"></td>
                        <td class="px-3 py-2">
                          <span class="badge text-xs"
                            :class="{
                              'badge-neutral': imp.status === 'pending',
                              'badge-info': imp.status === 'processing',
                              'badge-success': imp.status === 'completed',
                              'badge-error': imp.status === 'failed',
                            }"
                            x-text="imp.status"
                          ></span>
                        </td>
                        <td class="px-3 py-2" x-text="imp.created_rows || 0"></td>
                        <td class="px-3 py-2" x-text="imp.updated_rows || 0"></td>
                        <td class="px-3 py-2" x-text="imp.failed_rows || 0"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
    @endpush

    @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-filter-root]');
        const exportLink = document.querySelector('[data-contact-export]');

        if (!root || !exportLink) return;

        const searchInput = root.querySelector('[data-filter-search]');
        const baseUrl = exportLink.dataset.exportUrl || exportLink.href;

        const selectedValue = (name) => {
          const active = root.querySelector(`[data-dropdown-select][data-select-name="${name}"] [data-select-option].is-active`);
          return active?.dataset.value || 'all';
        };

        const updateExportUrl = () => {
          const url = new URL(baseUrl, window.location.origin);
          const query = (searchInput?.value || '').trim();
          const tag = selectedValue('tag');
          const optin = selectedValue('optin');

          if (query) url.searchParams.set('q', query);
          if (tag && tag !== 'all') url.searchParams.set('tag', tag);
          if (optin && optin !== 'all') url.searchParams.set('optin', optin);

          exportLink.href = url.toString();
        };

        searchInput?.addEventListener('input', updateExportUrl);
        root.querySelectorAll('[data-select-option]').forEach((option) => {
          option.addEventListener('click', () => setTimeout(updateExportUrl, 0));
        });

        updateExportUrl();
      });

      document.addEventListener('alpine:init', () => {
        Alpine.data('contactForm', () => ({
          editing: false,
          contactId: '',
          method: 'POST',
          action: '{{ route("user.contacts.store") }}',
          availableTags: @json($tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])),
          availableGroups: @json($groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])),
          phoneCountries: [
            { code: 'BD', dial: '+880', label: 'BD +880' },
            { code: 'US', dial: '+1', label: 'US +1' },
            { code: 'GB', dial: '+44', label: 'GB +44' },
            { code: 'IN', dial: '+91', label: 'IN +91' },
            { code: 'PK', dial: '+92', label: 'PK +92' },
            { code: 'LK', dial: '+94', label: 'LK +94' },
            { code: 'NP', dial: '+977', label: 'NP +977' },
            { code: 'MY', dial: '+60', label: 'MY +60' },
            { code: 'SG', dial: '+65', label: 'SG +65' },
            { code: 'AE', dial: '+971', label: 'AE +971' },
            { code: 'SA', dial: '+966', label: 'SA +966' },
          ],
          form: {
            name: '',
            phone: '',
            phone_code: '+880',
            email: '',
            city: '',
            country: '',
            custom_fields: { website: '' },
            custom_field_rows: [],
            opt_in_status: 'subscribed',
            tag_ids: [],
            group_ids: [],
          },
          applyPhoneCode() {
            const code = this.form.phone_code || '';
            const phone = String(this.form.phone || '').trim();

            if (!code || phone === '') return;
            if (phone.startsWith('+')) {
              this.form.phone_code = this.inferPhoneCode(phone);
              return;
            }

            this.form.phone = `${code}${phone.replace(/^0+/, '')}`;
          },
          inferPhoneCode(phone) {
            const match = [...this.phoneCountries]
              .sort((a, b) => b.dial.length - a.dial.length)
              .find((country) => String(phone || '').startsWith(country.dial));

            return match?.dial || this.form.phone_code || '+880';
          },
          customRows(fields = {}) {
            return Object.entries(fields || {})
              .filter(([key]) => key !== 'website')
              .map(([key, value]) => ({
                id: crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                key,
                value: value || '',
              }));
          },
          addCustomField() {
            this.form.custom_field_rows.push({
              id: crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`,
              key: '',
              value: '',
            });
          },
          removeCustomField(index) {
            this.form.custom_field_rows.splice(index, 1);
          },
          toggleTag(id) {
            const idx = this.form.tag_ids.indexOf(id);
            if (idx === -1) {
              this.form.tag_ids.push(id);
            } else {
              this.form.tag_ids.splice(idx, 1);
            }
          },
          toggleGroup(id) {
            const idx = this.form.group_ids.indexOf(id);
            if (idx === -1) {
              this.form.group_ids.push(id);
            } else {
              this.form.group_ids.splice(idx, 1);
            }
          },
          initForm(contact) {
            if (contact) {
              this.editing = true;
              this.contactId = contact.id;
              this.method = 'PUT';
              this.action = '{{ url("dashboard/contacts") }}/' + contact.id;
              const customFields = contact.custom_fields || {};
              this.form = {
                name: contact.name || '',
                phone: contact.phone || '',
                phone_code: this.inferPhoneCode(contact.phone || ''),
                email: contact.email || '',
                city: contact.city || '',
                country: contact.country || '',
                custom_fields: { website: customFields.website || '' },
                custom_field_rows: this.customRows(customFields),
                opt_in_status: contact.opt_in_status || 'unknown',
                tag_ids: contact.tag_ids || [],
                group_ids: contact.group_ids || [],
              };
            } else {
              this.reset();
            }
          },
          reset() {
            this.editing = false;
            this.contactId = '';
            this.method = 'POST';
            this.action = '{{ route("user.contacts.store") }}';
            this.form = {
              name: '',
              phone: '',
              phone_code: '+880',
              email: '',
              city: '',
              country: '',
              custom_fields: { website: '' },
              custom_field_rows: [],
              opt_in_status: 'subscribed',
              tag_ids: [],
              group_ids: [],
            };
          }
        }));

        Alpine.data('telegramInviteModal', () => ({
          contact: {},
          channel: 'copy',
          message: 'Hi @{{ name }}, click this link to connect with us on Telegram: @{{ telegram_link }}',
          inviteUrl: '',
          loading: false,
          copied: false,
          error: '',
          success: '',

          init(contact) {
            this.contact = contact || {};
            this.channel = 'copy';
            this.message = 'Hi @{{ name }}, click this link to connect with us on Telegram: @{{ telegram_link }}';
            this.inviteUrl = '';
            this.loading = false;
            this.copied = false;
            this.error = '';
            this.success = '';
          },

          async send() {
            if (!this.contact.url || this.loading) return;

            this.loading = true;
            this.error = '';
            this.success = '';

            try {
              const response = await fetch(this.contact.url, {
                method: 'POST',
                headers: {
                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                },
                body: JSON.stringify({
                  channel: this.channel,
                  message: this.message,
                }),
              });

              const data = await response.json().catch(() => ({}));

              if (!response.ok || data.ok === false) {
                throw new Error(data.error || data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Telegram invite failed.');
              }

              this.inviteUrl = data.invite_url || '';
              this.success = this.channel === 'copy' ? 'Invite link is ready to copy.' : 'Telegram invite sent.';

              if (this.channel === 'copy') {
                await this.copyInvite();
              }
            } catch (e) {
              this.error = e.message || 'Telegram invite failed.';
            } finally {
              this.loading = false;
            }
          },

          async copyInvite() {
            if (!this.inviteUrl) return;

            await navigator.clipboard?.writeText(this.inviteUrl);
            this.copied = true;
            setTimeout(() => this.copied = false, 1800);
          },
        }));

        Alpine.data('importWizard', () => ({
          step: 1,
          file: null,
          fileName: '',
          fileSize: 0,
          dragging: false,
          uploading: false,
          error: '',
          columns: [],
          result: {},
          selectedSheet: '',
          sheets: [],
          updateExisting: true,
          markOptin: false,
          importing: false,
          importError: '',
          imports: [],
          historyLoading: false,

          reset() {
            this.step = 1;
            this.file = null;
            this.fileName = '';
            this.fileSize = 0;
            this.dragging = false;
            this.uploading = false;
            this.error = '';
            this.columns = [];
            this.result = {};
            this.selectedSheet = '';
            this.sheets = [];
            this.updateExisting = true;
            this.markOptin = false;
            this.importing = false;
            this.importError = '';
          },

          fileSelected(event) {
            const f = event.target.files[0];
            if (f) this.setFile(f);
          },

          handleDrop(event) {
            const f = event.dataTransfer.files[0];
            if (f) this.setFile(f);
          },

          setFile(f) {
            this.error = '';
            const ext = '.' + f.name.split('.').pop().toLowerCase();
            const allowed = ['.csv', '.xlsx', '.xls', '.txt'];

            if (!allowed.includes(ext)) {
              this.error = 'Unsupported file type. Please use CSV, XLSX, or XLS files.';
              return;
            }

            if (f.size > 10 * 1024 * 1024) {
              this.error = 'File size must be under 10MB.';
              return;
            }

            this.file = f;
            this.fileName = f.name;
            this.fileSize = f.size;

            if (['.xlsx', '.xls'].includes(ext)) {
              this.fetchSheets(f);
            }
          },

          fileIcon() {
            if (!this.fileName) return 'ph-file';
            const ext = this.fileName.split('.').pop().toLowerCase();
            if (['xlsx', 'xls'].includes(ext)) return 'ph ph-file-xls text-green-600';
            return 'ph ph-file-csv text-primary';
          },

          formatSize() {
            if (!this.fileSize) return '';
            const kb = this.fileSize / 1024;
            return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.round(kb) + ' KB';
          },

          async fetchSheets(f) {
            const formData = new FormData();
            formData.append('file', f);

            try {
              const response = await fetch('{{ route("user.imports.sheets") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
              });
              if (response.ok) {
                const data = await response.json();
                this.sheets = data.sheets || [];
                if (this.sheets.length > 0) this.selectedSheet = this.sheets[0];
              }
            } catch (e) {
              this.sheets = [];
            }
          },

          async uploadFile() {
            if (!this.file) return;
            this.uploading = true;
            this.error = '';

            const formData = new FormData();
            formData.append('file', this.file);
            formData.append('update_existing', this.updateExisting ? '1' : '0');
            formData.append('mark_optin', this.markOptin ? '1' : '0');

            if (this.selectedSheet && this.sheets.length > 1) {
              formData.append('sheet', this.selectedSheet);
            }

            try {
              const response = await fetch('{{ route("user.imports.upload") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
              });

              if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.message || 'Failed to read file. Check the format and try again.');
              }

              this.result = await response.json();

              if (this.result.sheets && this.result.sheets.length > 1 && !this.selectedSheet) {
                this.sheets = this.result.sheets;
                this.selectedSheet = this.result.sheets[0];
                this.uploading = false;
                return;
              }

              this.columns = this.result.columns || [];

              this.step = 2;
            } catch (e) {
              this.error = e.message || 'Failed to upload file.';
            } finally {
              this.uploading = false;
            }
          },

          async submitImport() {
            this.importing = true;
            this.importError = '';

            try {
              const processUrl = '{{ route("user.imports.process", ["import" => "__IMPORT_ID__"]) }}'.replace('__IMPORT_ID__', this.result.import.id);
              const response = await fetch(processUrl, {
                method: 'POST',
                headers: {
                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                },
                body: JSON.stringify({ column_mapping: this.getMapping() }),
              });

              if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.message || 'Import failed');
              }

              location.reload();
            } catch (e) {
              this.importError = e.message || 'Failed to start import.';
            } finally {
              this.importing = false;
            }
          },

          getMapping() {
            const map = {};
            this.columns.forEach(col => { if (col.map) map[col.name] = col.map; });
            return map;
          },

          async loadHistory() {
            this.historyLoading = true;
            this.imports = [];

            try {
              const response = await fetch('{{ route("user.imports.history") }}', {
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
              });

              if (response.ok) {
                this.imports = await response.json();
              }
            } catch (e) {
              this.imports = [];
            } finally {
              this.historyLoading = false;
            }
          },
        }));
      });

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

      document.addEventListener('click', function(e) {
        const addBtn = e.target.closest('[data-modal-open="editContact"]');
        if (addBtn && !addBtn.hasAttribute('data-contact')) {
          window.dispatchEvent(new CustomEvent('modal-open', { detail: { id: 'editContact' } }));
        }
      }, true);

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-contact]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        try {
          const contact = JSON.parse(btn.getAttribute('data-contact'));
          window.dispatchEvent(new CustomEvent('modal-open', { detail: { id: 'editContact', contact } }));
          openInlineModal('editContact');
        } catch (e) {}
      });

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-telegram-invite]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        try {
          const contact = JSON.parse(btn.getAttribute('data-telegram-invite'));
          window.dispatchEvent(new CustomEvent('modal-open', { detail: { id: 'telegramInvite', contact } }));
          openInlineModal('telegramInvite');
        } catch (e) {}
      });

      document.querySelectorAll('[data-modal-open="bulkTag"], [data-modal-open="bulkGroup"]').forEach(btn => {
        btn.addEventListener('click', function() {
          const checked = document.querySelectorAll('[data-select-row]:checked');
          const container = document.getElementById(this.dataset.modalOpen === 'bulkTag' ? 'bulkTagContactIds' : 'bulkGroupContactIds');
          if (container) {
            container.innerHTML = '';
            checked.forEach(cb => {
              const input = document.createElement('input');
              input.type = 'hidden';
              input.name = 'contact_ids[]';
              input.value = cb.closest('[data-select-row]') ? cb.value : (cb.closest('[data-select-row]') || cb).value;
              container.appendChild(input);
            });
          }
        });
      });
    </script>
    @endpush
</x-layouts.user>
