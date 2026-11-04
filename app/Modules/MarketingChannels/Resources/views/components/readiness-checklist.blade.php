@props(['report' => null, 'channel' => null, 'title' => null])

@php
    $items = $report['items'] ?? [];
    $stateStyle = [
        'done' => ['bg-success/10 text-success', 'ph-check', null],
        'waiting' => ['bg-info/10 text-info', 'ph-hourglass-medium', __('Waiting for Meta')],
        'action' => ['bg-warning/10 text-warning', 'ph-hand-pointing', __('Your action')],
        'blocked' => ['bg-error/10 text-error', 'ph-prohibit', __('Blocking')],
        'optional' => ['bg-section text-neutral-400', 'ph-star', __('Recommended')],
        'unknown' => ['bg-section text-neutral-400', 'ph-question', __('Could not check')],
    ];
    $badge = ['waiting' => 'badge-info', 'action' => 'badge-warning', 'blocked' => 'badge-error', 'optional' => 'badge-neutral', 'unknown' => 'badge-neutral'];
    $checkedAt = filled($report['checked_at'] ?? null) ? \Illuminate\Support\Carbon::parse($report['checked_at']) : null;
@endphp

<div {{ $attributes->merge(['class' => 'section-card']) }} data-readiness-checklist="{{ $report['status'] ?? 'unchecked' }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="heading-5">{{ $title ?? __('What this channel still needs') }}</h3>
                <x-marketing-channels::readiness-badge :report="$report" />
            </div>
            @if ($report)
                <p class="mt-1 text-sm text-body" data-readiness-headline>{{ $report['headline'] }}</p>
                <p class="mt-0.5 text-xs text-body">
                    {{ __(':done of :total required steps done', ['done' => $report['done'], 'total' => $report['total']]) }}
                    @if ($checkedAt)
                        · {{ __('Checked :time', ['time' => $checkedAt->diffForHumans()]) }}
                    @endif
                </p>
            @else
                <p class="mt-1 text-sm text-body">{{ __('Not checked yet. Press Check now to see what Meta still needs from you.') }}</p>
            @endif
        </div>
        @if ($channel)
            <form method="POST" action="{{ route('user.channels.readiness', $channel) }}">
                @csrf
                <button type="submit" class="btn-sm btn-outline" data-readiness-recheck>
                    <i class="ph ph-arrows-clockwise text-base"></i>
                    {{ $report ? __('Re-check') : __('Check now') }}
                </button>
            </form>
        @endif
    </div>

    @if ($items !== [])
        <ol class="mt-4 divide-y divide-neutral-100 dark:divide-gray-800">
            @foreach ($items as $item)
                @php([$bullet, $icon, $stateLabel] = $stateStyle[$item['state']] ?? $stateStyle['unknown'])
                <li class="flex gap-3 py-3 first:pt-0 last:pb-0" data-readiness-item="{{ $item['key'] }}" data-readiness-state="{{ $item['state'] }}">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $bullet }}">
                        <i class="ph {{ $icon }} text-base"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-title">{{ $item['label'] }}</p>
                            @if ($stateLabel)
                                <span class="badge {{ $badge[$item['state']] ?? 'badge-neutral' }}">{{ $stateLabel }}</span>
                            @endif
                        </div>
                        @if ($item['state'] !== 'done')
                            <p class="mt-0.5 text-sm text-body">{{ $item['why'] }}</p>
                        @endif
                        @if ($item['detail'])
                            <p class="mt-0.5 text-xs font-medium text-body">{{ $item['detail'] }}</p>
                        @endif
                        @if ($item['state'] !== 'done' && $item['how'] !== [])
                            <details class="mt-2 rounded-md border border-neutral-100 px-3 py-2 dark:border-gray-800" @if ($item['state'] === 'blocked' || $item['state'] === 'action') open @endif>
                                <summary class="cursor-pointer text-xs font-semibold text-primary">{{ __('How to fix it') }}</summary>
                                <ol class="mt-2 list-decimal space-y-1 pl-4 text-sm text-body">
                                    @foreach ($item['how'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                        @if ($item['state'] !== 'done' && $item['link'])
                            <a href="{{ $item['link'] }}" target="_blank" rel="noopener" class="btn-sm btn-outline mt-2 inline-flex">
                                {{ $item['link_label'] }}
                                <i class="ph ph-arrow-square-out text-base"></i>
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if (($report['facts'] ?? []) !== [])
        <dl class="mt-4 grid gap-3 rounded-md bg-section p-3 text-sm sm:grid-cols-2">
            @foreach ($report['facts'] as $fact)
                <div class="min-w-0">
                    <dt class="text-xs text-body">{{ $fact['label'] }}</dt>
                    <dd class="font-medium text-title break-words">{{ $fact['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if (($report['notes'] ?? []) !== [])
        <ul class="mt-3 space-y-1 text-xs text-body">
            @foreach ($report['notes'] as $note)
                <li class="flex gap-1.5"><i class="ph ph-info mt-0.5 shrink-0"></i><span>{{ $note }}</span></li>
            @endforeach
        </ul>
    @endif
</div>
