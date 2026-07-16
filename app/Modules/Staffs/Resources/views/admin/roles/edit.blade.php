<x-layouts.admin :title="__('Edit Role')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ __('Edit Role') }}</h1>
                <p class="text-xs text-neutral-500 mt-1">{{ __('Manage role name and adjust its specific permissions.') }}</p>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.roles.index') }}">
                <i class="ph ph-arrow-left text-base mr-1"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Role Details -->
            <div class="section-card">
                <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                    <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <i class="ph ph-shield-check text-lg"></i>
                    </div>
                    <h2 class="font-semibold text-neutral-800">{{ __('Role Details') }}</h2>
                </div>
                <div class="max-w-xl">
                    <x-forms.input :label="__('Role Name')" name="name" :value="$role->name" required :placeholder="__('Enter role name')" />
                </div>
            </div>

            <!-- Role Permissions -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-2">
                    <h2 class="text-sm font-bold text-neutral-400 uppercase tracking-wider">{{ __('Permissions Matrix') }}</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($permissions as $module => $modulePermissions)
                        <div class="section-card permission-group-card" x-data="{ selectAll: false }">
                            <div class="flex items-center justify-between border-b border-neutral-100 pb-3 mb-4">
                                <h3 class="text-xs font-bold text-neutral-800 uppercase tracking-wider">{{ str_replace(['-', '_'], ' ', $module) }}</h3>
                                <label class="flex items-center gap-1.5 cursor-pointer text-[10px] font-semibold text-primary select-none">
                                    <input type="checkbox" x-model="selectAll" @change="const cbs = $el.closest('.permission-group-card').querySelectorAll('.checkbox-field'); cbs.forEach(cb => { cb.checked = selectAll; cb.dispatchEvent(new Event('change')); })" class="rounded border-neutral-300 text-primary focus:ring-primary focus:ring-opacity-25" />
                                    {{ __('Select All') }}
                                </label>
                            </div>
                            <div class="space-y-3">
                                @foreach($modulePermissions as $permission)
                                    <x-forms.checkbox
                                        :label="ucfirst(str_replace($module . '.', '', $permission->name))"
                                        name="permissions[]"
                                        :value="$permission->name"
                                        :checked="$role->hasPermissionTo($permission->name)"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('permissions')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-6 border-t border-neutral-100">
                <x-forms.submit :label="__('Update Role')" />
                <x-ui.button variant="ghost" href="{{ route('admin.roles.index') }}">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>