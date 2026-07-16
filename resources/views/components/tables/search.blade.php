@props(['action' => '', 'placeholder' => 'Search...'])

@php
    $currentSearch = request('search', '');
    $preservedParams = request()->except(['search', 'page']);
@endphp

<form action="{{ $action }}" method="GET" class="table-search-form">
    @foreach($preservedParams as $key => $value)
        @if(is_array($value))
            @foreach($value as $v)
                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}" />
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
        @endif
    @endforeach
    <div class="input-group">
        <i class="ph ph-magnifying-glass input-icon-left"></i>
        <input
            type="text"
            name="search"
            value="{{ $currentSearch }}"
            placeholder="{{ $placeholder }}"
            class="input-field has-icon-left"
            {{ $attributes }}
        />
        @if($currentSearch)
            <a href="{{ $action }}?{{ http_build_query($preservedParams) }}" class="input-icon-right search-clear">
                <i class="ph ph-x"></i>
            </a>
        @endif
    </div>
</form>
