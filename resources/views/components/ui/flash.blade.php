@php
    $flashes = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type, 'message' => session($type)]);
@endphp

@if($flashes->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($flashes as $flash)
    window.showToast(@js(ucfirst($flash['type'])), @js($flash['message']), @js($flash['type']));
    @endforeach
});
</script>
@endif
