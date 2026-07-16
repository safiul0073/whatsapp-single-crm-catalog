<x-layouts.admin :title="__('Edit Staff')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ __('Edit Staff') }}</h1>
                <p class="text-xs text-neutral-500 mt-1">{{ __('Manage profile, status, and assigned roles for :name.', ['name' => $staff->name]) }}</p>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.staffs.index') }}">
                <i class="ph ph-arrow-left text-base mr-1"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.staffs.update', $staff) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-user-circle text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Staff Profile') }}</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <x-forms.input :label="__('Name')" name="name" :value="$staff->name" required :placeholder="__('Enter full name')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Email')" name="email" type="email" :value="$staff->email" required :placeholder="__('Enter email address')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Phone')" name="phone" type="tel" :value="$staff->phone" :placeholder="__('Enter phone number')" />
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-lock text-lg"></i>
                            </div>
                            <div>
                                <h2 class="font-semibold text-neutral-800">{{ __('Password Security') }}</h2>
                                <p class="text-[10px] text-neutral-400 mt-0.5">{{ __('Leave blank to keep the current password.') }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input :label="__('New Password')" name="password" type="password" :placeholder="__('Enter new password')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Confirm New Password')" name="password_confirmation" type="password" :placeholder="__('Confirm password')" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-shield-chevron text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Roles & Status') }}</h2>
                        </div>
                        <div class="space-y-6">
                            <div class="space-y-3">
                                <label class="block text-xs font-bold text-neutral-400 uppercase tracking-wider">{{ __('Roles') }}</label>
                                <div class="space-y-2 bg-neutral-50/50 p-3 rounded-xl border border-neutral-100">
                                    @foreach($roles as $role)
                                        <x-forms.checkbox :label="$role->name" name="roles[]" :value="$role->name" :checked="$staff->hasRole($role->name)" />
                                    @endforeach
                                </div>
                                @error('roles')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-4 border-t border-neutral-100">
                                <label class="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3">{{ __('Status') }}</label>
                                <x-forms.toggle :label="__('Active')" name="is_active" :checked="$staff->is_active" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6 mt-6 border-t border-neutral-100">
                <x-forms.submit :label="__('Update Staff')" />
                <x-ui.button variant="ghost" href="{{ route('admin.staffs.index') }}">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>