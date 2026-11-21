@php
    $navItems = [
        ['key' => 'live', 'label' => __('Live Streams'), 'route' => 'user.commerce.storefront.live', 'icon' => 'ph-broadcast'],
        ['key' => 'videos', 'label' => __('Videos'), 'route' => 'user.commerce.storefront.videos', 'icon' => 'ph-video-camera'],
        ['key' => 'settings', 'label' => __('Live Settings'), 'route' => 'user.commerce.storefront.live-settings', 'icon' => 'ph-sliders-horizontal'],
    ];
    $liveEnabled = (bool) ($liveSettings['enabled'] ?? true);
@endphp

<header class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ __('Storefront & Growth') }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-2.5">
            <h1 class="heading-3 font-bold text-title">{{ $title }}</h1>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $liveEnabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400' }}">
                <span class="size-2 rounded-full {{ $liveEnabled ? 'bg-emerald-500' : 'bg-neutral-400' }}"></span>
                {{ $liveEnabled ? __('Live Hub Active') : __('Disabled') }}
            </span>
        </div>
        <p class="mt-1 text-sm text-body">{{ $subtitle }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if(! empty($storeUrl))
            <a href="{{ rtrim($storeUrl, '/') }}/live" target="_blank" rel="noopener" class="btn btn-outline btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5">
                <i class="ph ph-arrow-square-out text-base"></i>
                <span>{{ __('View Storefront /live') }}</span>
            </a>
        @endif
    </div>
</header>

<nav aria-label="{{ __('Live & Videos Navigation') }}" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-neutral-200/80 bg-white p-1.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
    @foreach($navItems as $item)
        @php $isActive = ($active ?? '') === $item['key']; @endphp
        <a
            href="{{ route($item['route']) }}"
            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition {{ $isActive ? 'bg-primary text-white shadow-2xs' : 'text-body hover:bg-neutral-100 hover:text-title dark:hover:bg-neutral-800' }}"
        >
            <i class="ph {{ $item['icon'] }} text-base"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

@if(session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 p-3.5 text-xs font-medium text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300" role="status">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="rounded-xl border border-red-200 bg-red-50/90 p-3.5 text-xs font-medium text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300" role="alert">
        {{ session('error') }}
    </div>
@endif

@if(isset($errors) && $errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50/90 p-4 text-sm text-red-900 shadow-xs dark:border-red-800 dark:bg-red-950/40 dark:text-red-300" role="alert">
        <div class="flex items-start gap-3">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-red-500 text-white shrink-0 mt-0.5">
                <i class="ph ph-warning-circle text-lg"></i>
            </span>
            <div class="space-y-1">
                <p class="font-semibold">{{ __('Please review the following issues:') }}</p>
                <ul class="list-disc space-y-0.5 pl-5 text-xs text-red-800 dark:text-red-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
