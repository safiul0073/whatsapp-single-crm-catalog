@php
    $data = $section->data ?? [];
    $contentBlocks = collect($data['content_blocks'] ?? [])
        ->filter(fn ($block) => is_array($block) && (filled($block['heading'] ?? null) || filled($block['body'] ?? null)))
        ->values();
@endphp

<section class="bg-neutral-0 py-20 lg:py-28">
    <div class="container">
        <div class="mx-auto max-w-4xl">
            <div class="mb-12 space-y-5">
                @if (! empty($data['eyebrow']))
                    <p class="text-sm font-bold uppercase tracking-[0.14em] text-primary">{{ $data['eyebrow'] }}</p>
                @endif

                @if (! empty($data['heading']))
                    <h1 class="font-title text-4xl font-extrabold tracking-tight text-deep md:text-5xl">{{ $data['heading'] }}</h1>
                @endif

                @if (! empty($data['subheading']))
                    <p class="max-w-3xl whitespace-pre-line text-lg leading-8 text-text-muted">{{ $data['subheading'] }}</p>
                @endif

                @if (! empty($data['effective_date']))
                    <p class="text-sm font-semibold text-neutral-500">{{ __('Effective date:') }} {{ $data['effective_date'] }}</p>
                @endif
            </div>

            <div class="space-y-8">
                @foreach ($contentBlocks as $block)
                    <article class="border-t border-neutral-200 pt-8">
                        @if (! empty($block['heading']))
                            <h2 class="font-title text-2xl font-bold text-deep">{{ $block['heading'] }}</h2>
                        @endif

                        @if (! empty($block['body']))
                            <p class="mt-4 whitespace-pre-line text-base leading-8 text-text-muted">{{ $block['body'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
