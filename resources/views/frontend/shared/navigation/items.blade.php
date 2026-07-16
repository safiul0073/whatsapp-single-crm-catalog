@props(['items' => [], 'level' => 0, 'footer' => false])

@foreach($items as $item)
    @continue(empty($item['is_visible']))
    <li class="{{ $level === 0 ? 'site-nav-item' : 'site-submenu-item' }}">
        @if(!empty($item['children']))
            <div class="site-nav-parent">
                @if(!empty($item['url']))
                    <a href="{{ $item['url'] }}" class="site-nav-link" @if(($item['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                        {{ $item['label'] }}
                    </a>
                @else
                    <button type="button" class="site-nav-link site-nav-button">{{ $item['label'] }}</button>
                @endif
                <ul class="site-submenu {{ $footer ? 'site-submenu-footer' : '' }}">
                    @include('frontend.shared.navigation.items', ['items' => $item['children'], 'level' => $level + 1, 'footer' => $footer])
                </ul>
            </div>
        @elseif(!empty($item['url']))
            <a href="{{ $item['url'] }}" class="site-nav-link" @if(($item['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                {{ $item['label'] }}
            </a>
        @else
            <span class="site-nav-link site-nav-text">{{ $item['label'] }}</span>
        @endif
    </li>
@endforeach
