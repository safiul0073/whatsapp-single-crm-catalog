<x-layouts.user :title="__('Team')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Team') }}</h2>
            <p class="m-text mt-1">
                {{ __('People who can access this workspace and their roles.') }}
            </p>
        </div>
        @can('team.manage')
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-sm btn-outline" data-modal-open="addMember">
                    <i class="ph ph-user-plus text-base"></i>
                    {{ __('Add member') }}
                </button>
                <button type="button" class="btn-sm btn-primary" data-modal-open="inviteMember">
                    <i class="ph ph-envelope-simple text-base"></i>
                    {{ __('Invite member') }}
                </button>
            </div>
        @elsecan('team.manage.staff_only')
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-sm btn-outline" data-modal-open="addMember">
                    <i class="ph ph-user-plus text-base"></i>
                    {{ __('Add member') }}
                </button>
                <button type="button" class="btn-sm btn-primary" data-modal-open="inviteMember">
                    <i class="ph ph-envelope-simple text-base"></i>
                    {{ __('Invite member') }}
                </button>
            </div>
        @endcan
    </div>

    {{-- KPI summary --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Members') }}</p>
                <span class="stat-card__icon"><i class="ph ph-users-three text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $counts['total'] }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('of :limit seats', ['limit' => $counts['seat_limit'] ?: __('unlimited')]) }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Administrators') }}</p>
                <span class="stat-card__icon"><i class="ph ph-shield-check text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $counts['administrators'] }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('full access') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Managers') }}</p>
                <span class="stat-card__icon"><i class="ph ph-headset text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $counts['managers'] }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('operational access') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Pending invites') }}</p>
                <span class="stat-card__icon"><i class="ph ph-envelope-simple text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $counts['pending_invites'] }}</p>
            <p class="mt-1 text-xs {{ $counts['pending_invites'] > 0 ? 'text-warning' : 'text-neutral-400' }}">
                {{ $counts['pending_invites'] > 0 ? __('awaiting acceptance') : __('no pending invites') }}
            </p>
        </div>
    </div>

    {{-- filter + search --}}
    <div data-filter-root>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="overflow-x-auto scrollbar-hide">
                <div data-range-group data-range-value="all" class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
                    <button type="button" class="range-btn is-active" data-range="all">{{ __('All') }}</button>
                    <button type="button" class="range-btn" data-range="administrator">{{ __('Administrators') }}</button>
                    <button type="button" class="range-btn" data-range="manager">{{ __('Managers') }}</button>
                    <button type="button" class="range-btn" data-range="staff">{{ __('Staff') }}</button>
                    <button type="button" class="range-btn" data-range="pending">{{ __('Pending') }}</button>
                </div>
            </div>
            <form class="relative w-full min-w-0 sm:ml-auto sm:w-64" role="search">
                <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
                <input type="search" name="q" placeholder="{{ __('Search members…') }}" class="form-input input-search" data-filter-search />
            </form>
        </div>

        <div class="app-card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <div data-filter-list class="list-table" style="--list-cols: minmax(13rem, 2fr) minmax(8rem, 1.1fr) minmax(7rem, 0.9fr) minmax(8rem, 1fr) 5rem;">
                    <div class="list-table__head">
                        <span>{{ __('Member') }}</span>
                        <span>{{ __('Role') }}</span>
                        <span>{{ __('Status') }}</span>
                        <span>{{ __('Last active') }}</span>
                        <span class="text-right">{{ __('Actions') }}</span>
                    </div>

                    @forelse ($members as $member)
                        @php
                            $isOwner = $workspace->isOwner($member);
                            $role = $member->pivot->role;
                            $status = $member->pivot->status;
                            $roleClass = match ($role->value) {
                                'administrator' => 'badge-deep',
                                'manager' => 'badge-info',
                                default => 'badge-neutral',
                            };
                        @endphp
                        <div data-filter-item data-status="{{ $role->value }}" data-name="{{ strtolower($member->name.' '.$member->email) }}" class="list-table__row">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="avatar">{{ $member->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-title">{{ $member->name }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ $member->email }}</p>
                                </div>
                            </div>
                            <span><span class="badge {{ $roleClass }}">{{ $role->label() }}</span></span>
                            <span><span class="badge badge-success">{{ $status->label() }}</span></span>
                            <span class="text-xs">{{ $member->last_login_at?->diffForHumans() ?? __('Never') }}</span>
                            <span class="flex items-center justify-end gap-1">
                                @if (! $isOwner)
                                    @can('team.manage')
                                        <a href="{{ route('user.workspaces.team.permissions', $member) }}"
                                            class="row-action"
                                            aria-label="{{ __('Edit permissions for :name', ['name' => $member->name]) }}">
                                            <i class="ph ph-shield-check text-lg"></i>
                                        </a>
                                    @endcan
                                @endif
                                @if (! $isOwner && $member->id !== $authUser->id)
                                    @canany(['team.manage', 'team.manage.staff_only'])
                                        @php
                                            $canEditThisMember = $authUser->can('team.manage')
                                                || ($authUser->can('team.manage.staff_only') && $role === App\Modules\Workspaces\Enums\WorkspaceMemberRole::Staff);
                                        @endphp
                                        @if ($canEditThisMember)
                                            <button type="button"
                                                class="row-action"
                                                data-modal-open="editMember"
                                                data-id="{{ $member->id }}"
                                                data-first-name="{{ $member->first_name }}"
                                                data-last-name="{{ $member->last_name }}"
                                                data-email="{{ $member->email }}"
                                                data-role="{{ $role->value }}"
                                                aria-label="{{ __('Edit :name', ['name' => $member->name]) }}">
                                                <i class="ph ph-pencil-simple text-lg"></i>
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('user.workspaces.team.destroy', $member) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="row-action text-error hover:text-error"
                                                data-confirm
                                                data-confirm-title="{{ __('Remove member?') }}"
                                                data-confirm-body="{{ __(':name will lose access to this workspace immediately. This can\'t be undone.', ['name' => $member->name]) }}"
                                                data-confirm-label="{{ __('Remove') }}"
                                                data-confirm-variant="error"
                                                aria-label="{{ __('Remove :name', ['name' => $member->name]) }}">
                                                <i class="ph ph-trash text-lg"></i>
                                            </button>
                                        </form>
                                    @endcanany
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="list-table__row">
                            <div class="col-span-full py-8 text-center text-sm text-neutral-500">
                                {{ __('No team members yet.') }}
                            </div>
                        </div>
                    @endforelse

                    @foreach ($invitations as $invitation)
                        <div data-filter-item data-status="pending" data-name="{{ strtolower($invitation->email) }}" class="list-table__row">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="avatar bg-neutral-100 text-neutral-400">{{ strtoupper(substr($invitation->email, 0, 2)) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-title">{{ $invitation->email }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ __('Invited :date', ['date' => $invitation->created_at->diffForHumans()]) }}</p>
                                </div>
                            </div>
                            <span><span class="badge badge-neutral">{{ $invitation->role->label() }}</span></span>
                            <span><span class="badge badge-warning">{{ __('Pending') }}</span></span>
                            <span class="text-xs text-neutral-400">—</span>
                            <span class="flex justify-end">
                                <div data-dropdown class="relative">
                                    <button type="button" data-dropdown-toggle class="row-action" aria-label="{{ __('Row actions') }}">
                                        <i class="ph ph-dots-three-outline text-lg"></i>
                                    </button>
                                    <div data-dropdown-menu class="dropdown-menu">
                                        <form method="POST" action="{{ route('user.workspaces.team.invitations.resend', $invitation) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">{{ __('Resend invite') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('user.workspaces.team.invitations.revoke', $invitation) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-error hover:text-error" data-confirm data-confirm-title="{{ __('Revoke invite?') }}" data-confirm-body="{{ __('The invitation to :email will be cancelled.', ['email' => $invitation->email]) }}" data-confirm-label="{{ __('Revoke') }}" data-confirm-variant="error">
                                                {{ __('Revoke invite') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div data-filter-empty class="hidden flex-col items-center justify-center px-6 py-16 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                    <i class="ph ph-magnifying-glass text-2xl"></i>
                </span>
                <h3 class="heading-4 mt-4">{{ __('No members match') }}</h3>
                <p class="m-text mt-1 max-w-sm">{{ __('Try a different role or search term.') }}</p>
            </div>
        </div>
    </div>

    @push('modals')
        {{-- Add member modal --}}
        <div class="modal" id="addMember" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="addMemberTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="addMemberTitle" class="heading-4">{{ $canAddMember ? __('Add member') : __('Limit Reached') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                @if ($canAddMember)
                    <form method="POST" action="{{ route('user.workspaces.team.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="addFirstName" class="form-label">{{ __('First name') }} <span class="text-error">*</span></label>
                                <input id="addFirstName" name="first_name" type="text" required class="form-input" value="{{ old('first_name') }}" />
                            </div>
                            <div>
                                <label for="addLastName" class="form-label">{{ __('Last name') }} <span class="text-error">*</span></label>
                                <input id="addLastName" name="last_name" type="text" required class="form-input" value="{{ old('last_name') }}" />
                            </div>
                        </div>
                        <div>
                            <label for="addEmail" class="form-label">{{ __('Email address') }} <span class="text-error">*</span></label>
                            <input id="addEmail" name="email" type="email" required class="form-input" value="{{ old('email') }}" />
                        </div>
                        <div>
                            <label for="addPassword" class="form-label">{{ __('Password') }} <span class="text-error">*</span></label>
                            <input id="addPassword" name="password" type="password" required class="form-input" />
                        </div>
                        <div>
                            <label for="addPasswordConfirmation" class="form-label">{{ __('Confirm password') }} <span class="text-error">*</span></label>
                            <input id="addPasswordConfirmation" name="password_confirmation" type="password" required class="form-input" />
                        </div>
                        <div>
                            <label for="addRole" class="form-label">{{ __('Role') }} <span class="text-error">*</span></label>
                            <select id="addRole" name="role" required class="form-input">
                                @can('team.manage')
                                    <option value="administrator">{{ __('Administrator') }} — {{ __('full access except billing') }}</option>
                                    <option value="manager">{{ __('Manager') }} — {{ __('operational access') }}</option>
                                @endcan
                                <option value="staff">{{ __('Staff') }} — {{ __('limited access') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <button type="submit" class="btn btn-primary flex-1">{{ __('Add Member') }}</button>
                            <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                        </div>
                    </form>
                @else
                    <div class="mt-4 flex flex-col items-center text-center p-4">
                        <img src="{{ asset('assets/images/limit_reached.png') }}" alt="{{ __('Limit Reached') }}" class="max-w-[240px] h-auto rounded-lg mb-4" />
                        <h4 class="text-lg font-semibold text-title mb-2">{{ __('Seat Limit Reached') }}</h4>
                        <p class="text-sm text-neutral-500 max-w-sm mb-6">
                            {{ __('Your workspace has reached the limit of allowed team members. Please upgrade your plan to add or invite more members.') }}
                        </p>
                        <div class="flex w-full items-center gap-3">
                            @can('subscription.manage')
                                <a href="{{ route('user.subscription.show') }}" class="btn btn-primary flex-1">{{ __('Upgrade Plan') }}</a>
                            @endcan
                            <button type="button" class="btn btn-outline flex-1" data-modal-close>{{ __('Close') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Invite member modal --}}
        <div class="modal" id="inviteMember" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="inviteMemberTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="inviteMemberTitle" class="heading-4">{{ $canAddMember ? __('Invite member') : __('Limit Reached') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                @if ($canAddMember)
                    <form method="POST" action="{{ route('user.workspaces.team.invite') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="inviteEmail" class="form-label">{{ __('Email address') }} <span class="text-error">*</span></label>
                            <input id="inviteEmail" name="email" type="email" required placeholder="name@company.com" class="form-input" />
                            <p class="form-hint">{{ __('They\'ll get an email to join this workspace.') }}</p>
                        </div>
                        <div>
                            <label for="inviteRole" class="form-label">{{ __('Role') }} <span class="text-error">*</span></label>
                            <select id="inviteRole" name="role" required class="form-input">
                                @can('team.manage')
                                    <option value="administrator">{{ __('Administrator') }} — {{ __('full access except billing') }}</option>
                                    <option value="manager">{{ __('Manager') }} — {{ __('operational access') }}</option>
                                @endcan
                                <option value="staff" selected>{{ __('Staff') }} — {{ __('limited access') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <button type="submit" class="btn btn-primary flex-1">{{ __('Send Invite') }}</button>
                            <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                        </div>
                    </form>
                @else
                    <div class="mt-4 flex flex-col items-center text-center p-4">
                        <img src="{{ asset('assets/images/limit_reached.png') }}" alt="{{ __('Limit Reached') }}" class="max-w-[240px] h-auto rounded-lg mb-4" />
                        <h4 class="text-lg font-semibold text-title mb-2">{{ __('Seat Limit Reached') }}</h4>
                        <p class="text-sm text-neutral-500 max-w-sm mb-6">
                            {{ __('Your workspace has reached the limit of allowed team members. Please upgrade your plan to add or invite more members.') }}
                        </p>
                        <div class="flex w-full items-center gap-3">
                            @can('subscription.manage')
                                <a href="{{ route('user.subscription.show') }}" class="btn btn-primary flex-1">{{ __('Upgrade Plan') }}</a>
                            @endcan
                            <button type="button" class="btn btn-outline flex-1" data-modal-close>{{ __('Close') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Edit member modal --}}
        <div class="modal" id="editMember" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="editMemberTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="editMemberTitle" class="heading-4">{{ __('Edit member') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                <form id="editMemberForm" method="POST" action="" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="editFirstName" class="form-label">{{ __('First name') }} <span class="text-error">*</span></label>
                            <input id="editFirstName" name="first_name" type="text" required class="form-input" />
                        </div>
                        <div>
                            <label for="editLastName" class="form-label">{{ __('Last name') }} <span class="text-error">*</span></label>
                            <input id="editLastName" name="last_name" type="text" required class="form-input" />
                        </div>
                    </div>
                    <div>
                        <label for="editEmail" class="form-label">{{ __('Email address') }} <span class="text-error">*</span></label>
                        <input id="editEmail" name="email" type="email" required class="form-input" />
                    </div>
                    <div>
                        <label for="editRole" class="form-label">{{ __('Role') }} <span class="text-error">*</span></label>
                        <select id="editRole" name="role" required class="form-input">
                            @can('team.manage')
                                <option value="administrator">{{ __('Administrator') }} — {{ __('full access except billing') }}</option>
                                <option value="manager">{{ __('Manager') }} — {{ __('operational access') }}</option>
                            @endcan
                            <option value="staff">{{ __('Staff') }} — {{ __('limited access') }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary flex-1">{{ __('Save Changes') }}</button>
                        <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const editButtons = document.querySelectorAll('[data-modal-open="editMember"]');
                const editForm = document.getElementById('editMemberForm');
                const firstNameInput = document.getElementById('editFirstName');
                const lastNameInput = document.getElementById('editLastName');
                const emailInput = document.getElementById('editEmail');
                const roleInput = document.getElementById('editRole');

                editButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        const id = button.dataset.id;
                        editForm.action = '{{ url('team') }}/' + id;
                        firstNameInput.value = button.dataset.firstName;
                        lastNameInput.value = button.dataset.lastName;
                        emailInput.value = button.dataset.email;
                        roleInput.value = button.dataset.role;
                    });
                });
            });
        </script>
    @endpush
</x-layouts.user>
