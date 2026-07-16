@foreach($items as $record)
    @if($definition->rowViewName())
        @include($definition->rowViewName(), ['record' => $record, 'definition' => $definition])
    @else
        <tr>
            @foreach($definition->visibleColumns($record) as $column)
                <td data-th="{{ __($column->dataTh()) }}" class="{{ $column->cellClasses() }}">
                    @include('components.tables.resource-cell', [
                        'definition' => $definition,
                        'column' => $column,
                        'record' => $record,
                    ])
                </td>
            @endforeach

            @if($definition->hasActions())
                <td data-th="{{ __($definition->actionsLabelText()) }}" class="{{ $definition->actionsCellClasses() }}">
                    @include('components.tables.resource-actions', [
                        'definition' => $definition,
                        'record' => $record,
                    ])
                </td>
            @endif
        </tr>
    @endif
@endforeach

@if($items->isEmpty())
    <tr>
        <td colspan="{{ $definition->colspan() }}" class="table-empty-state">
            <div class="table-empty-state__icon">
                <i class="ph ph-folder-open"></i>
            </div>
            <p>{{ __($definition->emptyMessageText()) }}</p>
        </td>
    </tr>
@endif
