@php
    $flashes = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type, 'message' => session($type)]);

    $allValidationMessages = collect(($errors ?? null)?->all() ?? [])->unique()->values();
    $validationMessages = $allValidationMessages->take(5);
    $hiddenValidationCount = $allValidationMessages->count() - $validationMessages->count();
@endphp

@if($flashes->isNotEmpty() || $validationMessages->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($flashes as $flash)
    window.showToast(@js(ucfirst($flash['type'])), @js($flash['message']), @js($flash['type']));
    @endforeach
    @foreach($validationMessages as $validationMessage)
    window.showToast(@js(__('Action failed')), @js($validationMessage), 'error');
    @endforeach
    @if($hiddenValidationCount > 0)
    window.showToast(@js(__('Action failed')), @js(trans_choice(':count more issue|:count more issues', $hiddenValidationCount, ['count' => $hiddenValidationCount])), 'error');
    @endif
});
</script>
@endif
