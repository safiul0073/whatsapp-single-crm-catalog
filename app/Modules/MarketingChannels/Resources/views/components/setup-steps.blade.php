@props(['steps' => [], 'title' => null])

@php
    $doneCount = collect($steps)->whereIn('state', ['done', 'skipped'])->count();
@endphp

<div {{ $attributes }} data-setup-steps>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="heading-5">{{ $title ?? __('Setup steps') }}</h3>
        <span class="text-xs font-semibold text-body">{{ __(':done of :total done', ['done' => $doneCount, 'total' => count($steps)]) }}</span>
    </div>

    <ol class="mt-4 divide-y divide-neutral-100">
        @foreach ($steps as $index => $step)
            @php
                $state = $step['state'];
                $bulletClass = match ($state) {
                    'done' => 'bg-success/10 text-success',
                    'current' => 'bg-primary text-neutral-0 shadow-[0_6px_18px_-8px] shadow-primary/50',
                    'skipped' => 'bg-section text-neutral-400',
                    default => 'border border-neutral-200 bg-neutral-0 text-neutral-400',
                };
            @endphp
            <li class="flex gap-4 py-4 first:pt-0 last:pb-0" data-step="{{ $step['key'] }}" data-step-state="{{ $state }}">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $bulletClass }}">
                    @if ($state === 'done')
                        <i class="ph ph-check text-base"></i>
                    @elseif ($state === 'skipped')
                        <i class="ph ph-minus text-base"></i>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold {{ $state === 'pending' ? 'text-body' : 'text-title' }}">{{ $step['label'] }}</p>
                        @if ($state === 'current')
                            <span class="badge badge-soft">{{ __('Next') }}</span>
                        @elseif ($state === 'skipped')
                            <span class="badge badge-neutral">{{ __('Optional') }}</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-sm text-body">{{ $step['description'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</div>
