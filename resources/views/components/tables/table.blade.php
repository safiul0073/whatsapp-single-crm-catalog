@props(['separator' => false, 'extraAttributes' => []])

<div class="table-responsive">
    <table
        {{ $attributes->merge(['class' => 'data-table table-stack' . ($separator ? ' data-table-separator' : '')]) }}
        @foreach($extraAttributes as $attribute => $value)
            {{ $attribute }}="{{ $value }}"
        @endforeach
    >
        {{ $slot }}
    </table>
</div>
