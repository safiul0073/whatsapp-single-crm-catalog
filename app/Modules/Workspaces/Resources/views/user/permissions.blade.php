<x-layouts.user :title="__('Role permissions')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Role permissions') }}</h2>
            <p class="m-text mt-1">{{ $member->name }} · {{ $role->label() }}</p>
        </div>
        <a href="{{ route('user.workspaces.team') }}" class="btn-sm btn-outline">
            <i class="ph ph-arrow-left text-base"></i>
            {{ __('Back to team') }}
        </a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="app-card p-5 lg:sticky lg:top-24 lg:self-start">
            <div class="flex items-center gap-3">
                <span class="avatar">{{ $member->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate font-semibold text-title">{{ $member->name }}</p>
                    <p class="truncate text-xs text-neutral-400">{{ $member->email }}</p>
                </div>
            </div>

            <div class="mt-5 space-y-3 border-t border-neutral-100 pt-5">
                <div>
                    <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Role') }}</p>
                    <p class="mt-1 font-semibold text-title">{{ $role->label() }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Selected') }}</p>
                    <p class="mt-1 font-semibold text-title">
                        {{ trans_choice(':count permission|:count permissions', count($rolePermissions['permissions'])) }}
                    </p>
                </div>
            </div>
        </aside>

        <form method="POST" action="{{ route('user.workspaces.team.permissions.update', $member) }}" class="app-card p-5 sm:p-6">
            @csrf
            @method('PUT')

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="heading-4">{{ __('Permissions') }}</h3>
                    <p class="m-text mt-1">{{ __('Changes apply to every workspace member with this role.') }}</p>
                </div>
                <button type="submit" class="btn-sm btn-primary">
                    <i class="ph ph-floppy-disk text-base"></i>
                    {{ __('Save permissions') }}
                </button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($rolePermissions['groups'] as $group)
                    <section class="rounded-lg border border-neutral-200 p-4">
                        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ $group['label'] }}</p>
                        <div class="mt-3 grid gap-2">
                            @foreach($group['permissions'] as $permission)
                                <label class="flex items-start gap-2 rounded-lg border border-neutral-100 bg-section px-3 py-2 text-sm text-body">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission['name'] }}"
                                        class="mt-0.5 rounded border-neutral-300 text-primary focus:ring-primary"
                                        @checked(in_array($permission['name'], $rolePermissions['permissions'], true))
                                    />
                                    <span>
                                        <span class="block font-semibold text-title">{{ $permission['label'] }}</span>
                                        <span class="block text-xs text-neutral-400">{{ $permission['name'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-neutral-100 pt-5">
                <a href="{{ route('user.workspaces.team') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Save permissions') }}</button>
            </div>
        </form>
    </div>
</x-layouts.user>
