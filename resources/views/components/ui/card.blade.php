@props([])
<div {{ $attributes->merge(['class' => 'section-card']) }}>
    @isset($header)
    <div class="mb-4 flex items-center justify-between border-b border-neutral-100 pb-4">
        {{ $header }}
    </div>
    @endisset
    {{ $slot }}
    @isset($footer)
    <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-4">
        {{ $footer }}
    </div>
    @endisset
</div>
