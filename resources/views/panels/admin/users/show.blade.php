<x-layouts.admin :title="__('User Details')">
    @push('scripts')
    <script>
        function impersonateUser(userId) {
            fetch('/admin/users/' + userId + '/impersonate', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.url) {
                    window.open(data.url, '_blank');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to start impersonation');
            });
        }
    </script>
    @endpush

    <div class="space-y-6">
        <!-- Top Profile Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-neutral-100 pb-5">
            <div class="flex items-center gap-4">
                @if($user->avatar && avatar_url($user->avatar))
                    <img src="{{ avatar_url($user->avatar) }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-2xl object-cover ring-4 ring-neutral-100" />
                @else
                    <div class="h-16 w-16 rounded-2xl bg-primary/10 flex items-center justify-center ring-4 ring-primary/20">
                        <span class="text-2xl font-bold text-primary">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    </div>
                @endif
                <div>
                    <h1 class="heading-3 text-neutral-950 font-bold">{{ $user->name }}</h1>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ $user->email }}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <x-ui.badge :variant="$user->is_active ? 'success' : 'danger'" class="text-[10px] font-semibold py-0.5 px-2">
                            {{ $user->is_active ? __('Active') : __('Inactive') }}
                        </x-ui.badge>
                        @if($user->roles->count() > 0)
                            @foreach($user->roles->take(2) as $role)
                                <x-ui.badge variant="info" class="text-[10px] font-semibold py-0.5 px-2">{{ $role->name }}</x-ui.badge>
                            @endforeach
                            @if($user->roles->count() > 2)
                                <x-ui.badge variant="neutral" class="text-[10px] font-semibold py-0.5 px-2">+{{ $user->roles->count() - 2 }}</x-ui.badge>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button variant="primary" size="sm" onclick="impersonateUser({{ $user->id }})">
                    <i class="ph ph-sign-in text-base mr-1"></i>
                    {{ __('Login as User') }}
                </x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('admin.users.edit', $user) }}">
                    <i class="ph ph-pencil-simple text-base mr-1"></i>
                    {{ __('Edit') }}
                </x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('admin.users.index') }}">
                    <i class="ph ph-arrow-left text-base mr-1"></i>
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="stat-card group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600 group-hover:scale-110 transition-transform">
                        @if($user->email_verified_at)
                            <i class="ph ph-envelope-simple-open text-xl"></i>
                        @else
                            <i class="ph ph-envelope-simple text-xl"></i>
                        @endif
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">{{ __('Email Verification') }}</p>
                    <p class="text-base font-extrabold text-neutral-900 mt-1">
                        @if($user->email_verified_at)
                            <span class="text-emerald-600">{{ __('Verified') }}</span>
                        @else
                            <span class="text-amber-500">{{ __('Unverified') }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="stat-card group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-blue-50 text-blue-600 group-hover:scale-110 transition-transform">
                        <i class="ph ph-shield-check text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">{{ __('2FA Security') }}</p>
                    <p class="text-base font-extrabold text-neutral-900 mt-1">
                        @if($user->hasTwoFactorEnabled())
                            <span class="text-blue-600">{{ __('Enabled') }}</span>
                        @else
                            <span class="text-neutral-400">{{ __('Disabled') }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="stat-card group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-violet-50 text-violet-600 group-hover:scale-110 transition-transform">
                        <i class="ph ph-buildings text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">{{ __('Workspaces') }}</p>
                    <p class="text-lg font-extrabold text-neutral-900 mt-1">
                        {{ $user->ownedWorkspaces->count() + $user->workspaces->where('owner_id', '!=', $user->id)->count() }}
                    </p>
                </div>
            </div>

            <div class="stat-card group hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600 group-hover:scale-110 transition-transform">
                        <i class="ph ph-clock text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">{{ __('Last Active') }}</p>
                    <p class="text-base font-extrabold text-neutral-900 mt-1 truncate">
                        @if($user->last_login_at)
                            {{ $user->last_login_at->diffForHumans() }}
                        @else
                            <span class="text-neutral-400 text-sm">{{ __('Never') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Details & Workspaces Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Info Panel -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Personal Information -->
                <div class="section-card space-y-4">
                    <h3 class="flex items-center gap-2 text-xs font-bold tracking-wider text-neutral-400 uppercase pb-2 border-b border-neutral-100">
                        <i class="ph ph-user-circle text-base text-primary"></i>
                        {{ __('Personal Information') }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Full Name') }}</p>
                            <p class="text-sm font-medium text-neutral-900 mt-0.5">{{ $user->name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Email Address') }}</p>
                            <p class="text-sm font-medium text-neutral-900 mt-0.5">{{ $user->email }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Phone Number') }}</p>
                            <p class="text-sm font-medium text-neutral-900 mt-0.5">{{ $user->phone ?? __('Not provided') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Member Since') }}</p>
                            <p class="text-sm font-medium text-neutral-900 mt-0.5">{{ format_date($user->created_at, true) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Security & Roles -->
                <div class="section-card space-y-4">
                    <h3 class="flex items-center gap-2 text-xs font-bold tracking-wider text-neutral-400 uppercase pb-2 border-b border-neutral-100">
                        <i class="ph ph-shield text-base text-primary"></i>
                        {{ __('Security Details') }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Email Status') }}</p>
                            <div class="mt-1 flex items-center gap-1.5">
                                @if($user->email_verified_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700">
                                        {{ __('Verified') }}
                                    </span>
                                    <span class="text-[10px] text-neutral-400">{{ format_date($user->email_verified_at) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700">
                                        {{ __('Unverified') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase">{{ __('Phone Status') }}</p>
                            <div class="mt-1 flex items-center gap-1.5">
                                @if($user->phone_verified_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700">
                                        {{ __('Verified') }}
                                    </span>
                                    <span class="text-[10px] text-neutral-400">{{ format_date($user->phone_verified_at) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700">
                                        {{ __('Unverified') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] text-neutral-400 font-semibold uppercase mb-1">{{ __('Assigned Roles') }}</p>
                            <div class="flex flex-wrap gap-1">
                                @if($user->roles->count() > 0)
                                    @foreach($user->roles as $role)
                                        <x-ui.badge variant="info" class="text-[10px] font-semibold py-0.5 px-2">{{ $role->name }}</x-ui.badge>
                                    @endforeach
                                @else
                                    <span class="text-xs text-neutral-400 italic">{{ __('No roles assigned') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Metadata -->
                <div class="section-card space-y-4">
                    <h3 class="flex items-center gap-2 text-xs font-bold tracking-wider text-neutral-400 uppercase pb-2 border-b border-neutral-100">
                        <i class="ph ph-info text-base text-primary"></i>
                        {{ __('Additional Info') }}
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between py-1 text-xs border-b border-neutral-50">
                            <span class="text-neutral-400">{{ __('User ID') }}</span>
                            <span class="font-mono font-bold text-neutral-900">#{{ $user->id }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-xs border-b border-neutral-50">
                            <span class="text-neutral-400">{{ __('Timezone') }}</span>
                            <span class="font-medium text-neutral-900">{{ $user->timezone ?? __('UTC') }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-xs border-b border-neutral-50">
                            <span class="text-neutral-400">{{ __('Last Updated') }}</span>
                            <span class="font-medium text-neutral-900">{{ format_date($user->updated_at, true) }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-xs">
                            <span class="text-neutral-400">{{ __('Deleted State') }}</span>
                            @if($user->deleted_at)
                                <span class="text-danger font-semibold">{{ __('Soft Deleted') }}</span>
                            @else
                                <span class="text-neutral-500 font-semibold">{{ __('Active') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Workspace Panel -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Associated Workspaces -->
                <div class="section-card">
                    <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                        <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                            <i class="ph ph-buildings text-lg"></i>
                        </div>
                        <h2 class="font-semibold text-neutral-800">{{ __('Associated Workspaces') }}</h2>
                    </div>

                    <div class="space-y-6">
                        <!-- Owned Workspaces -->
                        <div>
                            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="ph ph-crown text-amber-500 text-sm"></i>
                                {{ __('Owned Workspaces') }}
                            </h3>
                            @if($user->ownedWorkspaces->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-neutral-100 text-neutral-400 uppercase font-semibold">
                                                <th class="py-2">{{ __('Workspace Name') }}</th>
                                                <th class="py-2">{{ __('Status') }}</th>
                                                <th class="py-2">{{ __('Timezone') }}</th>
                                                <th class="py-2 text-right">{{ __('Created') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($user->ownedWorkspaces as $workspace)
                                                <tr class="border-b border-neutral-50 hover:bg-neutral-50/50 transition-colors">
                                                    <td class="py-3 font-semibold text-neutral-900">{{ $workspace->name }}</td>
                                                    <td class="py-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700">
                                                            {{ $workspace->status->value ?? $workspace->status }}
                                                        </span>
                                                    </td>
                                                    <td class="py-3 text-neutral-500">{{ $workspace->timezone }}</td>
                                                    <td class="py-3 text-neutral-500 text-right">{{ format_date($workspace->created_at) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-xs text-neutral-400 italic py-2">{{ __('Does not own any workspaces.') }}</p>
                            @endif
                        </div>

                        <!-- Joined Workspaces -->
                        <div>
                            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="ph ph-users-three text-neutral-400 text-sm"></i>
                                {{ __('Joined Workspaces') }}
                            </h3>
                            @php
                                $joinedWorkspaces = $user->workspaces->where('owner_id', '!=', $user->id);
                            @endphp
                            @if($joinedWorkspaces->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-neutral-100 text-neutral-400 uppercase font-semibold">
                                                <th class="py-2">{{ __('Workspace Name') }}</th>
                                                <th class="py-2">{{ __('Role') }}</th>
                                                <th class="py-2">{{ __('Status') }}</th>
                                                <th class="py-2 text-right">{{ __('Joined') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($joinedWorkspaces as $workspace)
                                                <tr class="border-b border-neutral-50 hover:bg-neutral-50/50 transition-colors">
                                                    <td class="py-3 font-semibold text-neutral-900">{{ $workspace->name }}</td>
                                                    <td class="py-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-700">
                                                            {{ $workspace->pivot->role ?? __('Member') }}
                                                        </span>
                                                    </td>
                                                    <td class="py-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-800">
                                                            {{ $workspace->pivot->status ?? __('Active') }}
                                                        </span>
                                                    </td>
                                                    <td class="py-3 text-neutral-500 text-right">{{ format_date($workspace->pivot->created_at ?? $workspace->created_at) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-xs text-neutral-400 italic py-2">{{ __('Not a member of any other workspaces.') }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="section-card space-y-4">
                    <div class="flex items-center gap-3 border-b border-neutral-100 pb-4">
                        <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                            <i class="ph ph-activity text-lg"></i>
                        </div>
                        <h2 class="font-semibold text-neutral-800">{{ __('Activity Log') }}</h2>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between py-1 text-xs border-b border-neutral-50">
                            <span class="text-neutral-400">{{ __('Last Login Time') }}</span>
                            <span class="font-medium text-neutral-900">
                                @if($user->last_login_at)
                                    {{ format_date($user->last_login_at, true) }}
                                @else
                                    <span class="text-neutral-400 italic">{{ __('Never logged in') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between py-1 text-xs border-b border-neutral-50">
                            <span class="text-neutral-400">{{ __('Last Login IP') }}</span>
                            <span class="font-mono text-neutral-900">{{ $user->last_login_ip ?? __('N/A') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
