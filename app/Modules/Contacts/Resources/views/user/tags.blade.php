<x-layouts.user :title="__('Tags')">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="heading-2">Contact Tags</h2>
            <p class="m-text mt-1">Categorise contacts with tags for easy filtering and targeting.</p>
          </div>
          <button
            type="button"
            class="btn-sm btn-primary"
            data-modal-open="newTag"
          >
            <i class="ph ph-plus text-base"></i>
            New Tag
          </button>
        </div>

        <div data-table class="mt-6">
          <div class="app-card overflow-hidden">
            <div class="overflow-x-auto">
              <div class="list-table" style="--list-cols: minmax(10rem, 1fr) 5rem minmax(8rem, 1fr) 3rem;">
                <div class="list-table__head">
                  <span>Tag</span>
                  <span>Color</span>
                  <span>Contacts</span>
                  <span class="text-right">Actions</span>
                </div>

                @forelse($tags as $tag)
                <div class="list-table__row" data-tag>
                  <div class="flex min-w-0 items-center gap-3">
                    <span
                      class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white text-sm font-bold"
                      style="background-color: {{ $tag->color ?? '#6366f1' }}"
                    >
                      <i class="ph ph-tag text-base"></i>
                    </span>
                    <span class="truncate font-semibold text-title">{{ $tag->name }}</span>
                  </div>
                  <span>
                    @if($tag->color)
                    <span class="inline-block h-5 w-5 rounded-full border" style="background-color: {{ $tag->color }}"></span>
                    @else
                    <span class="text-neutral-400">—</span>
                    @endif
                  </span>
                  <span class="font-semibold text-title">{{ $tag->contacts_count }}</span>
                  <span class="flex justify-end">
                    <div data-dropdown class="relative">
                      <button type="button" data-dropdown-toggle class="row-action" aria-label="Row actions">
                        <i class="ph ph-dots-three-outline text-lg"></i>
                      </button>
                      <div data-dropdown-menu class="dropdown-menu">
                        <button
                          type="button"
                          class="dropdown-item"
                          data-modal-open="editTag"
                          data-tag="{{ json_encode(['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]) }}"
                        >
                          Edit
                        </button>
                        <a href="{{ route('user.contacts.index') }}" class="dropdown-item">View contacts</a>
                        <form method="POST" action="{{ route('user.tags.destroy', $tag->id) }}" class="inline">
                          @csrf
                          @method('DELETE')
                          <button
                            type="submit"
                            class="dropdown-item w-full text-left text-error hover:text-error"
                            data-confirm
                            data-confirm-title="Delete tag?"
                            data-confirm-body="This tag will be removed from all contacts. Campaigns targeting this tag will need to be updated."
                            data-confirm-label="Delete"
                            data-confirm-variant="error"
                          >
                            Delete
                          </button>
                        </form>
                      </div>
                    </div>
                  </span>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                  <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                    <i class="ph ph-tag text-2xl"></i>
                  </span>
                  <h3 class="heading-4 mt-4">No tags yet</h3>
                  <p class="m-text mt-1 max-w-sm">Create your first tag to categorise contacts.</p>
                  <button type="button" class="btn-sm btn-primary mt-5" data-modal-open="newTag">
                    New Tag
                  </button>
                </div>
                @endforelse
              </div>
            </div>
          </div>

          @if($tags->hasPages())
          <div class="mt-4">
            {{ $tags->links() }}
          </div>
          @endif
        </div>

    @push('modals')
    <div class="modal" id="newTag" data-modal>
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="newTagTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="newTagTitle" class="heading-4">New Tag</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
        <form class="mt-4 space-y-4" method="POST" action="{{ route('user.tags.store') }}">
          @csrf
          <div>
            <label for="tagName" class="form-label">Tag name <span class="text-error">*</span></label>
            <input id="tagName" name="name" type="text" required placeholder="e.g. VIP Customer" class="form-input" />
          </div>
          <div>
            <label for="tagColor" class="form-label">Color <span class="font-normal text-body">(optional)</span></label>
            <input id="tagColor" name="color" type="color" value="#6366f1" class="form-input h-10 w-20 p-1" />
          </div>
          <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="btn btn-primary flex-1">Create Tag</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal" id="editTag" data-modal>
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="editTagTitle">
        <div class="flex items-center justify-between gap-3">
          <h3 id="editTagTitle" class="heading-4">Edit Tag</h3>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
        <form class="mt-4 space-y-4" method="POST" id="editTagForm">
          @csrf
          @method('PUT')
          <input type="hidden" name="id" id="editTagId" />
          <div>
            <label for="editTagName" class="form-label">Tag name <span class="text-error">*</span></label>
            <input id="editTagName" name="name" type="text" required class="form-input" />
          </div>
          <div>
            <label for="editTagColor" class="form-label">Color <span class="font-normal text-body">(optional)</span></label>
            <input id="editTagColor" name="color" type="color" class="form-input h-10 w-20 p-1" />
          </div>
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
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-tag] [data-modal-open="editTag"]');
        if (!btn) return;
        try {
          const tag = JSON.parse(btn.getAttribute('data-tag'));
          document.getElementById('editTagId').value = tag.id;
          document.getElementById('editTagName').value = tag.name;
          document.getElementById('editTagColor').value = tag.color || '#6366f1';
          document.getElementById('editTagForm').action = '{{ url("dashboard/tags") }}/' + tag.id;
        } catch (e) {}
      });
    </script>
    @endpush
</x-layouts.user>
