@php
    $statusVariant = ['draft' => 'secondary', 'sent' => 'info', 'paid' => 'success', 'cancelled' => 'danger'];
@endphp
<x-layouts.user :title="__('Custom invoices')">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider uppercase text-primary">{{ __('Commerce Management') }}</p>
                <h1 class="heading-3 text-title font-bold">{{ __('Custom invoices') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Bill customers for anything: catalog products, services, fees or custom work.') }}</p>
            </div>
            @can('commerce.invoices.manage')
                <x-ui.button size="sm" href="{{ route('user.commerce.custom-invoices.create') }}">
                    <i class="ph ph-plus text-base"></i>
                    <span>{{ __('New invoice') }}</span>
                </x-ui.button>
            @endcan
        </header>

        @if ($requests->isNotEmpty())
            <section class="card p-4 sm:p-5 space-y-3">
                <div class="flex items-center gap-2">
                    <i class="ph ph-envelope-simple-open text-lg text-primary"></i>
                    <h2 class="heading-5">{{ __('Invoice requests from customers') }}</h2>
                    <x-ui.badge variant="warning">{{ $requests->count() }}</x-ui.badge>
                </div>
                <ul class="divide-y divide-border-soft">
                    @foreach ($requests as $invoiceRequest)
                        <li class="flex flex-col gap-3 py-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-semibold text-title">
                                    {{ $invoiceRequest->customer?->contact?->name ?? $invoiceRequest->customer?->verified_phone ?? __('Customer') }}
                                    <span class="font-normal text-body">· {{ $invoiceRequest->customer?->contact?->phone ?? $invoiceRequest->customer?->verified_phone }}</span>
                                </p>
                                <p class="text-sm text-body whitespace-pre-line">{{ $invoiceRequest->message }}</p>
                                @if (! empty($invoiceRequest->items))
                                    <p class="text-xs text-body">
                                        {{ __('Products:') }}
                                        {{ collect($invoiceRequest->items)->map(fn ($item) => ($item['name'] ?? '#'.$item['product_id']).' × '.$item['quantity'])->implode(', ') }}
                                    </p>
                                @endif
                                <p class="text-xs text-neutral-500">{{ $invoiceRequest->created_at->diffForHumans() }}</p>
                            </div>
                            @can('commerce.invoices.manage')
                                <div class="flex shrink-0 flex-wrap items-start gap-2">
                                    <x-ui.button size="sm" href="{{ route('user.commerce.custom-invoices.create', ['request' => $invoiceRequest->id]) }}">
                                        <i class="ph ph-receipt text-base"></i>
                                        <span>{{ __('Create invoice') }}</span>
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('user.commerce.custom-invoices.requests.reject', $invoiceRequest) }}" class="flex items-start gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="owner_note" maxlength="1000" class="form-input h-9 text-sm" placeholder="{{ __('Reason (optional)') }}" aria-label="{{ __('Reason for declining') }}">
                                        <x-ui.button type="submit" size="sm" variant="outline">{{ __('Decline') }}</x-ui.button>
                                    </form>
                                </div>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="card p-4 sm:p-5">
            <form method="GET" action="{{ route('user.commerce.custom-invoices.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input type="search" name="search" value="{{ $search }}" class="form-input flex-1" placeholder="{{ __('Search number, name or phone') }}" aria-label="{{ __('Search invoices') }}">
                <select name="status" class="form-input sm:w-48" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Modules\Commerce\Models\CustomInvoice::STATUSES as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ __(ucfirst($option)) }}</option>
                    @endforeach
                </select>
                <x-ui.button type="submit" size="sm" variant="outline">{{ __('Filter') }}</x-ui.button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-border-soft bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <th class="py-3 px-4">{{ __('Invoice') }}</th>
                            <th class="py-3 px-4">{{ __('Bill to') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Lines') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Total') }}</th>
                            <th class="py-3 px-4">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse ($invoices as $invoice)
                            <tr class="hover:bg-neutral-50/70 transition-colors">
                                <td class="py-3 px-4">
                                    <a href="{{ route('user.commerce.custom-invoices.show', $invoice) }}" class="font-semibold text-primary hover:underline">{{ $invoice->number }}</a>
                                    @if ($invoice->invoice_request_id)
                                        <span class="ml-1 text-xs text-body">{{ __('(requested)') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <p class="text-title">{{ $invoice->bill_to_name }}</p>
                                    <p class="text-xs text-body">{{ $invoice->bill_to_phone }}</p>
                                </td>
                                <td class="py-3 px-4"><x-ui.badge :variant="$statusVariant[$invoice->status] ?? 'secondary'">{{ __(ucfirst($invoice->status)) }}</x-ui.badge></td>
                                <td class="py-3 px-4">{{ $invoice->items_count }}</td>
                                <td class="py-3 px-4 text-right font-semibold">{{ $invoice->currency }} {{ \App\Modules\Commerce\Services\OrderMoney::decimal(\App\Modules\Commerce\Services\OrderMoney::minor($invoice->total, $invoice->moneyPrecision()), $invoice->moneyPrecision()) }}</td>
                                <td class="py-3 px-4 text-body">{{ ($invoice->issued_at ?? $invoice->created_at)->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 px-4 text-center text-body">{{ __('No custom invoices yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $invoices->links() }}</div>
        </div>
    </div>
</x-layouts.user>
