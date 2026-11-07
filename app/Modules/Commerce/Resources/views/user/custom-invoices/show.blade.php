@php
    $statusVariant = ['draft' => 'secondary', 'sent' => 'info', 'paid' => 'success', 'cancelled' => 'danger'];
    $precision = $invoice->moneyPrecision();
    $money = fn ($amount): string => $invoice->currency.' '.\App\Modules\Commerce\Services\OrderMoney::decimal(\App\Modules\Commerce\Services\OrderMoney::minor($amount ?? 0, $precision), $precision);
    $isZero = fn ($amount): bool => \App\Modules\Commerce\Services\OrderMoney::minor($amount ?? 0, $precision) === 0;
@endphp
<x-layouts.user :title="$invoice->number">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('user.commerce.custom-invoices.index') }}" class="text-sm text-primary hover:underline">← {{ __('Custom invoices') }}</a>
                <h1 class="heading-3 text-title font-bold flex items-center gap-3">
                    {{ $invoice->number }}
                    <x-ui.badge :variant="$statusVariant[$invoice->status] ?? 'secondary'">{{ __(ucfirst($invoice->status)) }}</x-ui.badge>
                </h1>
                <p class="mt-1 text-sm text-body">
                    {{ __('Created :date', ['date' => $invoice->created_at->format('d M Y')]) }}
                    @if ($invoice->sent_at) · {{ __('Sent on WhatsApp :date', ['date' => $invoice->sent_at->format('d M Y H:i')]) }} @endif
                    @if ($invoice->paid_at) · {{ __('Paid :date', ['date' => $invoice->paid_at->format('d M Y')]) }} @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button size="sm" variant="outline" href="{{ route('user.commerce.custom-invoices.pdf', $invoice) }}" target="_blank">
                    <i class="ph ph-file-pdf text-base"></i><span>{{ __('PDF') }}</span>
                </x-ui.button>
                @can('commerce.invoices.manage')
                    @if ($invoice->isDraft())
                        <x-ui.button size="sm" variant="outline" href="{{ route('user.commerce.custom-invoices.edit', $invoice) }}">
                            <i class="ph ph-pencil-simple text-base"></i><span>{{ __('Edit') }}</span>
                        </x-ui.button>
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.transition', $invoice) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="sent">
                            <x-ui.button type="submit" size="sm"><i class="ph ph-check text-base"></i><span>{{ __('Issue') }}</span></x-ui.button>
                        </form>
                    @endif
                    @if ($invoice->status !== 'cancelled')
                        @if ($invoice->customer_id)
                            <form method="POST" action="{{ route('user.commerce.custom-invoices.send', $invoice) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="outline"><i class="ph ph-whatsapp-logo text-base"></i><span>{{ $invoice->isDraft() ? __('Issue & send') : __('Send on WhatsApp') }}</span></x-ui.button>
                            </form>
                        @endif
                        @unless ($invoice->isDraft())
                            <button type="button" class="btn btn-outline btn-sm" data-copy-invoice-link="{{ $invoice->publicUrl() }}" data-copied-label="{{ __('Copied') }}">
                                <i class="ph ph-link text-base"></i><span>{{ __('Copy link') }}</span>
                            </button>
                        @endunless
                    @endif
                    @if ($invoice->status === 'sent')
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.transition', $invoice) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="paid">
                            <x-ui.button type="submit" size="sm" variant="success"><i class="ph ph-currency-circle-dollar text-base"></i><span>{{ __('Mark paid') }}</span></x-ui.button>
                        </form>
                    @endif
                    @if ($invoice->status === 'paid')
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.transition', $invoice) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="sent">
                            <x-ui.button type="submit" size="sm" variant="outline">{{ __('Mark unpaid') }}</x-ui.button>
                        </form>
                    @endif
                    @if (in_array($invoice->status, ['draft', 'sent'], true))
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.transition', $invoice) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="cancelled">
                            <x-ui.button type="submit" size="sm" variant="outline" class="text-danger">{{ __('Cancel invoice') }}</x-ui.button>
                        </form>
                    @endif
                    @if ($invoice->isDraft())
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.destroy', $invoice) }}">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" size="sm" variant="outline" class="text-danger"><i class="ph ph-trash text-base"></i><span>{{ __('Delete draft') }}</span></x-ui.button>
                        </form>
                    @endif
                @endcan
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card p-5 lg:col-span-2 space-y-4">
                <h2 class="heading-5">{{ __('Lines') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-border-soft text-xs font-semibold uppercase tracking-wider text-neutral-600">
                                <th class="py-2 pr-4">{{ __('Item') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('Qty') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('Unit price') }}</th>
                                <th class="py-2 text-right">{{ __('Line total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <p class="text-title">{{ $item->name }}</p>
                                        <p class="text-xs text-body">{{ $item->description ?? ($item->product_id ? __('Catalog product') : __('Custom line')) }}</p>
                                    </td>
                                    <td class="py-2 pr-4 text-right">{{ $item->quantity }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($item->unit_price) }}</td>
                                    <td class="py-2 text-right font-semibold">{{ $money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="ml-auto max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-body">{{ __('Subtotal') }}</dt><dd>{{ $money($invoice->subtotal) }}</dd></div>
                    @unless ($isZero($invoice->discount_amount))<div class="flex justify-between"><dt class="text-body">{{ __('Discount') }}</dt><dd>-{{ $money($invoice->discount_amount) }}</dd></div>@endunless
                    @unless ($isZero($invoice->tax_amount))<div class="flex justify-between"><dt class="text-body">{{ __('Tax') }}</dt><dd>{{ $money($invoice->tax_amount) }}</dd></div>@endunless
                    @unless ($isZero($invoice->shipping_amount))<div class="flex justify-between"><dt class="text-body">{{ __('Shipping') }}</dt><dd>{{ $money($invoice->shipping_amount) }}</dd></div>@endunless
                    <div class="flex justify-between border-t border-border-soft pt-2 text-base font-bold text-title"><dt>{{ __('Total') }}</dt><dd>{{ $money($invoice->total) }}</dd></div>
                </dl>
            </section>

            <aside class="space-y-6">
                <section class="card p-5 space-y-2 text-sm">
                    <h2 class="heading-5">{{ __('Bill to') }}</h2>
                    <p class="font-semibold text-title">{{ $invoice->bill_to_name }}</p>
                    @if ($invoice->bill_to_phone)<p class="text-body">{{ $invoice->bill_to_phone }}</p>@endif
                    @if ($invoice->bill_to_email)<p class="text-body">{{ $invoice->bill_to_email }}</p>@endif
                    @if ($invoice->bill_to_address)<p class="text-body whitespace-pre-line">{{ $invoice->bill_to_address }}</p>@endif
                    @if ($invoice->customer_id)
                        <p class="text-xs text-success"><i class="ph ph-user-check"></i> {{ __('Visible in the customer\'s account once issued') }}</p>
                    @endif
                    @if ($invoice->due_date)<p class="text-body">{{ __('Due :date', ['date' => $invoice->due_date->format('d M Y')]) }}</p>@endif
                </section>
                @if ($invoice->notes)
                    <section class="card p-5 space-y-2 text-sm">
                        <h2 class="heading-5">{{ __('Notes') }}</h2>
                        <p class="text-body whitespace-pre-line">{{ $invoice->notes }}</p>
                    </section>
                @endif
                @if ($invoice->request)
                    <section class="card p-5 space-y-2 text-sm">
                        <h2 class="heading-5">{{ __('Customer request') }}</h2>
                        <p class="text-body whitespace-pre-line">{{ $invoice->request->message }}</p>
                    </section>
                @endif
                @can('commerce.invoices.manage')
                    @unless ($invoice->isDraft())
                        <form method="POST" action="{{ route('user.commerce.custom-invoices.link', $invoice) }}" class="text-sm">
                            @csrf
                            <button type="submit" class="text-primary hover:underline">{{ __('Create a new private link') }}</button>
                        </form>
                    @endunless
                @endcan
            </aside>
        </div>
    </div>
</x-layouts.user>
