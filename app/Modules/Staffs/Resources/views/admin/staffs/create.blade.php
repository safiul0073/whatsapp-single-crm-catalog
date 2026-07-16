<x-layouts.admin :title="__('Create Staff')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ __('Create Staff') }}</h1>
                <p class="text-xs text-neutral-500 mt-1">{{ __('Add a new staff member to the administration panel.') }}</p>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.staffs.index') }}">
                <i class="ph ph-arrow-left text-base mr-1"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.staffs.store') }}">
            @csrf
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-user-circle-plus text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Staff Profile') }}</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <x-forms.input :label="__('Name')" name="name" required :placeholder="__('Enter full name')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Email')" name="email" type="email" required :placeholder="__('Enter email address')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Phone')" name="phone" type="tel" :placeholder="__('Enter phone number')" />
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-lock text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Password Security') }}</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input :label="__('Password')" name="password" type="password" required :placeholder="__('Enter password')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Confirm Password')" name="password_confirmation" type="password" required :placeholder="__('Confirm password')" />
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
                                        <x-forms.checkbox :label="$role->name" name="roles[]" :value="$role->name" />
                                    @endforeach
                                </div>
                                @error('roles')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-4 border-t border-neutral-100">
                                <label class="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3">{{ __('Status') }}</label>
                                <x-forms.toggle :label="__('Active')" name="is_active" :checked="true" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6 mt-6 border-t border-neutral-100">
                <x-forms.submit :label="__('Create Staff')" />
                <x-ui.button variant="ghost" href="{{ route('admin.staffs.index') }}">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>