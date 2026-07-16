@php $d = $section->data ?? []; @endphp
<section id="modules" class="spy-section">
    <div class="container">
        <div class="mx-auto max-w-2xl text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif
        </div>

        @php $modules = $d['modules'] ?? []; $chunks = array_chunk($modules, 4); @endphp
        @if (!empty($modules))
            <div class="mt-14 space-y-3">
                @foreach ($chunks as $row)
                    <div class="acc-row">
                        @foreach ($row as $index => $module)
                            <article class="acc-panel {{ $index === 0 ? 'is-default' : '' }}" tabindex="0">
                                <span class="acc-panel__icon">
                                    @if (!empty($module['icon_svg']))
                                        {!! $module['icon_svg'] !!}
                                    @else
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>
                                    @endif
                                </span>
                                <h3 class="acc-panel__label">{{ $module['label'] ?? '' }}</h3>
                                @if (!empty($module['description']) || !empty($module['link_text']))
                                    <div class="acc-panel__details">
                                        <div class="acc-panel__details-inner">
                                            @if (!empty($module['description']))
                                                <p class="acc-panel__reveal">{{ $module['description'] }}</p>
                                            @endif
                                            @if (!empty($module['link_text']))
                                                <a href="{{ $module['link_url'] ?? route('features') }}" class="acc-panel__more">{{ $module['link_text'] }}
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
