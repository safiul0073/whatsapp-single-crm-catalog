@props(['field' => '', 'sortable' => false])

@php
    $currentSortBy = request('sort_by');
    $currentSortOrder = request('sort_order', 'asc');
    $isActive = $sortable && $currentSortBy === $field;
    $nextOrder = $isActive && $currentSortOrder === 'asc' ? 'desc' : 'asc';
@endphp

<th {{ $attributes }}>
    @if ($sortable && $field)
        <a href="{{ request()->fullUrlWithQuery(['sort_by' => $field, 'sort_order' => $nextOrder]) }}"
            class="table-sort-link @if ($isActive) active @endif">
            {{ $slot }}
            <i class="ph ph-sort-ascending table-sort-icon @if ($isActive && $currentSortOrder === 'desc') sort-desc @endif"></i>
        </a>
    @else
        {{ $slot }}
    @endif
</th>
