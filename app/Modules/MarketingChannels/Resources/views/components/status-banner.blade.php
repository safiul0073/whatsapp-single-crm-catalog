@props([])

@php
    $validationErrors = $errors ?? session('errors');
@endphp

@if (session('status'))
    <x-ui.alert type="success" class="mt-4" dismissible data-status-banner="status">{{ session('status') }}</x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert type="error" class="mt-4" dismissible data-status-banner="error">{{ session('error') }}</x-ui.alert>
@endif

@if ($validationErrors?->any())
    <x-ui.alert type="error" class="mt-4" data-status-banner="validation">{{ $validationErrors->first() }}</x-ui.alert>
@endif
