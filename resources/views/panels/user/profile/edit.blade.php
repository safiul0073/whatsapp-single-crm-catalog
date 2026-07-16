@php
    $initials = strtoupper(substr($user->name ?? 'U', 0, 2));
    $avatarUrl = $user->avatar ? avatar_url($user->avatar) : null;
    $timezoneValue = old('timezone', $user->timezone ?: config('app.timezone', 'UTC'));
    $localeValue = old('locale', $user->locale ?: app()->getLocale());
@endphp

<x-layouts.user :title="__('My Profile')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('My Profile') }}</h2>
            <p class="m-text mt-1">{{ __('Your personal account — separate from workspace settings.') }}</p>
        </div>
        @if (Route::has('user.workspaces.index'))
            <a href="{{ route('user.workspaces.index') }}" class="btn-sm btn-outline">
                <i class="ph ph-gear-six text-base"></i>
                {{ __('Workspace settings') }}
            </a>
        @endif
    </div>

    <section class="app-card mt-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-4">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $user->name }}"
                    class="h-16 w-16 shrink-0 rounded-full object-cover">
            @else
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-deep text-xl font-bold text-neutral-0">
                    {{ $initials }}
                </span>
            @endif
            <div class="min-w-0">
                <h3 class="font-title text-lg font-bold text-title">{{ $user->name }}</h3>
                <p class="m-text truncate">{{ $user->email }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($user->email_verified_at)
                        <span class="badge badge-success">{{ __('Email verified') }}</span>
                    @else
                        <span class="badge badge-warning">{{ __('Email pending') }}</span>
                    @endif
                    @if ($user->hasConfirmedTwoFactor())
                        <span class="badge badge-success">{{ __('2FA enabled') }}</span>
                    @else
                        <span class="badge badge-neutral">{{ __('2FA off') }}</span>
                    @endif
                    <span class="badge badge-neutral">{{ $user->timezone ?: config('app.timezone', 'UTC') }}</span>
                </div>
            </div>
            <div class="ms-auto">
                <button type="button" class="btn-sm btn-outline" data-modal-open="changeAvatar">
                    <i class="ph ph-camera text-base"></i>
                    {{ __('Change photo') }}
                </button>
            </div>
        </div>
    </section>

    <div class="mt-6 overflow-x-auto scrollbar-hide">
        <div data-tab-group="profile" class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
            <button type="button" class="range-btn is-active" data-tab-target="profDetails">{{ __('Details') }}</button>
            <button type="button" class="range-btn" data-tab-target="profSecurity">{{ __('Security') }}</button>
            <button type="button" class="range-btn" data-tab-target="profSessions">{{ __('Sessions') }}</button>
            <button type="button" class="range-btn" data-tab-target="profPrefs">{{ __('Preferences') }}</button>
        </div>
    </div>

    <div id="profDetails" data-tab-panel="profile" class="mt-6">
        <section class="app-card max-w-2xl p-5 sm:p-6">
            <h3 class="heading-4">{{ __('Personal details') }}</h3>
            <p class="form-hint mt-1">{{ __('This name and email are how teammates see you.') }}</p>
            <form method="POST" action="{{ route('user.profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="details">

                <div>
                    <label for="name" class="form-label">{{ __('Full name') }} <span class="text-error">*</span></label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                        class="form-input">
                    @error('name')
                        <p class="form-hint text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="form-label">{{ __('Email address') }} <span class="text-error">*</span></label>
                    <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}"
                        class="form-input">
                    <p class="form-hint">{{ __('Changing this sends a verification link to the new address.') }}</p>
                    @error('email')
                        <p class="form-hint text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="form-label">{{ __('Phone') }}</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}"
                        placeholder="+1 503 555 0119" class="form-input">
                    @error('phone')
                        <p class="form-hint text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="bio" class="form-label">{{ __('Short bio') }}</label>
                    <textarea id="bio" name="bio" rows="4" maxlength="500" class="form-input"
                        placeholder="{{ __('What should teammates know about your role or availability?') }}">{{ old('bio', $user->bio) }}</textarea>
                    <p class="form-hint">{{ __('Shown only inside your account and team workflows. Max 500 characters.') }}</p>
                    @error('bio')
                        <p class="form-hint text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </section>
    </div>

    <div id="profSecurity" data-tab-panel="profile" class="mt-6 hidden">
        <div class="grid max-w-2xl gap-6">
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Change password') }}</h3>
                <p class="form-hint mt-1">{{ __('Use at least 8 characters with a number and a symbol.') }}</p>
                <form method="POST" action="{{ route('user.profile.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="security">

                    <div>
                        <label for="currentPass" class="form-label">{{ __('Current password') }}</label>
                        <div class="relative">
                            <input id="currentPass" name="current_password" type="password" class="form-input pr-11"
                                autocomplete="current-password">
                            <button type="button"
                                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-neutral-400 hover:text-title"
                                data-password-toggle="currentPass" aria-label="{{ __('Show password') }}">
                                <i class="ph ph-eye text-lg"></i>
                            </button>
                        </div>
                        @error('current_password')
                            <p class="form-hint text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="newPass" class="form-label">{{ __('New password') }}</label>
                            <div class="relative">
                                <input id="newPass" name="password" type="password" class="form-input pr-11"
                                    autocomplete="new-password">
                                <button type="button"
                                    class="absolute inset-y-0 right-0 grid w-11 place-items-center text-neutral-400 hover:text-title"
                                    data-password-toggle="newPass" aria-label="{{ __('Show password') }}">
                                    <i class="ph ph-eye text-lg"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="form-hint text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="confirmPass" class="form-label">{{ __('Confirm new password') }}</label>
                            <div class="relative">
                                <input id="confirmPass" name="password_confirmation" type="password"
                                    class="form-input pr-11" autocomplete="new-password">
                                <button type="button"
                                    class="absolute inset-y-0 right-0 grid w-11 place-items-center text-neutral-400 hover:text-title"
                                    data-password-toggle="confirmPass" aria-label="{{ __('Show password') }}">
                                    <i class="ph ph-eye text-lg"></i>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <p class="form-hint text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary">{{ __('Update Password') }}</button>
                    </div>
                </form>
            </section>

            @if (setting('enable_2fa_for_users', true))
                <section class="app-card p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="heading-4">{{ __('Two-factor authentication') }}</h3>
                            <p class="form-hint mt-1">{{ __('Use a one-time code sent to your verified email address or phone number at sign-in.') }}</p>
                            <div class="mt-3">
                                @if ($user->hasOtpTwoFactorEnabled())
                                    <span class="badge badge-success">{{ __('Enabled') }}</span>
                                    <span class="badge badge-neutral">
                                        {{ $user->otp_two_factor_channel === 'sms' ? __('Phone') : __('Email') }}
                                    </span>
                                @else
                                    <span class="badge badge-warning">{{ __('Not enabled') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($user->hasOtpTwoFactorEnabled())
                            @unless (setting('require_2fa_for_users', false))
                                <form method="POST" action="{{ route('user.two-factor.disable') }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="password" name="password" class="form-input form-input-sm w-44"
                                        placeholder="{{ __('Password') }}" required>
                                    <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error">
                                        {{ __('Disable 2FA') }}
                                    </button>
                                </form>
                            @endunless
                        @else
                            <a href="{{ route('user.two-factor.setup') }}" class="btn-sm btn-primary">{{ __('Enable 2FA') }}</a>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>

    <div id="profSessions" data-tab-panel="profile" class="mt-6 hidden">
        <section class="app-card max-w-3xl p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="heading-4">{{ __('Active sessions') }}</h3>
                    <p class="form-hint mt-1">{{ __('Devices currently signed in to your account.') }}</p>
                </div>
                @if ($sessions->where('is_current', false)->count() > 0)
                    <button type="button" class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                        data-modal-trigger="confirmRevokeAllSessions">
                        {{ __('Sign out others') }}
                    </button>
                @endif
            </div>

            <ul class="mt-5 divide-y divide-neutral-100">
                @forelse ($sessions as $session)
                    <li class="flex items-center gap-4 py-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-section text-primary">
                            @if ($session->device === 'Mobile')
                                <i class="ph ph-device-mobile text-xl"></i>
                            @elseif ($session->device === 'Tablet')
                                <i class="ph ph-device-tablet text-xl"></i>
                            @else
                                <i class="ph ph-laptop text-xl"></i>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-title">
                                {{ $session->platform }} · {{ $session->browser }}
                                @if ($session->is_current)
                                    <span class="badge badge-success ml-1">{{ __('This device') }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-neutral-400">
                                {{ $session->ip_address }} · {{ $session->last_activity->diffForHumans() }}
                            </p>
                        </div>
                        @unless ($session->is_current)
                            <button type="button" class="row-action text-error hover:bg-error/10"
                                data-modal-trigger="confirmRevokeSession-{{ $loop->index }}"
                                aria-label="{{ __('Sign out this device') }}">
                                <i class="ph ph-sign-out text-base"></i>
                            </button>
                        @endunless
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-neutral-400">{{ __('No active sessions found.') }}</li>
                @endforelse
            </ul>

            @if (Route::has('user.developer-api.reports'))
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-section p-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-neutral-0 text-primary">
                            <i class="ph ph-list-magnifying-glass text-xl"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-title">{{ __('Activity log') }}</p>
                            <p class="text-xs text-neutral-400">{{ __('Full record of sign-ins and account changes.') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('user.developer-api.reports') }}" class="btn-sm btn-outline">{{ __('View log') }}</a>
                </div>
            @endif
        </section>
    </div>

    <div id="profPrefs" data-tab-panel="profile" class="mt-6 hidden">
        <section class="app-card max-w-2xl p-5 sm:p-6">
            <h3 class="heading-4">{{ __('Personal preferences') }}</h3>
            <p class="form-hint mt-1">{{ __('These apply to your account only.') }}</p>
            <form method="POST" action="{{ route('user.profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="preferences">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="prefLang" class="form-label">{{ __('Language') }}</label>
                        <select id="prefLang" name="locale" class="form-input">
                            @foreach ($locales as $code => $label)
                                <option value="{{ $code }}" @selected($localeValue === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('locale')
                            <p class="form-hint text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="prefTz" class="form-label">{{ __('Time zone') }}</label>
                        <select id="prefTz" name="timezone" class="form-input">
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected($timezoneValue === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="form-hint text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn btn-primary">{{ __('Save Preferences') }}</button>
                </div>
            </form>
        </section>
    </div>

    @push('modals')
        <div class="modal" id="changeAvatar" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="changeAvatarTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="changeAvatarTitle" class="heading-4">{{ __('Change photo') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('user.profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="avatar">
                    <div class="flex items-start gap-4">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}"
                                class="h-16 w-16 shrink-0 rounded-full object-cover">
                        @else
                            <span class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-deep text-xl font-bold text-neutral-0">
                                {{ $initials }}
                            </span>
                        @endif
                        <div class="flex-1">
                            <label for="avatarUpload" class="form-label">{{ __('Upload from device') }}</label>
                            <input id="avatarUpload" name="avatar_upload" type="file" accept="image/png,image/jpeg,image/webp"
                                class="form-input">
                            <p class="form-hint">{{ __('PNG, JPG or WebP up to 5 MB. Square images look best.') }}</p>
                            @error('avatar_upload')
                                <p class="form-hint text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Or choose from media library') }}</label>
                        <x-media.picker name="avatar" :value="$user->avatar" accept="image" />
                        @error('avatar')
                            <p class="form-hint text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($avatarUrl)
                        <label class="flex items-center gap-2 rounded-lg border border-neutral-200 p-3 text-sm font-semibold text-title">
                            <input type="checkbox" name="remove_avatar" value="1" class="app-checkbox">
                            {{ __('Remove current photo') }}
                        </label>
                    @endif

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary flex-1">{{ __('Save Photo') }}</button>
                        <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($sessions as $session)
            @unless ($session->is_current)
                <x-ui.confirm
                    :id="'confirmRevokeSession-' . $loop->index"
                    :title="__('Revoke Session?')"
                    :message="__('Are you sure you want to revoke this session? The device will be signed out immediately.')"
                    :confirmText="__('Yes, Revoke')"
                    :formId="'revoke-session-' . $loop->index"
                />
                <form id="revoke-session-{{ $loop->index }}" method="POST"
                    action="{{ route('user.profile.sessions.revoke', $session->id) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        @endforeach

        @if ($sessions->where('is_current', false)->count() > 0)
            <x-ui.confirm
                id="confirmRevokeAllSessions"
                :title="__('Revoke All Other Sessions?')"
                :message="__('Are you sure you want to revoke all other sessions? All other devices will be signed out immediately.')"
                :confirmText="__('Yes, Revoke All')"
                formId="revoke-all-sessions"
            />
            <form id="revoke-all-sessions" method="POST" action="{{ route('user.profile.sessions.revoke-all') }}"
                class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endpush
</x-layouts.user>
