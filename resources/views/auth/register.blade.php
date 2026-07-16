<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Create a free WaPro account to connect WhatsApp Cloud API and launch campaigns.">
    <title>{{ __('Create account') }} - WaPro</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%2325d366'/%3E%3Cpath d='M16 7a9 9 0 0 0-7.7 13.6L7 25l4.5-1.2A9 9 0 1 0 16 7z' fill='%23fff'/%3E%3C/svg%3E">
    @vite(['resources/css/wapro/home.css', 'resources/js/wapro/auth.js'])
    <x-plugins.head-scripts />
</head>
<body class="overflow-x-hidden">
    <main class="relative isolate flex min-h-screen flex-col overflow-hidden bg-section px-5 py-8 sm:px-8">
        <div class="pointer-events-none absolute -top-24 -left-20 -z-10 h-80 w-80 rounded-full bg-primary/10 blur-3xl animate-blob"></div>
        <div class="pointer-events-none absolute -right-24 -bottom-24 -z-10 h-80 w-80 rounded-full bg-accent/10 blur-3xl animate-blob-slow"></div>

        <div class="f-between">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" /></svg>
                </span>
                <span class="font-title text-xl font-extrabold tracking-tight text-title">WaPro</span>
            </a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-body transition-colors hover:text-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 18l-6-6 6-6" /></svg>
                {{ __('Back to site') }}
            </a>
        </div>

        <div class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-md rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)] sm:p-8">
                <div class="text-center">
                    <span class="eyebrow">{{ __('Step 1 of 3') }}</span>
                    <h1 class="heading-2 mt-3">{{ __('Create your workspace') }}</h1>
                    <p class="m-text mt-2">
                        {{ __('Already have an account?') }}
                        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('Sign in') }}</a>
                    </p>
                </div>

                <x-auth.social-buttons />

                <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-sm font-semibold text-title">{{ __('First name') }}</label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required autocomplete="given-name" placeholder="Jane" class="form-input @error('first_name') border-error focus:border-error @enderror" />
                            @error('first_name')
                                <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Last name') }}</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required autocomplete="family-name" placeholder="Doe" class="form-input @error('last_name') border-error focus:border-error @enderror" />
                            @error('last_name')
                                <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Work email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="jane@company.com" class="form-input @error('email') border-error focus:border-error @enderror" />
                        @error('email')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Password') }}</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="{{ __('At least 8 characters') }}" class="form-input pr-12 @error('password') border-error focus:border-error @enderror" />
                            <button type="button" data-password-toggle aria-label="{{ __('Show password') }}" class="absolute top-1/2 right-2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-neutral-400 transition-colors hover:text-title">
                                <svg data-eye class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                <svg data-eye-off class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A9.5 9.5 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.3 4M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.5 9.5 0 0 0 3-.5" /></svg>
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs text-neutral-400">{{ __('Use 8+ characters with a mix of letters and numbers.') }}</p>
                        @error('password')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Confirm password') }}</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="{{ __('Repeat your password') }}" class="form-input pr-12 @error('password_confirmation') border-error focus:border-error @enderror" />
                            <button type="button" data-password-toggle aria-label="{{ __('Show password') }}" class="absolute top-1/2 right-2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-neutral-400 transition-colors hover:text-title">
                                <svg data-eye class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                <svg data-eye-off class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A9.5 9.5 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.3 4M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.5 9.5 0 0 0 3-.5" /></svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-body">
                        <input type="checkbox" name="terms" required class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-primary focus:ring-primary/30" />
                        <span>{{ __('I agree to the Terms and Privacy Policy.') }}</span>
                    </label>

                    <x-plugins.recaptcha />

                    <button type="submit" class="btn btn-primary w-full">{{ __('Continue to workspace') }}</button>
                </form>

                <p class="mt-7 text-center text-xs text-neutral-400">
                    {{ __('Free Starter plan available. Next you will set up your workspace and choose a plan.') }}
                </p>
            </div>
        </div>
    </main>
</body>
</html>
