@php $d = $section->data ?? []; @endphp
<section class="spy-section">
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

        @php
            $columns = $d['columns'] ?? [];
            $rows = $d['rows'] ?? [];
        @endphp
        @if (!empty($columns) && !empty($rows))
            <div class="compare-table-scroll mt-12 overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-neutral-200">
                            <th class="py-4 pr-4 font-title text-sm font-bold tracking-wide text-neutral-500 uppercase">{{ __('Feature') }}</th>
                            @foreach ($columns as $column)
                                <th class="px-4 py-4 text-center font-title text-base font-bold {{ !empty($column['highlighted']) ? 'text-primary' : 'text-title' }}">{{ $column['label'] ?? $column['key'] ?? '' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $rowIndex => $row)
                            <tr class="border-b border-neutral-100 {{ $rowIndex % 2 === 1 ? 'bg-section/60' : '' }}">
                                <td class="py-4 pr-4 text-sm font-medium text-title">{{ $row['feature'] ?? $row['label'] ?? '' }}</td>
                                @php
                                    $rowValues = collect($row['values'] ?? [])->keyBy('plan_key')->all();
                                @endphp
                                @foreach ($columns as $column)
                                    @php
                                        $planKey = $column['key'] ?? $column['label'] ?? '';
                                        $value = $rowValues[$planKey]['value'] ?? null;
                                    @endphp
                                    <td class="px-4 py-4 text-center">
                                        @if ($value === true || $value === 'yes' || $value === 'true')
                                            <span class="mx-auto grid h-5 w-5 place-items-center rounded-full bg-primary/10 text-primary"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                        @elseif ($value === false || $value === 'no' || $value === 'false' || $value === null || $value === '')
                                            <span class="text-sm text-neutral-300">—</span>
                                        @else
                                            <span class="text-sm text-body">{{ $value }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
