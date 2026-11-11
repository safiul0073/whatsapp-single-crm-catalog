<x-layouts.document :title="$invoice['number'].' · '.__('Invoice')" :back="$invoice['back_url']" :back-label="__('Back to subscription')" format="a4">
    <header class="grid grid-cols-2 items-start gap-4 border-b border-border-default pb-4 text-sm">
        <div class="flex min-w-0 items-start gap-3">
            @if($invoice['brand']['logo'])
                <img src="{{ $invoice['brand']['logo'] }}" alt="{{ $invoice['brand']['name'] }}" class="h-10 w-auto max-w-28 object-contain">
            @endif
            <div class="min-w-0 leading-snug">
                <h1 class="text-lg font-semibold leading-tight">{{ $invoice['brand']['name'] }}</h1>
            </div>
        </div>
        <div class="text-right leading-snug">
            <p class="text-xl font-semibold tracking-wide">{{ __('INVOICE') }}</p>
            <p class="font-medium">{{ $invoice['number'] }}</p>
            <p class="text-body">{{ $invoice['date'] }}</p>
            <p class="text-body">{{ __('Status') }}: {{ $invoice['status'] }}</p>
        </div>
    </header>

    <section class="mt-4 grid grid-cols-2 gap-4 text-sm">
        <div class="leading-snug">
            <p class="document-label">{{ __('Bill to') }}</p>
            <p class="font-medium">{{ $invoice['bill_to']['workspace'] }}</p>
            <p class="text-body">{{ $invoice['bill_to']['name'] }}</p>
            <p class="break-all text-body">{{ $invoice['bill_to']['email'] }}</p>
        </div>
        <div class="text-right leading-snug">
            <p class="document-label">{{ __('Payment') }}</p>
            <p class="font-medium">{{ $invoice['gateway'] }}</p>
            <p class="break-all text-body">{{ $invoice['reference'] }}</p>
        </div>
    </section>

    <table class="document-table mt-6">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice['lines'] as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-right tabular-nums">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="font-semibold">{{ __('Total paid') }}</td>
                <td class="text-right font-semibold tabular-nums">{{ $invoice['total'] }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="mt-8 text-xs text-body">{{ __('Thank you for your business.') }}</p>
</x-layouts.document>
