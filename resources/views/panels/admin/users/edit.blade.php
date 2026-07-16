<x-layouts.admin :title="__('Edit User')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ __('Edit User') }}</h1>
                <p class="text-xs text-neutral-500 mt-1">{{ __('Manage profile, status, and verification settings for :name.', ['name' => $user->name]) }}</p>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('admin.users.index') }}">
                <i class="ph ph-arrow-left text-base mr-1"></i> {{ __('Back to Users') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Profile Card -->
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-user text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Profile Information') }}</h2>
                        </div>

                        <div class="mb-6">
                            <x-media.picker :label="__('Avatar')" name="avatar" :value="$user->avatar" accept="image" :hint="__('Select an image from media library')" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <x-forms.input :label="__('Name')" name="name" :value="$user->name" required :placeholder="__('Enter full name')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Email Address')" name="email" type="email" :value="$user->email" required :placeholder="__('Enter email address')" />
                            </div>
                            <div>
                                <x-forms.input :label="__('Phone Number')" name="phone" type="tel" :value="$user->phone" :placeholder="__('Enter phone number')" />
                            </div>
                        </div>
                    </div>

                    <!-- Password Card -->
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
                    <!-- Account Status -->
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-toggle-left text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Account Status') }}</h2>
                        </div>
                        <div>
                            <label class="text-xs text-neutral-400 block mb-2">{{ __('Active') }}</label>
                            <x-forms.toggle :label="__('Allow access to account')" name="is_active" :checked="$user->is_active" />
                        </div>
                    </div>

                    <!-- Verification Status -->
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-shield-check text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Verification Status') }}</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between py-2 border-b border-neutral-100">
                                <div class="flex items-center gap-2">
                                    <i class="ph ph-envelope text-lg text-neutral-400"></i>
                                    <div>
                                        <p class="text-xs font-semibold text-neutral-700">{{ __('Email Verification') }}</p>
                                        @if($user->email_verified_at)
                                            <p class="text-[10px] text-neutral-400 mt-0.5">{{ $user->email_verified_at->format('M d, Y H:i') }}</p>
                                        @else
                                            <p class="text-[10px] text-amber-500 mt-0.5">{{ __('Pending verification') }}</p>
                                        @endif
                                    </div>
                                </div>
                                <x-forms.toggle :label="''" name="email_verified_at" :checked="$user->email_verified_at" />
                            </div>

                            <div class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2">
                                    <i class="ph ph-phone text-lg text-neutral-400"></i>
                                    <div>
                                        <p class="text-xs font-semibold text-neutral-700">{{ __('Phone Verification') }}</p>
                                        @if($user->phone_verified_at)
                                            <p class="text-[10px] text-neutral-400 mt-0.5">{{ $user->phone_verified_at->format('M d, Y H:i') }}</p>
                                        @else
                                            <p class="text-[10px] text-amber-500 mt-0.5">{{ __('Pending verification') }}</p>
                                        @endif
                                    </div>
                                </div>
                                <x-forms.toggle :label="''" name="phone_verified_at" :checked="$user->phone_verified_at" />
                            </div>
                        </div>
                    </div>

                    <!-- Two-Factor Authentication -->
                    <div class="section-card">
                        <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 mb-4">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ph ph-key text-lg"></i>
                            </div>
                            <h2 class="font-semibold text-neutral-800">{{ __('Two-Factor Authentication') }}</h2>
                        </div>

                        @if($user->hasTwoFactorEnabled())
                            <div class="flex items-center gap-2 mb-3">
                                <x-ui.badge variant="success">{{ __('Enabled') }}</x-ui.badge>
                                @if($user->hasConfirmedTwoFactor())
                                    <x-ui.badge variant="light">{{ __('Confirmed') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning">{{ __('Not Confirmed') }}</x-ui.badge>
                                @endif
                            </div>

                            @if($user->two_factor_recovery_codes && is_array($user->two_factor_recovery_codes))
                                <div class="mb-4">
                                    <p class="text-[10px] text-neutral-400 mb-1 font-semibold">{{ __('RECOVERY CODES:') }}</p>
                                    <div class="grid grid-cols-2 gap-1 bg-neutral-50 p-2 rounded-lg border border-neutral-100">
                                        @foreach($user->two_factor_recovery_codes as $code)
                                            <code class="text-[10px] text-neutral-600 font-mono">{{ $code }}</code>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <button type="submit" name="2fa_action" value="disable" class="btn btn-xs btn-outline-danger w-full flex justify-center py-1.5" onclick="return confirm('{{ __('Are you sure you want to disable 2FA for this user?') }}')">
                                    {{ __('Disable 2FA') }}
                                </button>
                                <button type="submit" name="2fa_action" value="reset" class="btn btn-xs btn-outline-warning w-full flex justify-center py-1.5" onclick="return confirm('{{ __('Are you sure you want to reset 2FA for this user?') }}')">
                                    {{ __('Reset 2FA') }}
                                </button>
                            </div>
                        @else
                            <div class="flex items-center gap-2 py-1">
                                <x-ui.badge variant="light">{{ __('Disabled') }}</x-ui.badge>
                                <span class="text-xs text-neutral-400">{{ __('2FA is not active') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6 mt-6 border-t border-neutral-100">
                <x-forms.submit :label="__('Update User')" class="btn-primary" />
                <x-ui.button variant="ghost" href="{{ route('admin.users.index') }}">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>