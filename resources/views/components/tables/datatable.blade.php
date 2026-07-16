@props([
    'url' => '',
    'searchable' => true,
    'placeholder' => 'Search...',
    'perPageOptions' => [10, 15, 25, 50],
    'exportUrl' => '',
    'extraAttributes' => [],
])

@php
    $currentSearch = request('search', '');
    $currentPerPage = request('per_page', 15);
    $currentSortBy = request('sort_by', 'created_at');
    $currentSortOrder = request('sort_order', 'desc');
    $hasToolbar = $searchable || $exportUrl || $perPageOptions !== [];
@endphp

<div
    x-data="dataTable({
        url: '{{ $url }}',
        search: '{{ $currentSearch }}',
        sortBy: '{{ $currentSortBy }}',
        sortOrder: '{{ $currentSortOrder }}',
        perPage: {{ $currentPerPage }}
    })"
    {{ $attributes->merge(['class' => 'datatable-shell']) }}
    @foreach($extraAttributes as $attribute => $value)
        {{ $attribute }}="{{ $value }}"
    @endforeach
>
    @if($hasToolbar)
        {{-- Search + Per Page controls --}}
        <div class="datatable-toolbar">
            @if($searchable)
            {{-- Search: Alpine-enhanced (debounced AJAX) with noscript GET fallback --}}
            <div class="relative w-full sm:max-w-sm">
                <form action="{{ $url }}" method="GET" x-ref="searchForm" class="input-group">
                    <i class="ph ph-magnifying-glass input-icon-left"></i>
                    <input
                        type="text"
                        name="search"
                        x-model="search"
                        x-on:input="onSearch()"
                        placeholder="{{ $placeholder }}"
                        class="input-field has-icon-left"
                        autocomplete="off"
                    />
                    <button
                        x-show="search"
                        x-on:click.prevent="search = ''; onSearch()"
                        type="button"
                        class="input-icon-right search-clear"
                        x-cloak
                    >
                        <i class="ph ph-x"></i>
                    </button>
                </form>
            </div>
            @endif

            <div class="datatable-controls">
                @if($exportUrl)
                {{-- Export dropdown --}}
                <div class="relative inline-block">
                    <button type="button" class="btn btn-outline btn-sm" data-floating-dropdown="export-dropdown">
                        <i class="ph ph-file-csv"></i>
                        {{ __('Export') }}
                        <i class="ph ph-caret-down text-xs"></i>
                    </button>
                    <div id="export-dropdown" class="floating-dropdown-panel min-w-44">
                        <a href="{{ $exportUrl }}?{{ http_build_query(request()->only('search', 'sort_by', 'sort_order', 'is_active')) }}" class="floating-dropdown-item">
                            <i class="ph ph-file-csv"></i>
                            {{ __('Export All') }}
                        </a>
                        <button
                            type="button"
                            class="floating-dropdown-item w-full text-left"
                            x-on:click="exportSelected('{{ $exportUrl }}')"
                        >
                            <i class="ph ph-check-square"></i>
                            {{ __('Export Selected') }}
                        </button>
                    </div>
                </div>
                @endif

                {{-- Per Page selector --}}
                @if($perPageOptions !== [])
                    <div class="datatable-per-page">
                        <span>{{ __('Show') }}</span>
                        <select
                            x-model="perPage"
                            x-on:change="changePerPage()"
                            class="input-field w-auto rounded-lg px-2.5 py-1.5 text-sm"
                        >
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        <span>{{ __('entries') }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Bulk actions slot --}}
    {{ $bulkActions ?? '' }}

    {{-- Optional filters / toolbar section --}}
    {{ $beforeTable ?? '' }}

    {{-- Table wrapper with loading overlay --}}
    <div class="datatable-body">
        {{ $slot }}

        {{-- Loading overlay --}}
        <div class="datatable-overlay" x-show="loading" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak>
            <div class="datatable-spinner"></div>
        </div>
    </div>

    {{-- Pagination (swapped via AJAX) --}}
    <div data-datatable-pagination>
        {{ $pagination ?? '' }}
    </div>
</div>
