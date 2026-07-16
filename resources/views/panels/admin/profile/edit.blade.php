<x-layouts.admin :title="__('Edit Profile')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Edit Profile') }}</h1>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Left Column: Profile Information --}}
            <div class="space-y-6">
                {{-- Profile Details --}}
                <div class="section-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-user text-xl"></i>
                        </div>
                        <div>
                            <h3 class="heading-5 text-neutral-950">{{ __('Profile Information') }}</h3>
                            <p class="text-sm text-neutral-400">{{ __('Update your personal details and avatar.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <x-forms.input :label="__('Name')" name="name" :value="$user->name" required />
                        <x-forms.input :label="__('Email')" name="email" type="email" :value="$user->email" required />
                        <x-forms.input :label="__('Phone')" name="phone" :value="$user->phone" :placeholder="__('Enter phone number')" />

                        {{-- Avatar Upload --}}
                        <div>
                            @if($user->avatar)
                                <label class="form-label">{{ __('Current Avatar') }}</label>
                                <div class="mb-3">
                                    <img src="{{ Storage::url($user->avatar) }}" alt="{{ __('Current avatar') }}" class="h-20 w-20 rounded-full object-cover">
                                </div>
                            @endif
                            <x-forms.file-upload :label="__('Avatar')" name="avatar" accept="image/*" />
                        </div>

                        <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                            <x-forms.submit :label="__('Update Profile')" />
                        </div>
                    </form>
                </div>

                {{-- Change Password --}}
                <div class="section-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10 text-warning">
                            <i class="ph ph-lock-key text-xl"></i>
                        </div>
                        <div>
                            <h3 class="heading-5 text-neutral-950">{{ __('Change Password') }}</h3>
                            <p class="text-sm text-neutral-400">{{ __('Ensure your account uses a strong password.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        {{-- Hidden fields to preserve current values --}}
                        <input type="hidden" name="name" value="{{ $user->name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <x-forms.input :label="__('New Password')" name="password" type="password" required :placeholder="__('Enter new password')" />
                        <x-forms.input :label="__('Confirm Password')" name="password_confirmation" type="password" required :placeholder="__('Confirm new password')" />

                        <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                            <x-forms.submit :label="__('Update Password')" />
                        </div>
                    </form>
                </div>
            </div>

            {{-- Right Column: Security --}}
            <div class="space-y-6">
                {{-- Two-Factor Authentication --}}
                @include('panels.admin.profile._two-factor-setup')

                {{-- Active Sessions --}}
                <x-sessions.active-sessions
                    :sessions="$sessions"
                    revokeRoute="admin.profile.sessions.revoke"
                    revokeAllRoute="admin.profile.sessions.revoke-all"
                />
            </div>
        </div>
    </div>
</x-layouts.admin>
