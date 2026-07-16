<x-layouts.admin :title="__('Create User')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ __('Create User') }}</h1>
                <p class="text-xs text-neutral-500 mt-1">{{ __('Add a new user account to the system.') }}</p>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.users.index') }}">
                <i class="ph ph-arrow-left text-base mr-1"></i> {{ __('Back to Users') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-user text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Personal Profile') }}</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <x-forms.input :label="__('Name')" name="name" required :placeholder="__('Enter full name')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Email Address')" name="email" type="email" required :placeholder="__('Enter email address')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Phone Number')" name="phone" type="tel" :placeholder="__('Enter phone number')" />
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
                                <x-forms.input :label="__('Password')" name="password" type="password" required :placeholder="__('Enter password (min. 8 characters)')" />
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
                                <i class="ph ph-toggle-left text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Account Controls') }}</h2>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-neutral-400 block mb-2">{{ __('Status') }}</label>
                                <x-forms.toggle :label="__('Active Account')" name="is_active" :checked="true" />
                                <p class="text-xs text-neutral-400 mt-2">{{ __('Inactive users will not be able to log in or access any workspace.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6 mt-6 border-t border-neutral-100">
                <x-forms.submit :label="__('Create User')" class="btn-primary" />
                <x-ui.button variant="ghost" href="{{ route('admin.users.index') }}">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>
