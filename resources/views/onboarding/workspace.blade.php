<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Set up your WaPro workspace.">
    <title>{{ __('Workspace setup') }} - WaPro</title>
    @vite(['resources/css/wapro/home.css', 'resources/js/wapro/auth.js'])
</head>
<body class="overflow-x-hidden">
    <main class="relative isolate flex min-h-screen flex-col overflow-hidden bg-section px-5 py-8 sm:px-8">
        <div class="f-between">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" /></svg>
                </span>
                <span class="font-title text-xl font-extrabold tracking-tight text-title">WaPro</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-body transition-colors hover:text-primary">{{ __('Sign out') }}</button>
            </form>
        </div>

        <div class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-2xl rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)] sm:p-8">
                <div>
                    <span class="eyebrow">{{ __('Step 2 of 3') }}</span>
                    <h1 class="heading-2 mt-3">{{ __('Create your workspace') }}</h1>
                    <p class="m-text mt-2">{{ __('Tell us how your team will use WaPro so your dashboard starts with the right context.') }}</p>
                </div>

                <form method="POST" action="{{ route('onboarding.workspace.store') }}" class="mt-7 grid gap-4 sm:grid-cols-2">
                    @csrf

                    <div class="sm:col-span-2">
                        <label for="name" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Workspace title') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $workspace->name) }}" required class="form-input @error('name') border-error focus:border-error @enderror" />
                        @error('name')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="category" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Category') }}</label>
                        <select id="category" name="category" required class="form-input @error('category') border-error focus:border-error @enderror">
                            <option value="">{{ __('Select category') }}</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', data_get($workspace->settings, 'category')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="team_size" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Team size') }}</label>
                        <select id="team_size" name="team_size" required class="form-input @error('team_size') border-error focus:border-error @enderror">
                            <option value="">{{ __('Select team size') }}</option>
                            @foreach ($teamSizes as $value => $label)
                                <option value="{{ $value }}" @selected(old('team_size', data_get($workspace->settings, 'team_size')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('team_size')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="timezone" class="mb-1.5 block text-sm font-semibold text-title">{{ __('Time zone') }}</label>
                        <select id="timezone" name="timezone" required class="form-input @error('timezone') border-error focus:border-error @enderror">
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $workspace->timezone) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-primary w-full">{{ __('Continue to plans') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
