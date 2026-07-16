@props([
    'definition',
    'items',
])

@php
    $filters = $definition->filtersConfig();
    $bulkActions = $definition->bulkActionsConfig();
@endphp

<x-tables.datatable
    :url="url()->current()"
    :searchable="$definition->isSearchable()"
    :placeholder="$definition->searchPlaceholderText()"
    :perPageOptions="$definition->perPageOptionValues()"
    :exportUrl="$definition->exportUrlValue()"
    :extraAttributes="$definition->wrapperAttributeBag()"
    :class="$definition->wrapperClasses()"
>
    <x-slot:bulkActions>
        @if($bulkActions)
            @if($bulkActions->viewName())
                @include($bulkActions->viewName(), array_merge(
                    ['bulkActions' => $bulkActions, 'items' => $items, 'definition' => $definition],
                    $bulkActions->resolveViewData($items, $definition)
                ))
            @else
                <x-tables.bulk-actions
                    :group="$bulkActions->group()"
                    :deleteAction="$bulkActions->resolveDeleteAction($items, $definition)"
                    :toggleAction="$bulkActions->resolveToggleAction($items, $definition)"
                    :exportAction="$bulkActions->resolveExportAction($items, $definition)"
                />
            @endif
        @endif
    </x-slot:bulkActions>

    <x-slot:beforeTable>
        @if(isset($toolbar))
            {{ $toolbar }}
        @endif

        @if($filters)
            @include($filters->viewName(), array_merge(
                ['items' => $items, 'definition' => $definition],
                $filters->resolveData($items, $definition)
            ))
        @endif

        @if(isset($secondaryControls))
            {{ $secondaryControls }}
        @endif
    </x-slot:beforeTable>

    <x-tables.table
        :class="$definition->tableClasses()"
        :extraAttributes="$definition->tableAttributeBag()"
    >
        @if($definition->headerViewName())
            @include($definition->headerViewName(), ['definition' => $definition, 'items' => $items])
        @else
            <thead>
                <tr>
                    @foreach($definition->visibleColumns() as $column)
                        @if($column->typeName() === 'select')
                            <th class="{{ $column->headerClasses() ?: 'w-10' }}">
                                <input type="checkbox" class="custom-checkbox" data-select-all="{{ $definition->queryKey() }}" />
                            </th>
                        @elseif($column->isSortable())
                            <x-tables.heading
                                :field="$column->sortField()"
                                sortable
                                :class="$column->headerClasses()"
                            >
                                {{ __($column->label()) }}
                            </x-tables.heading>
                        @else
                            <th class="{{ $column->headerClasses() }}">{{ __($column->label()) }}</th>
                        @endif
                    @endforeach

                    @if($definition->hasActions())
                        <th class="{{ $definition->actionsHeaderClasses() }}">{{ __($definition->actionsLabelText()) }}</th>
                    @endif
                </tr>
            </thead>
        @endif

        <tbody data-datatable-body>
            @include('components.tables.resource-rows', ['definition' => $definition, 'items' => $items])
        </tbody>
    </x-tables.table>

    <x-slot:pagination>
        <x-tables.pagination :paginator="$items" />
    </x-slot:pagination>
</x-tables.datatable>
