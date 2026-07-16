@php
    $providers = collect([
        ['key' => 'google', 'label' => 'Google'],
        ['key' => 'facebook', 'label' => 'Facebook'],
        ['key' => 'github', 'label' => 'GitHub'],
    ])->filter(function (array $provider): bool {
        return (bool) setting("social_{$provider['key']}_enabled", false)
            && (string) config("services.{$provider['key']}.client_id") !== ''
            && (string) config("services.{$provider['key']}.client_secret") !== '';
    });
@endphp

@if($providers->isNotEmpty())
    <div class="mt-6 grid gap-3">
        @foreach($providers as $provider)
            <a href="{{ route('social.redirect', $provider['key']) }}"
               class="group flex min-h-12 w-full items-center justify-center gap-3 rounded-2xl border border-neutral-200 bg-neutral-0 px-4 py-3 text-sm font-bold text-title shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:bg-primary/5 hover:text-primary hover:shadow-lg hover:shadow-primary/10">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-neutral-100 text-title transition-colors duration-200 group-hover:bg-neutral-0">
                    @switch($provider['key'])
                        @case('google')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.3 9.14 5.38 12 5.38z"/>
                            </svg>
                            @break

                        @case('facebook')
                            <svg class="h-5 w-5 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.438H7.078v-3.49h3.047V9.414c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97H15.83c-1.491 0-1.956.932-1.956 1.887v2.263h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
                            </svg>
                            @break

                        @case('github')
                            <svg class="h-5 w-5 text-neutral-950" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56v-2.15c-3.2.7-3.88-1.38-3.88-1.38-.52-1.33-1.27-1.68-1.27-1.68-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.18 1.76 1.18 1.02 1.75 2.68 1.24 3.34.95.1-.74.4-1.24.72-1.53-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.28 1.18-3.09-.12-.29-.51-1.46.11-3.05 0 0 .96-.31 3.15 1.18A10.9 10.9 0 0 1 12 6c.97 0 1.94.13 2.85.39 2.19-1.49 3.15-1.18 3.15-1.18.62 1.59.23 2.76.11 3.05.73.81 1.18 1.83 1.18 3.09 0 4.42-2.69 5.39-5.25 5.68.41.36.77 1.06.77 2.13v3.19c0 .31.21.68.79.56A11.51 11.51 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5z"/>
                            </svg>
                            @break
                    @endswitch
                </span>
                <span>{{ __('Continue with :provider', ['provider' => $provider['label']]) }}</span>
            </a>
        @endforeach
    </div>
@endif
