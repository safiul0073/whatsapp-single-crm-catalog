<x-layouts.admin :title="__('Setup Two-Factor Authentication')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Setup Two-Factor Authentication') }}</h1>
            <a href="{{ route('admin.profile.edit') }}" class="btn btn-outline">
                <i class="ph ph-arrow-left mr-1.5"></i>
                {{ __('Back to Profile') }}
            </a>
        </div>

        <div class="section-card max-w-2xl">
            <div class="space-y-6">
                {{-- Step 1: Scan QR Code --}}
                <div>
                    <h3 class="heading-5 text-neutral-950 mb-2">{{ __('Step 1: Scan QR Code') }}</h3>
                    <p class="text-sm text-neutral-500 mb-4">{{ __('Scan the following QR code using your authenticator app (Google Authenticator, Authy, etc.).') }}</p>

                    <div class="flex justify-center rounded-xl border border-neutral-100 bg-neutral-50 p-6">
                        <div class="bg-white rounded-lg p-3">
                            {!! $qrCodeSvg !!}
                        </div>
                    </div>
                </div>

                {{-- Manual Entry --}}
                <div>
                    <h3 class="heading-5 text-neutral-950 mb-2">{{ __('Or enter the key manually') }}</h3>
                    <p class="text-sm text-neutral-500 mb-3">{{ __('If you cannot scan the QR code, enter this key into your authenticator app manually.') }}</p>
                    <div class="flex items-center gap-2">
                        <code class="rounded-lg bg-neutral-50 border border-neutral-100 px-4 py-2.5 font-mono text-sm text-neutral-700 tracking-wider select-all">{{ $secret }}</code>
                    </div>
                </div>

                {{-- Step 2: Verify --}}
                <div class="pt-4 border-t border-neutral-100">
                    <h3 class="heading-5 text-neutral-950 mb-2">{{ __('Step 2: Verify Code') }}</h3>
                    <p class="text-sm text-neutral-500 mb-4">{{ __('Enter the 6-digit code from your authenticator app to confirm setup.') }}</p>

                    <form method="POST" action="{{ route('admin.two-factor.enable') }}" class="max-w-sm space-y-4">
                        @csrf
                        <x-forms.input :label="__('Authentication Code')" name="code" type="text" required placeholder="000000" icon="ph ph-shield-check" inputmode="numeric" autocomplete="one-time-code" autofocus />
                        <div class="flex items-center gap-3">
                            <x-forms.submit :label="__('Enable Two-Factor Authentication')" />
                            <a href="{{ route('admin.profile.edit') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
