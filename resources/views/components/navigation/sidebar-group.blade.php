@props([
    'title' => 'Main Menu',
])

<div class="space-y-2">
    <h3 class="nav-group-title">{{ __($title) }}</h3>
    <nav class="space-y-0.5">
        {{ $slot }}
    </nav>
</div>
