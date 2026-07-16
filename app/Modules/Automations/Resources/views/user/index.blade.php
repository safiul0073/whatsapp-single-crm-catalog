<x-layouts.user :title="__('Automations')">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="heading-2">Automations</h2>
      <p class="m-text mt-1">Trigger-based workflows that message contacts automatically.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="row-action" data-drawer-trigger="automationFlowHelpDrawer" aria-label="Flow builder help" title="Flow builder help">
        <i class="ph ph-question text-lg"></i>
      </button>
      <a href="{{ route('user.automations.create') }}" class="btn-sm btn-outline">
        <i class="ph ph-plus text-base"></i>
        New Automation
      </a>
      @if($canUseAutomationAi)
        <button type="button" class="btn-sm btn-primary" data-modal-open="aiAutomationModal">
          <i class="ph ph-sparkle text-base"></i>
          Generate with AI
        </button>
      @else
        <a href="{{ route('user.subscription.show') }}" class="btn-sm btn-primary">
          <i class="ph ph-crown text-base"></i>
          Upgrade for AI
        </a>
      @endif
    </div>
  </div>

  <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card">
      <div class="f-between">
        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Active workflows</p>
        <span class="stat-card__icon"><i class="ph ph-lightning text-lg"></i></span>
      </div>
      <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $stats['active'] }}</p>
      <p class="mt-1 text-xs text-neutral-400">of {{ $stats['total'] }} total</p>
    </div>
    <div class="stat-card">
      <div class="f-between">
        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Runs</p>
        <span class="stat-card__icon"><i class="ph ph-arrows-clockwise text-lg"></i></span>
      </div>
      <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($stats['runs']) }}</p>
      <p class="mt-1 text-xs text-neutral-400">All automation runs</p>
    </div>
    <div class="stat-card">
      <div class="f-between">
        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Completed</p>
        <span class="stat-card__icon"><i class="ph ph-paper-plane-tilt text-lg"></i></span>
      </div>
      <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($stats['messages']) }}</p>
      <p class="mt-1 text-xs text-neutral-400">Completed runs</p>
    </div>
    <div class="stat-card">
      <div class="f-between">
        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Avg. completion</p>
        <span class="stat-card__icon"><i class="ph ph-check-circle text-lg"></i></span>
      </div>
      <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $stats['completion'] }}%</p>
      <p class="mt-1 text-xs text-neutral-400">Across all runs</p>
    </div>
  </div>

  <div
    class="mt-6"
    x-data="{ status: 'all', query: '' }"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <div class="overflow-x-auto scrollbar-hide">
        <div class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
          <button type="button" class="range-btn" :class="{ 'is-active': status === 'all' }" @click="status = 'all'">All</button>
          <button type="button" class="range-btn" :class="{ 'is-active': status === 'active' }" @click="status = 'active'">Active</button>
          <button type="button" class="range-btn" :class="{ 'is-active': status === 'inactive' }" @click="status = 'inactive'">Inactive</button>
        </div>
      </div>
      <form class="relative w-full min-w-0 sm:ml-auto sm:w-72" role="search" @submit.prevent>
        <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
        <input
          type="search"
          placeholder="Search automations..."
          class="form-input input-search"
          x-model.debounce.150ms="query"
        />
      </form>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      @forelse($automations as $automation)
        @php
          $cardSearch = \Illuminate\Support\Str::lower($automation->name.' '.$automation->description);
          $cardStatus = $automation->is_active ? 'active' : 'inactive';
        @endphp
        <article
          class="app-card flex flex-col p-5"
          data-search="{{ $cardSearch }}"
          x-show="(status === 'all' || status === '{{ $cardStatus }}') && (!query || $el.dataset.search.includes(query.toLowerCase()))"
        >
          <div class="flex items-start justify-between gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
              <i class="ph ph-flow-arrow text-xl"></i>
            </span>
            <form method="POST" action="{{ route('user.automations.toggle', $automation) }}">
              @csrf
              @method('PATCH')
              <x-forms.switch
                :checked="$automation->is_active"
                :title="$automation->is_active ? __('Deactivate') : __('Activate')"
                submit-on-change
              />
            </form>
          </div>

          <h3 class="mt-4 font-title text-lg font-bold text-title">{{ $automation->name }}</h3>
          <p class="m-text mt-1 line-clamp-2">
            {{ $automation->description ?: 'No description added yet.' }}
          </p>

          <div class="mt-3 flex flex-wrap gap-1.5">
            <span class="badge badge-soft"><i class="ph ph-lightning text-xs"></i>Trigger: {{ $automation->trigger_label }}</span>
            <span class="badge badge-soft">{{ $automation->step_count }} steps</span>
          </div>

          <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-neutral-100 pt-4 text-sm">
            <div>
              <dt class="text-xs text-neutral-400">Runs</dt>
              <dd class="font-semibold text-title">{{ number_format($automation->runs_count) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-neutral-400">Completion</dt>
              <dd class="font-semibold text-success">{{ $automation->completion_rate }}%</dd>
            </div>
          </dl>
          <p class="mt-2 text-xs text-neutral-400">
            Last run: {{ $automation->last_run_at?->diffForHumans() ?? 'No runs yet' }}
          </p>

          <div class="mt-4 flex items-center justify-between gap-2">
            <span class="badge {{ $automation->is_active ? 'badge-success' : 'badge-warning' }}">
              {{ $automation->is_active ? 'Active' : 'Inactive' }}
            </span>
            <div class="flex items-center gap-1.5">
              <a href="{{ route('user.automations.edit', $automation) }}" class="btn-sm btn-outline">Edit</a>
              <button
                type="button"
                class="row-action text-error"
                aria-label="Delete automation"
                data-modal-open="globalConfirmModal"
                data-confirm-title="Delete automation?"
                data-confirm-message="{{ $automation->name }} will be permanently deleted. This cannot be undone."
                data-confirm-button="Delete"
                data-confirm-action="{{ route('user.automations.destroy', $automation) }}"
                data-confirm-method="DELETE"
              >
                <i class="ph ph-trash text-lg"></i>
              </button>
            </div>
          </div>
        </article>
      @empty
        <div class="app-card col-span-full p-8 text-center">
          <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-section text-neutral-400">
            <i class="ph ph-flow-arrow text-xl"></i>
          </span>
          <h3 class="heading-4 mt-4">No automations yet</h3>
          <p class="m-text mx-auto mt-2 max-w-md">Create your first visual flow to start turning contacts, triggers, and messages into repeatable workflows.</p>
          <div class="mt-5 flex flex-wrap justify-center gap-2">
            @if($canUseAutomationAi)
              <button type="button" class="btn-sm btn-primary" data-modal-open="aiAutomationModal">
                <i class="ph ph-sparkle text-base"></i>
                Generate with AI
              </button>
            @else
              <a href="{{ route('user.subscription.show') }}" class="btn-sm btn-primary">
                <i class="ph ph-crown text-base"></i>
                Upgrade for AI
              </a>
            @endif
            <a href="{{ route('user.automations.create') }}" class="btn-sm btn-outline">
              <i class="ph ph-plus text-base"></i>
              Blank builder
            </a>
          </div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="mt-6">
    {{ $automations->links() }}
  </div>

  @if($canUseAutomationAi)
    @push('modals')
    <div class="modal" id="aiAutomationModal" data-modal>
      <div class="modal__backdrop" data-modal-close></div>
      <div class="modal__panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="aiAutomationModalTitle">
        <div class="flex items-start justify-between gap-4 border-b border-neutral-100 p-5">
          <div class="min-w-0">
            <p class="text-[10px] font-bold tracking-[0.2em] text-primary uppercase">AI flow builder</p>
            <h3 id="aiAutomationModalTitle" class="mt-1 font-title text-xl font-bold text-title">Generate an automation</h3>
            <p class="mt-1 text-sm text-neutral-500">Describe the customer journey. AI will create a draft flow you can edit before saving.</p>
          </div>
          <button type="button" class="row-action" data-modal-close aria-label="Close">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>

        <form method="GET" action="{{ route('user.automations.create') }}" class="space-y-4 p-5">
          <div class="rounded-lg border border-neutral-200 bg-section p-4">
            <p class="text-sm font-semibold text-title">Write your prompt with:</p>
            <div class="mt-3 grid gap-2 text-sm text-neutral-600 sm:grid-cols-2">
              <span><i class="ph ph-check text-primary"></i> When the flow should start</span>
              <span><i class="ph ph-check text-primary"></i> What message to send</span>
              <span><i class="ph ph-check text-primary"></i> Questions or buttons to ask</span>
              <span><i class="ph ph-check text-primary"></i> Wait times, tags, or handoff rules</span>
            </div>
          </div>

          <div>
            <label for="ai_prompt" class="form-label">Automation prompt</label>
            <textarea
              id="ai_prompt"
              name="ai_prompt"
              rows="5"
              required
              minlength="10"
              maxlength="1200"
              class="form-input"
              placeholder="Example: When a new WhatsApp lead asks about pricing, welcome them, ask their budget, show quick reply options, wait 1 day, then assign interested leads to sales."
            ></textarea>
          </div>

          <div class="rounded-lg border border-dashed border-neutral-200 bg-neutral-0 p-4">
            <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Example prompt</p>
            <p class="mt-2 text-sm text-neutral-600">Welcome new WhatsApp leads, ask what service they need, send quick reply options for Support, Sales, and Pricing, wait 1 day, then assign interested leads to the sales team and log them to Google Sheets.</p>
          </div>

          <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-100 pt-4">
            <button type="button" class="btn-sm btn-outline" data-modal-close>Cancel</button>
            <a href="{{ route('user.automations.create') }}" class="btn-sm btn-outline">Blank builder</a>
            <button type="submit" class="btn-sm btn-primary">
              <i class="ph ph-sparkle text-base"></i>
              Generate flow
            </button>
          </div>
        </form>
      </div>
    </div>
    @endpush
  @endif

  @push('drawers')
    @include('automations::user.partials.flow-help-drawer')
  @endpush
</x-layouts.user>
