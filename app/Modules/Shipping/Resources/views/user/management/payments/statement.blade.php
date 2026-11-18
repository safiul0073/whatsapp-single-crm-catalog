<x-layouts.user :title="__('Statement — :agent', ['agent' => $agent->name])">
    @php $amount = fn ($value) => $value === null ? '' : number_format((float) $value, 2); @endphp
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', [
            'heading' => __('Statement — :agent', ['agent' => $agent->name]),
            'intro' => __('Invoices and payments in :currency, :from to :to.', ['currency' => $agent->currency, 'from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]),
            'back' => ['href' => route('user.shipping.payments.index'), 'label' => __('Back to agent payments')],
        ])

        <form method="GET" action="{{ route('user.shipping.payments.statement', $agent) }}" class="section-card flex flex-wrap items-end gap-3">
            <div><label class="form-label" for="from">{{ __('From') }}</label><input id="from" class="form-input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
            <div><label class="form-label" for="to">{{ __('To') }}</label><input id="to" class="form-input" type="date" name="to" value="{{ $to->toDateString() }}"></div>
            <x-forms.submit :label="__('Show')" />
        </form>

        <section class="section-card">
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-body">{{ __('Opening balance') }}</dt><dd class="font-semibold text-title">{{ $agent->currency }} {{ $amount($statement['opening']) }}</dd></div>
                <div><dt class="text-body">{{ __('Billed') }}</dt><dd class="font-semibold text-title">{{ $agent->currency }} {{ $amount($statement['billed']) }}</dd></div>
                <div><dt class="text-body">{{ __('Paid') }}</dt><dd class="font-semibold text-title">{{ $agent->currency }} {{ $amount($statement['paid']) }}</dd></div>
                <div><dt class="text-body">{{ __('Closing balance') }}</dt><dd class="font-semibold text-title">{{ $agent->currency }} {{ $amount($statement['closing']) }}</dd></div>
            </dl>

            @if($statement['entries']->isEmpty())
                <p class="mt-4 text-sm text-body">{{ __('Nothing in this period.') }}</p>
            @else
                <div class="table-responsive mt-4">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Details') }}</th><th class="text-right">{{ __('Billed') }}</th><th class="text-right">{{ __('Paid') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
                        <tbody>
                            @foreach($statement['entries'] as $entry)
                                <tr>
                                    <td>{{ $entry['date']->format('d M Y') }}</td>
                                    <td>
                                        @if($entry['type'] === 'invoice')<a class="text-primary" href="{{ route('user.shipping.invoices.show', $entry['model']) }}">{{ $entry['description'] }}</a>@else{{ $entry['description'] }}@endif
                                    </td>
                                    <td class="text-right">{{ $amount($entry['debit']) }}</td>
                                    <td class="text-right">{{ $amount($entry['credit']) }}</td>
                                    <td class="text-right font-semibold text-title">{{ $amount($entry['balance']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="form-hint">{{ __('A negative balance means the agent holds a credit.') }}</p>
        </section>
    </div>
</x-layouts.user>
