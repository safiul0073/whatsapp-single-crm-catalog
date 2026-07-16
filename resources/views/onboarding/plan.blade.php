<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Choose your WaPro plan.">
    <title>{{ __('Choose plan') }} - WaPro</title>
    @vite(['resources/css/wapro/home.css'])
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

        <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center py-10">
            <div class="max-w-2xl">
                <span class="eyebrow">{{ __('Step 3 of 3') }}</span>
                <h1 class="heading-2 mt-3">{{ __('Choose your plan') }}</h1>
                <p class="m-text mt-2">{{ __('Start free or pick the plan that matches your team volume.') }}</p>
            </div>

            @if (session('error'))
                <div class="mt-5 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">{{ session('error') }}</div>
            @endif

            <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($plans as $plan)
                    <form method="POST" action="{{ route('onboarding.plan.store') }}" class="flex min-h-[440px] flex-col rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_20px_50px_-35px_rgba(10,27,20,0.35)]">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">

                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-primary">{{ ucfirst($plan->interval) }}</p>
                            <h2 class="mt-2 text-2xl font-extrabold text-title">{{ $plan->name }}</h2>
                            <p class="m-text mt-2 min-h-[48px]">{{ $plan->description }}</p>
                        </div>

                        <div class="mt-6">
                            <span class="text-4xl font-extrabold text-title">{{ currency_format($plan->price) }}</span>
                            <span class="text-sm text-body">/{{ $plan->interval }}</span>
                        </div>

                        <ul class="mt-6 flex-1 space-y-3 text-sm text-body">
                            @foreach (($plan->features ?? []) as $feature)
                                <li class="flex gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <button type="submit" class="btn btn-primary mt-6 w-full">
                            {{ (float) $plan->price <= 0 ? __('Start free') : __('Continue to checkout') }}
                        </button>
                    </form>
                @empty
                    <div class="rounded-3xl border border-neutral-200 bg-neutral-0 p-8 text-center text-body md:col-span-2 xl:col-span-4">
                        {{ __('No active plans are available. Please contact support.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </main>
</body>
</html>
