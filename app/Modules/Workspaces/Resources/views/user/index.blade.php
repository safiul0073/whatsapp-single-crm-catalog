<x-layouts.user :title="__('Workspaces')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Workspaces') }}</h2>
            <p class="m-text mt-1">
                {{ __('Switch between workspaces or create a new one.') }}
            </p>
        </div>
        <button type="button" class="btn-sm btn-primary" data-modal-open="newWorkspace">
            <i class="ph ph-plus text-base"></i>
            {{ __('Create workspace') }}
        </button>
    </div>

    @if ($currentWorkspace)
        <section class="mt-6">
            <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">
                {{ __('Current workspace') }}
            </p>
            <article class="app-card mt-3 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-primary/10 font-title text-lg font-extrabold text-primary">
                            {{ strtoupper(substr($currentWorkspace->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-title text-lg font-bold text-title">
                                    {{ $currentWorkspace->name }}
                                </h3>
                                @if ($currentWorkspace->status->value === 'active')
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ $currentWorkspace->status->label() }}</span>
                                @endif
                                <span class="badge badge-deep">{{ $currentWorkspace->viewer_role === 'owner' ? __('Owner') : ucfirst($currentWorkspace->viewer_role) }}</span>
                            </div>
                            <p class="m-text mt-1">
                                {{ request()->getHost() }}/{{ $currentWorkspace->slug }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($currentWorkspace->viewer_role === 'owner')
                            <button type="button" class="btn-sm btn-outline" data-modal-open="editWorkspace"
                                data-id="{{ $currentWorkspace->id }}"
                                data-name="{{ $currentWorkspace->name }}"
                                data-timezone="{{ $currentWorkspace->timezone }}">
                                {{ __('Edit') }}
                            </button>
                        @endif
                        <a href="{{ route('user.workspaces.team') }}" class="btn-sm btn-outline">{{ __('Team') }}</a>
                    </div>
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-neutral-100 pt-5 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-neutral-400">{{ __('Members') }}</dt>
                        <dd class="mt-0.5 font-semibold text-title">{{ $currentWorkspace->active_members_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">{{ __('Slug') }}</dt>
                        <dd class="mt-0.5 font-semibold text-title">{{ $currentWorkspace->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">{{ __('Timezone') }}</dt>
                        <dd class="mt-0.5 font-semibold text-title">{{ $currentWorkspace->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">{{ __('Your role') }}</dt>
                        <dd class="mt-0.5 font-semibold text-title">{{ $currentWorkspace->viewer_role === 'owner' ? __('Owner') : ucfirst($currentWorkspace->viewer_role) }}</dd>
                    </div>
                </dl>
            </article>
        </section>
    @endif

    <section class="mt-6">
        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">
            {{ __('All workspaces') }}
        </p>

        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($ownedWorkspaces as $workspace)
                @php
                    $isCurrent = $currentWorkspace && $currentWorkspace->id === $workspace->id;
                    $isSuspended = $workspace->status->value === 'suspended';
                    $initials = strtoupper(substr($workspace->name, 0, 2));
                @endphp
                <article class="app-card flex flex-col p-5 {{ $isSuspended ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-section font-title font-extrabold text-primary">
                            {{ $initials }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-title text-base font-bold text-title">
                                {{ $workspace->name }}
                            </h3>
                            <p class="truncate text-xs text-neutral-400">
                                {{ request()->getHost() }}/{{ $workspace->slug }}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <span class="badge badge-deep">{{ __('Owner') }}</span>
                            <form method="POST" action="{{ route('user.workspaces.toggle-status', $workspace) }}">
                                @csrf
                                @method('PATCH')
                                <x-forms.switch
                                    :checked="$workspace->status->value === 'active'"
                                    :title="$workspace->status->value === 'active' ? __('Deactivate') : __('Activate')"
                                    submit-on-change
                                />
                            </form>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-neutral-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Members') }}</dt>
                            <dd class="font-semibold text-title">{{ $workspace->active_members_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Timezone') }}</dt>
                            <dd class="font-semibold text-title">{{ $workspace->timezone }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center gap-2">
                        @if (! $isCurrent && $workspace->status->value === 'active')
                            <form method="POST" action="{{ route('user.workspaces.switch', $workspace) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="btn-sm btn-primary w-full">
                                    {{ __('Switch') }}
                                </button>
                            </form>
                        @elseif ($isCurrent)
                            <span class="btn-sm btn-outline flex-1 text-center cursor-default">{{ __('Current') }}</span>
                        @elseif($isSuspended)
                            <span class="btn-sm btn-outline flex-1 text-center cursor-default opacity-50">{{ __('Suspended') }}</span>
                        @endif

                        @if ($workspace->viewer_role === 'owner')
                            <button type="button" class="btn-sm btn-outline text-neutral-500 hover:text-neutral-700" data-modal-open="editWorkspace"
                                data-id="{{ $workspace->id }}"
                                data-name="{{ $workspace->name }}"
                                data-timezone="{{ $workspace->timezone }}"
                                title="{{ __('Edit details') }}">
                                <i class="ph ph-pencil-simple text-base"></i>
                            </button>
                        @endif

                        @if ($workspace->can_delete)
                            <form method="POST" action="{{ route('user.workspaces.destroy', $workspace) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-sm btn-outline text-error hover:bg-error/10"
                                    data-confirm
                                    data-confirm-title="{{ __('Delete workspace?') }}"
                                    data-confirm-body="{{ __('This action cannot be undone. The workspace :name and all its data will be permanently deleted.', ['name' => $workspace->name]) }}"
                                    data-confirm-label="{{ __('Delete') }}"
                                    data-confirm-variant="error"
                                    title="{{ __('Delete workspace') }}">
                                    <i class="ph ph-trash text-base"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                @if ($memberWorkspaces->isEmpty() && $invitations->isEmpty())
                    <div class="col-span-full flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-300 p-10 text-center">
                        <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph ph-buildings text-2xl"></i>
                        </span>
                        <h3 class="heading-4 mt-4">{{ __('No workspaces yet') }}</h3>
                        <p class="m-text mt-1 max-w-sm">{{ __('Create your first workspace to get started managing your business communications.') }}</p>
                        <button type="button" class="btn-sm btn-primary mt-4" data-modal-open="newWorkspace">
                            <i class="ph ph-plus text-base"></i>
                            {{ __('Create workspace') }}
                        </button>
                    </div>
                @endif
            @endforelse

            @foreach ($memberWorkspaces as $workspace)
                @php
                    $isCurrent = $currentWorkspace && $currentWorkspace->id === $workspace->id;
                    $isSuspended = $workspace->status->value === 'suspended';
                    $initials = strtoupper(substr($workspace->name, 0, 2));
                    $roleLabel = $workspace->viewer_role === 'administrator' ? __('Admin') : ucfirst($workspace->viewer_role);
                    $roleClass = match ($workspace->viewer_role) {
                        'administrator' => 'badge-info',
                        'manager' => 'badge-info',
                        default => 'badge-neutral',
                    };
                @endphp
                <article class="app-card flex flex-col p-5 {{ $isSuspended ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-section font-title font-extrabold text-primary">
                            {{ $initials }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-title text-base font-bold text-title">
                                {{ $workspace->name }}
                            </h3>
                            <p class="truncate text-xs text-neutral-400">
                                {{ request()->getHost() }}/{{ $workspace->slug }}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            @if ($isSuspended)
                                <span class="badge badge-warning">{{ __('Suspended') }}</span>
                            @endif
                            <span class="badge {{ $roleClass }}">{{ $roleLabel }}</span>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-neutral-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Members') }}</dt>
                            <dd class="font-semibold text-title">{{ $workspace->active_members_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Timezone') }}</dt>
                            <dd class="font-semibold text-title">{{ $workspace->timezone }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center gap-2">
                        @if (! $isCurrent && $workspace->status->value === 'active')
                            <form method="POST" action="{{ route('user.workspaces.switch', $workspace) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="btn-sm btn-primary w-full">
                                    {{ __('Switch') }}
                                </button>
                            </form>
                        @elseif ($isCurrent)
                            <span class="btn-sm btn-outline flex-1 text-center cursor-default">{{ __('Current') }}</span>
                        @elseif($isSuspended)
                            <span class="btn-sm btn-outline flex-1 text-center cursor-default opacity-50">{{ __('Suspended') }}</span>
                        @endif

                        <form method="POST" action="{{ route('user.workspaces.leave', $workspace) }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline text-error hover:bg-error/10"
                                data-confirm
                                data-confirm-title="{{ __('Leave workspace?') }}"
                                data-confirm-body="{{ __('You will lose access to :name. An admin would need to re-invite you.', ['name' => $workspace->name]) }}"
                                data-confirm-label="{{ __('Leave') }}"
                                data-confirm-variant="error"
                                title="{{ __('Leave Workspace') }}">
                                <i class="ph ph-sign-out text-base"></i>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach

            <button type="button" data-modal-open="newWorkspace"
                class="flex min-h-[12rem] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-neutral-300 p-5 text-center text-neutral-400 transition-colors hover:border-primary/50 hover:text-primary">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-primary">
                    <i class="ph ph-plus text-xl"></i>
                </span>
                <span class="mt-1 text-sm font-semibold text-title">{{ __('Create workspace') }}</span>
                <span class="text-xs">{{ __('Start fresh for another brand or location') }}</span>
            </button>
        </div>
    </section>

    @if ($invitations->isNotEmpty())
        <section class="app-card mt-6 overflow-hidden">
            <div class="border-b border-neutral-100 p-5">
                <h3 class="heading-4">{{ __('Pending invitations') }}</h3>
                <p class="form-hint mt-0.5">
                    {{ __('Workspaces that have invited you to join.') }}
                </p>
            </div>
            @foreach ($invitations as $invitation)
                <div class="flex flex-wrap items-center justify-between gap-3 p-5 {{ $loop->last ? '' : 'border-b border-neutral-100' }}">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-section font-title font-extrabold text-primary">
                            {{ strtoupper(substr($invitation->workspace->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-title">{{ $invitation->workspace->name }}</p>
                            <p class="text-xs text-neutral-400">
                                {{ __('Invited as :role', ['role' => $invitation->role->label()]) }}
                                                @if ($invitation->inviter)
                                                    {{ __('by :name', ['name' => $invitation->inviter->name]) }}
                                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('user.workspaces.invitations.accept', $invitation) }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-primary">
                                {{ __('Accept') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.workspaces.invitations.decline', $invitation) }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                                data-confirm
                                data-confirm-title="{{ __('Decline invitation?') }}"
                                data-confirm-body="{{ __('The invitation from :name will be declined.', ['name' => $invitation->workspace->name]) }}"
                                data-confirm-label="{{ __('Decline') }}"
                                data-confirm-variant="error">
                                {{ __('Decline') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @push('modals')
        <div class="modal" id="newWorkspace" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="newWorkspaceTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="newWorkspaceTitle" class="heading-4">{{ __('Create workspace') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('user.workspaces.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="wsNewName" class="form-label">{{ __('Workspace name') }} <span class="text-error">*</span></label>
                        <input id="wsNewName" name="name" type="text" required placeholder="{{ __('e.g. Downtown Roasters') }}" class="form-input" value="{{ old('name') }}" />
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="wsNewSlug" class="form-label">{{ __('Workspace URL') }} <span class="text-error">*</span></label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-neutral-400">{{ request()->getHost() }}/</span>
                            <input id="wsNewSlug" name="slug" type="text" required placeholder="downtown" class="form-input" value="{{ old('slug') }}" />
                        </div>
                        @error('slug')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="wsNewTimezone" class="form-label">{{ __('Timezone') }}</label>
                        <select id="wsNewTimezone" name="timezone" class="form-input">
                            <option value="">{{ __('Default') }}</option>
                            @foreach (timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', config('app.timezone')) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary flex-1">
                            {{ __('Create Workspace') }}
                        </button>
                        <button type="button" class="btn btn-outline" data-modal-close>
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="editWorkspace" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="editWorkspaceTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="editWorkspaceTitle" class="heading-4">{{ __('Edit workspace') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                <form id="editWorkspaceForm" method="POST" action="" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="wsEditName" class="form-label">{{ __('Workspace name') }} <span class="text-error">*</span></label>
                        <input id="wsEditName" name="name" type="text" required class="form-input" />
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="wsEditTimezone" class="form-label">{{ __('Timezone') }}</label>
                        <select id="wsEditTimezone" name="timezone" class="form-input">
                            <option value="">{{ __('Default') }}</option>
                            @foreach (timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary flex-1">
                            {{ __('Save Changes') }}
                        </button>
                        <button type="button" class="btn btn-outline" data-modal-close>
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const editButtons = document.querySelectorAll('[data-modal-open="editWorkspace"]');
                const editForm = document.getElementById('editWorkspaceForm');
                const nameInput = document.getElementById('wsEditName');
                const timezoneInput = document.getElementById('wsEditTimezone');

                editButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        const id = button.dataset.id;
                        editForm.action = '{{ url('dashboard/workspaces') }}/' + id;
                        nameInput.value = button.dataset.name || '';
                        if (timezoneInput) {
                            timezoneInput.value = button.dataset.timezone || '';
                        }
                    });
                });
            });
        </script>
    @endpush
</x-layouts.user>
