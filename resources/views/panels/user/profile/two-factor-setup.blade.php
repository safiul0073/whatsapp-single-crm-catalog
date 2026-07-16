<x-layouts.user :title="__('Setup Two-Factor Authentication')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Setup Two-Factor Authentication') }}</h1>
            <a href="{{ route('user.profile.edit') }}" class="btn btn-outline">
                <i class="ph ph-arrow-left mr-1.5"></i>
                {{ __('Back to Profile') }}
            </a>
        </div>

        <div class="section-card max-w-2xl">
            @if ($pendingDelivery)
                <div class="space-y-5">
                    <div>
                        <h3 class="heading-5 text-neutral-950 mb-2">{{ __('Enter verification code') }}</h3>
                        <p class="text-sm text-neutral-500">
                            {{ __('We sent a 6-digit code to :destination.', ['destination' => $pendingDelivery['masked_destination'] ?? '']) }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('user.two-factor.enable') }}" class="max-w-sm space-y-4">
                        @csrf
                        <x-forms.input :label="__('Verification Code')" name="code" type="text" required placeholder="000000" icon="ph ph-shield-check" inputmode="numeric" autocomplete="one-time-code" autofocus />
                        <div class="flex flex-wrap items-center gap-3">
                            <x-forms.submit :label="__('Confirm 2FA')" />
                            <button type="submit" name="channel" value="{{ $pendingDelivery['channel'] ?? 'email' }}" class="btn btn-outline" formnovalidate>
                                {{ __('Resend code') }}
                            </button>
                            <a href="{{ route('user.profile.edit') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            @else
                <div class="space-y-5">
                    <div>
                        <h3 class="heading-5 text-neutral-950 mb-2">{{ __('Choose verification method') }}</h3>
                        <p class="text-sm text-neutral-500">{{ __('Only verified email addresses or phone numbers can be used for two-factor authentication.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('user.two-factor.enable') }}" class="space-y-3">
                        @csrf
                        @php($firstVerifiedChannel = collect($channels)->filter(fn ($meta) => $meta['verified'])->keys()->first())
                        @foreach ($channels as $channel => $meta)
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-neutral-100 p-4 {{ $meta['verified'] ? 'cursor-pointer hover:border-primary/40' : 'opacity-60' }}">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-section text-primary">
                                        <i class="ph {{ $channel === 'email' ? 'ph-envelope-simple' : 'ph-device-mobile' }} text-xl"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-title">{{ $meta['label'] }}</span>
                                        <span class="block truncate text-xs text-neutral-400">{{ $meta['destination'] }}</span>
                                    </span>
                                </span>
                                <span class="flex items-center gap-2">
                                    @if ($meta['verified'])
                                        <span class="badge badge-success">{{ __('Verified') }}</span>
                                        <input type="radio" name="channel" value="{{ $channel }}" class="app-checkbox" @checked($firstVerifiedChannel === $channel)>
                                    @else
                                        <span class="badge badge-warning">{{ __('Not verified') }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach

                        @error('channel')
                            <p class="form-hint text-error">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" class="btn btn-primary" @disabled(! collect($channels)->contains(fn ($meta) => $meta['verified']))>
                                {{ __('Send verification code') }}
                            </button>
                            <a href="{{ route('user.profile.edit') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.user>
