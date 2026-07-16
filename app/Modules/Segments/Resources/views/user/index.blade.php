<x-layouts.user :title="__('Segments')">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="heading-2">Audience Segments</h2>
            <p class="m-text mt-1">Create reusable contact audiences for WhatsApp campaigns.</p>
          </div>
          <button type="button" class="btn-sm btn-primary" data-modal-open="newSegment">
            <i class="ph ph-plus text-base"></i>
            Build Segment
          </button>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="stat-card">
            <div class="f-between">
              <p class="text-sm font-semibold text-body">Total segments</p>
              <span class="stat-card__icon"><i class="ph ph-funnel-simple text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title">{{ number_format($stats['total'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-body">saved audiences</p>
          </div>
          <div class="stat-card">
            <div class="f-between">
              <p class="text-sm font-semibold text-body">Auto-updating</p>
              <span class="stat-card__icon"><i class="ph ph-arrows-clockwise text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title">{{ number_format($stats['dynamic'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-body">rule-based segments</p>
          </div>
          <div class="stat-card">
            <div class="f-between">
              <p class="text-sm font-semibold text-body">Reachable contacts</p>
              <span class="stat-card__icon"><i class="ph ph-users-three text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title">{{ number_format($stats['reach'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-body">subscribed and unblocked</p>
          </div>
          <div class="stat-card">
            <div class="f-between">
              <p class="text-sm font-semibold text-body">Largest segment</p>
              <span class="stat-card__icon"><i class="ph ph-crown-simple text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title">{{ number_format(data_get($stats, 'largest.count', 0)) }}</p>
            <p class="mt-1 truncate text-xs text-body">{{ data_get($stats, 'largest.name', 'No segment yet') }}</p>
          </div>
        </div>

        <div data-table class="mt-6">
          <div class="app-card overflow-hidden">
            <div class="overflow-x-auto">
              <div class="list-table" style="--list-cols: minmax(12rem, 2fr) minmax(7rem, 1fr) minmax(7rem, 1fr) minmax(12rem, 2fr) minmax(7rem, 1fr) 3rem;">
                <div class="list-table__head">
                  <span>Segment</span>
                  <span>Type</span>
                  <span>Contacts</span>
                  <span>Definition</span>
                  <span>Updated</span>
                  <span class="text-right">Actions</span>
                </div>

                @forelse($segments as $segment)
                  <div class="list-table__row">
                    <div class="flex min-w-0 items-center gap-3">
                      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $segment->type === 'dynamic' ? 'bg-info/10 text-info' : 'bg-primary/10 text-primary' }}">
                        <i class="ph {{ $segment->type === 'dynamic' ? 'ph-arrows-clockwise' : 'ph-list-bullets' }} text-base"></i>
                      </span>
                      <span class="truncate font-semibold text-title">{{ $segment->name }}</span>
                    </div>
                    <span><span class="badge {{ $segment->type === 'dynamic' ? 'badge-info' : 'badge-neutral' }}">{{ $segment->type === 'dynamic' ? 'Rule-based' : 'Manual' }}</span></span>
                    <span class="font-semibold text-title">{{ number_format($segment->contacts_count ?? 0) }}</span>
                    <span class="truncate text-body">{{ $segment->definition }}</span>
                    <span class="text-body">{{ $segment->updated_at?->diffForHumans() }}</span>
                    <span class="flex justify-end">
                      <div data-dropdown class="relative">
                        <button type="button" data-dropdown-toggle class="row-action" aria-label="Row actions">
                          <i class="ph ph-dots-three-outline text-lg"></i>
                        </button>
                        <div data-dropdown-menu class="dropdown-menu">
                          <a href="{{ route('user.segments.preview', $segment->id) }}" class="dropdown-item">Preview contacts</a>
                          <form method="POST" action="{{ route('user.segments.duplicate', $segment->id) }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Duplicate</button>
                          </form>
                          <form method="POST" action="{{ route('user.segments.destroy', $segment->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item text-error">Delete</button>
                          </form>
                        </div>
                      </div>
                    </span>
                  </div>
                @empty
                  <div class="px-6 py-12 text-center">
                    <h3 class="heading-4">No segments yet</h3>
                    <p class="m-text mt-1">Build your first manual or rule-based audience.</p>
                  </div>
                @endforelse
              </div>
            </div>
          </div>

          @if($segments->hasPages())
            <div class="mt-4">{{ $segments->links() }}</div>
          @endif
        </div>

    @push('modals')
      <div class="modal" id="newSegment" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="newSegmentTitle">
          <div class="flex items-center justify-between gap-3">
            <h3 id="newSegmentTitle" class="heading-4">Build Segment</h3>
            <button type="button" class="row-action" data-modal-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>
          <form class="mt-4 space-y-4" method="POST" action="{{ route('user.segments.store') }}">
            @csrf
            <div>
              <label for="segmentName" class="form-label">Segment name</label>
              <input id="segmentName" name="name" type="text" required class="form-input" placeholder="e.g. Promo opt-ins" />
            </div>
            <div>
              <label for="segmentDescription" class="form-label">Description</label>
              <input id="segmentDescription" name="description" type="text" class="form-input" placeholder="Optional note" />
            </div>
            <div>
              <span class="form-label">Type</span>
              <div class="mt-2 flex flex-wrap gap-2">
                <label class="radio-card"><input type="radio" name="type" value="dynamic" checked /><span>Rule-based</span></label>
                <label class="radio-card"><input type="radio" name="type" value="static" /><span>Manual</span></label>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Opt-in status</label>
                <select name="rules[opt_in_status]" class="form-input">
                  <option value="">Any</option>
                  <option value="subscribed">Subscribed</option>
                  <option value="unsubscribed">Unsubscribed</option>
                  <option value="unknown">Unknown</option>
                </select>
              </div>
              <div>
                <label class="form-label">Source</label>
                <select name="rules[source]" class="form-input">
                  <option value="">Any</option>
                  <option value="manual">Manual</option>
                  <option value="import">Import</option>
                  <option value="website">Website</option>
                  <option value="form">Form</option>
                </select>
              </div>
              <div>
                <label class="form-label">Country</label>
                <input name="rules[country]" type="text" maxlength="2" class="form-input" placeholder="US" />
              </div>
              <div>
                <label class="form-label">City</label>
                <input name="rules[city]" type="text" class="form-input" placeholder="Dhaka" />
              </div>
              <div>
                <label class="form-label">Created within days</label>
                <input name="rules[created_within_days]" type="number" min="1" class="form-input" />
              </div>
              <div>
                <label class="form-label">Last interaction before days</label>
                <input name="rules[last_interaction_before_days]" type="number" min="1" class="form-input" />
              </div>
              <div>
                <label class="form-label">Tags</label>
                <select name="rules[tag_ids][]" multiple class="form-input">
                  @foreach($tags as $tag)
                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="form-label">Groups</label>
                <select name="rules[group_ids][]" multiple class="form-input">
                  @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div>
              <label class="form-label">Manual contacts</label>
              <select name="contact_ids[]" multiple class="form-input">
                @foreach($contacts as $contact)
                  <option value="{{ $contact->id }}">{{ $contact->name ?: $contact->phone }} ({{ $contact->phone }})</option>
                @endforeach
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="btn-sm btn-outline" data-modal-close>Cancel</button>
              <button type="submit" class="btn-sm btn-primary">Save Segment</button>
            </div>
          </form>
        </div>
      </div>
    @endpush
</x-layouts.user>
