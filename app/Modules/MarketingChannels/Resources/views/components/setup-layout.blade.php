@props([])

<div {{ $attributes->merge(['class' => 'mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_360px]']) }}>
    <div class="min-w-0 space-y-6">{{ $slot }}</div>
    @isset($aside)
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">{{ $aside }}</aside>
    @endisset
</div>
