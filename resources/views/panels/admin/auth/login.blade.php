<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Secure WaPro administration login for platform operators.">
    <title>{{ __('Admin Login') }} - WaPro</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23075e54'/%3E%3Cpath d='M16 7a9 9 0 0 0-7.7 13.6L7 25l4.5-1.2A9 9 0 1 0 16 7z' fill='%23fff'/%3E%3C/svg%3E">
    @vite(['resources/css/wapro/home.css', 'resources/js/wapro/auth.js'])
    <x-plugins.head-scripts />
</head>
<body class="overflow-x-hidden">
    <main class="relative isolate flex min-h-screen flex-col overflow-hidden bg-section px-5 py-8 sm:px-8">
        <div class="pointer-events-none absolute -top-24 -left-20 -z-10 h-80 w-80 rounded-full bg-deep/10 blur-3xl animate-blob"></div>
        <div class="pointer-events-none absolute -right-24 -bottom-24 -z-10 h-80 w-80 rounded-full bg-primary/10 blur-3xl animate-blob-slow"></div>

        <div class="f-between">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-deep text-neutral-0 shadow-[0_6px_16px_-6px_rgba(7,94,84,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" /></svg>
                </span>
                <span class="font-title text-xl font-extrabold tracking-tight text-title">WaPro Admin</span>
            </a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-body transition-colors hover:text-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 18l-6-6 6-6" /></svg>
                {{ __('Back to site') }}
            </a>
        </div>

        <div class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-md rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)] sm:p-8">
                <div class="text-center">
                    <span class="eyebrow">{{ __('Secure operator access') }}</span>
                    <h1 class="heading-2 mt-3">{{ __('Admin sign in') }}</h1>
                    <p class="m-text mt-2">{{ __('Manage clients, plans, payments, integrations, support, and platform settings.') }}</p>
                </div>

                @if (session('success'))
                    <div class="mt-6 rounded-2xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mt-6 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                        {{ session('error') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-6 rounded-2xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="my-6 flex items-center gap-4">
                    <span class="h-px flex-1 bg-neutral-200"></span>
                    <span class="text-xs font-medium text-neutral-400">{{ __('admin credentials') }}</span>
                    <span class="h-px flex-1 bg-neutral-200"></span>
                </div>

                <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="admin@example.com" class="form-input @error('email') border-error focus:border-error @enderror" />
                        @error('email')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="text-sm font-semibold text-title">{{ __('Password') }}</label>
                            <a href="{{ route('admin.password.request') }}" class="text-sm font-medium text-primary hover:underline">{{ __('Forgot password?') }}</a>
                        </div>
                        <div class="relative">
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="{{ __('Enter your password') }}" class="form-input pr-12 @error('password') border-error focus:border-error @enderror" />
                            <button type="button" data-password-toggle aria-label="{{ __('Show password') }}" class="absolute top-1/2 right-2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-neutral-400 transition-colors hover:text-title">
                                <svg data-eye class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                <svg data-eye-off class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A9.5 9.5 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.3 4M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.5 9.5 0 0 0 3-.5" /></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-body">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-neutral-300 text-primary focus:ring-primary/30" @checked(old('remember')) />
                        {{ __('Remember me') }}
                    </label>

                    <x-plugins.recaptcha />

                    <button type="submit" class="btn btn-primary w-full">{{ __('Sign in to admin') }}</button>
                </form>

                <p class="mt-7 text-center text-xs text-neutral-400">
                    {{ __('Access is restricted to authorized WaPro platform staff.') }}
                </p>
            </div>
        </div>
    </main>

    <x-ui.flash />
</body>
</html>
