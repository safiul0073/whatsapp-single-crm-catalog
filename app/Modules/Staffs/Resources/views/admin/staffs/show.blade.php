<x-layouts.admin :title="__('Staff Details')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Staff Details') }}</h1>
            <div class="flex items-center gap-2">
                @can('staffs.edit')
                <x-ui.button variant="outline" href="{{ route('admin.staffs.edit', $staff) }}">
                    <i class="ph ph-pencil-simple"></i> {{ __('Edit') }}
                </x-ui.button>
                @endcan
                <x-ui.button variant="outline" href="{{ route('admin.staffs.index') }}">
                    <i class="ph ph-arrow-left"></i> {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>

        <div class="section-card">
            <div class="space-y-4 max-w-2xl">
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Avatar') }}</span>
                    <div class="mt-2">
                        @if($staff->avatar)
                            <img src="{{ Storage::url($staff->avatar) }}" alt="{{ $staff->name }}" class="h-16 w-16 rounded-full object-cover" />
                        @else
                            <div class="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
                                <span class="text-2xl font-medium text-primary">{{ strtoupper(substr($staff->name, 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Name') }}</span>
                    <p class="text-neutral-950 font-medium">{{ $staff->name }}</p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Email') }}</span>
                    <p class="text-neutral-900">{{ $staff->email }}</p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Phone') }}</span>
                    <p class="text-neutral-900">{{ $staff->phone ?? __('N/A') }}</p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Role') }}</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($staff->roles as $role)
                            <x-ui.badge variant="info">{{ $role->name }}</x-ui.badge>
                        @endforeach
                    </div>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Status') }}</span>
                    <p>
                        <x-ui.badge :variant="$staff->is_active ? 'success' : 'danger'">
                            {{ $staff->is_active ? __('Active') : __('Inactive') }}
                        </x-ui.badge>
                    </p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Last Login') }}</span>
                    <p class="text-neutral-900">{{ format_date($staff->last_login_at, true) ?: __('Never') }}</p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Created') }}</span>
                    <p class="text-neutral-900">{{ format_date($staff->created_at, true) }}</p>
                </div>
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Last Updated') }}</span>
                    <p class="text-neutral-900">{{ format_date($staff->updated_at, true) }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>