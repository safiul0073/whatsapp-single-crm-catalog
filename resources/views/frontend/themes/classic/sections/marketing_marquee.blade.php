@php $d = $section->data ?? []; @endphp
<div class="border-y border-neutral-200 bg-section py-10">
    @php $items = $d['items'] ?? []; @endphp
    @if (!empty($items))
        <div class="marquee marquee--fade" data-speed="35">
            <div class="marquee__track">
                @foreach ($items as $item)
                    <span class="marquee__item display-1 {{ !empty($item['accent']) ? 'text-primary' : '' }}">{{ $item['text'] ?? '' }}</span>
                    <span class="size-2.5 shrink-0 rounded-full bg-primary/40"></span>
                @endforeach
            </div>
        </div>
    @endif
</div>
