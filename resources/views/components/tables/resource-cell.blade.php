@php
    $value = $column->formatValue($record);
@endphp

@if($column->typeName() === 'select')
    <input
        type="checkbox"
        class="custom-checkbox"
        data-select-item="{{ $definition->queryKey() }}"
        value="{{ data_get($record, $column->key()) }}"
    />
@elseif($column->viewName())
    @include($column->viewName(), array_merge(
        ['record' => $record, 'column' => $column, 'value' => $value, 'definition' => $definition],
        $column->resolveViewData($record, $value)
    ))
@elseif(in_array($column->typeName(), ['badge', 'boolean_badge'], true))
    @php($badge = $column->badgeConfig($record))
    <div class="flex justify-end lg:justify-start rtl:justify-start">
        <x-ui.badge :variant="$badge['variant']">
            {{ __($badge['label']) }}
        </x-ui.badge>
    </div>
@elseif($column->hasLink())
    <a
        href="{{ $column->resolveHref($record) }}"
        class="font-medium text-primary transition-colors hover:text-primary/80 hover:underline"
        @if($column->shouldOpenInNewTab()) target="_blank" rel="noopener noreferrer" @endif
    >
        {{ $value }}
    </a>
@elseif($column->isRawHtml())
    {!! $value !!}
@else
    {{ $value }}
@endif
