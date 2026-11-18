<x-layouts.user :title="__('Shipping Profit/Loss')">
    @php $f = fn (?int $minor) => \App\Modules\Shipping\Services\ShippingReportService::format($minor, $precision); @endphp
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="heading-4 text-title">{{ __('Shipping Profit/Loss') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Customers pay :currency 0 for shipping. These internal figures are never shown to them. Amounts in :currency.', ['currency' => $currency]) }}</p>
            </div>
            @if($canManageCosts)<x-ui.button :href="route('user.shipping.product-costs.index')" variant="outline">{{ __('Product cost prices') }}</x-ui.button>@endif
        </div>

        <form method="GET" action="{{ route('user.shipping.reports.index') }}" class="section-card grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <div><label class="form-label" for="from">{{ __('From') }}</label><input id="from" class="form-input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
            <div><label class="form-label" for="to">{{ __('To') }}</label><input id="to" class="form-input" type="date" name="to" value="{{ $to->toDateString() }}">@error('to')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="period">{{ __('Group by') }}</label><select id="period" class="form-input" name="period">@foreach($periods as $value => $label)<option value="{{ $value }}" @selected($period === $value)>{{ __($label) }}</option>@endforeach</select></div>
            <div class="flex items-end"><x-forms.submit :label="__('Show report')" /></div>
        </form>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                [__('Estimated shipping'), $f($totals['estimated'])],
                [__('Actual shipping'), $f($totals['actual'])],
                [__('Cost difference'), $f($totals['difference'])],
                [__('Shipping cost per set'), $f($totals['cost_per_set'])],
                [__('Shipping as % of revenue'), $totals['percent_of_revenue'] !== null ? $totals['percent_of_revenue'].'%' : '—'],
                [__('Order profit'), $f($totals['profit'])],
            ] as [$label, $value])
                <div class="section-card"><span class="text-sm text-body">{{ $label }}</span><span class="heading-5 block text-title">{{ $value }}</span></div>
            @endforeach
        </div>
        @if($totals['profit_incomplete'] > 0)
            <p class="text-sm text-body"><i class="ph ph-info"></i> {{ __('Profit leaves out :count order(s) that have no billed shipping cost yet or are missing a product cost price.', ['count' => $totals['profit_incomplete']]) }}</p>
        @endif

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __($periods[$period]) }} {{ __('totals') }}</h2>
            @if($buckets->isEmpty())
                <p class="py-6 text-center text-body">{{ __('No shipments in this period.') }}</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Period') }}</th><th class="text-right">{{ __('Orders') }}</th><th class="text-right">{{ __('Revenue') }}</th><th class="text-right">{{ __('Estimated') }}</th><th class="text-right">{{ __('Actual') }}</th><th class="text-right">{{ __('Difference') }}</th><th class="text-right">{{ __('Per set') }}</th><th class="text-right">{{ __('% of revenue') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                            @foreach($buckets as $bucket)
                                <tr>
                                    <td class="font-semibold text-title">{{ $bucket['label'] }}</td>
                                    <td class="text-right">{{ $bucket['orders'] }}</td>
                                    <td class="text-right">{{ $f($bucket['revenue']) }}</td>
                                    <td class="text-right">{{ $f($bucket['estimated']) }}</td>
                                    <td class="text-right">{{ $f($bucket['actual']) }}</td>
                                    <td @class(['text-right', 'text-error' => $bucket['difference'] > 0])>{{ $f($bucket['difference']) }}</td>
                                    <td class="text-right">{{ $f($bucket['cost_per_set']) }}</td>
                                    <td class="text-right">{{ $bucket['percent_of_revenue'] !== null ? $bucket['percent_of_revenue'].'%' : '—' }}</td>
                                    <td class="text-right">{{ $f($bucket['profit']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('By order') }}</h2>
            @if($rows->isEmpty())
                <p class="py-6 text-center text-body">{{ __('No shipments in this period.') }}</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Order') }}</th><th class="text-right">{{ __('Sets') }}</th><th class="text-right">{{ __('Revenue') }}</th><th class="text-right">{{ __('Product cost') }}</th><th class="text-right">{{ __('Estimated') }}</th><th class="text-right">{{ __('Actual') }}</th><th class="text-right">{{ __('Difference') }}</th><th class="text-right">{{ __('Per set') }}</th><th class="text-right">{{ __('% of revenue') }}</th><th class="text-right">{{ __('Other costs') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td><span class="font-semibold text-title">{{ $row['order']->number }}</span>@if($row['needs_review']) <span class="badge badge-error">{{ __('Review') }}</span>@endif<span class="block text-xs text-body">{{ $row['date']->format('d M Y') }} · {{ trans_choice(':count shipment|:count shipments', $row['shipments'], ['count' => $row['shipments']]) }}</span></td>
                                    <td class="text-right">{{ $row['sets'] }}</td>
                                    <td class="text-right">{{ $f($row['revenue']) }}</td>
                                    <td class="text-right">@if($row['product_cost'] === null)<span class="badge badge-warning">{{ __('Missing') }}</span>@else{{ $f($row['product_cost']) }}@endif</td>
                                    <td class="text-right">{{ $f($row['estimated']) }}</td>
                                    <td class="text-right">{{ $f($row['actual']) }}</td>
                                    <td @class(['text-right', 'text-error' => ($row['difference'] ?? 0) > 0])>{{ $f($row['difference']) }}</td>
                                    <td class="text-right">{{ $f($row['cost_per_set']) }}</td>
                                    <td class="text-right">{{ $row['percent_of_revenue'] !== null ? $row['percent_of_revenue'].'%' : '—' }}</td>
                                    <td class="text-right">
                                        @if($canManageCosts)
                                            <form method="POST" action="{{ route('user.shipping.other-costs.update', $row['order']) }}" class="flex justify-end gap-1">@csrf @method('PUT')<input class="form-input form-input-sm w-24 text-right" type="number" step="0.01" min="0" name="other_costs" value="{{ number_format($row['other_costs'] / (10 ** $precision), $precision, '.', '') }}" aria-label="{{ __('Other costs for order :number', ['number' => $row['order']->number]) }}"><button class="btn btn-ghost btn-sm" type="submit" aria-label="{{ __('Save other costs') }}"><i class="ph ph-check"></i></button></form>
                                        @else
                                            {{ $f($row['other_costs']) }}
                                        @endif
                                    </td>
                                    <td @class(['text-right font-semibold', 'text-error' => ($row['profit'] ?? 0) < 0])>{{ $row['profit'] === null ? __('Incomplete') : $f($row['profit']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="form-hint">{{ __('Order profit = sales revenue − product cost − actual shipping cost − other costs.') }}</p>
            @endif
        </section>
    </div>
</x-layouts.user>
