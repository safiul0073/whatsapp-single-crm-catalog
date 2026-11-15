@php
    $money = fn (int $minor) => \App\Modules\Commerce\Services\OrderMoney::decimal($minor, $precision);
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $card = 'rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition';
    $danger = 'inline-flex items-center gap-1.5 rounded-xl bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-500/20 transition dark:text-rose-300';
    $labels = [
        'pending_referrer' => __('Pending referrer review'),
        'referrer_rejected' => __('Rejected by referrer'),
        'awaiting_admin' => __('Awaiting your decision'),
        'processing' => __('Processing'),
        'completed' => __('Completed'),
        'admin_rejected' => __('Rejected by you'),
    ];
    $actors = ['customer' => __('Customer'), 'referrer' => __('Referrer'), 'admin' => __('Store')];
    $row = 'flex justify-between gap-4 py-2 text-xs';
@endphp
<x-layouts.user :title="__('Refund :reference', ['reference' => $refund->reference])">
    <div class="space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('user.commerce.refunds.index') }}" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300"><i class="ph ph-arrow-left"></i> {{ __('Refund Management') }}</a>
                <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $refund->reference }}</h1>
            </div>
            <span class="self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $labels[$refund->status] ?? $refund->status }}</span>
        </header>

        @if(session('success'))
            <div class="rounded-2xl bg-emerald-500/10 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-2xl bg-rose-500/10 p-4 text-xs font-medium text-rose-800 dark:text-rose-300">
                <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="{{ $card }}">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Request') }}</h2>
                    <div class="mt-2 divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Requested amount') }}</span><span class="font-semibold">{{ $refund->currency }} {{ $money($refund->amount_minor) }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Reason') }}</span><span>{{ __(ucfirst(str_replace('_', ' ', $refund->reason))) }}</span></div>
                        @if($refund->details)<div class="py-2 text-xs"><span class="text-slate-500">{{ __('Customer explanation') }}</span><p class="mt-1 whitespace-pre-line">{{ $refund->details }}</p></div>@endif
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Customer') }}</span><span>{{ $refund->customer?->contact?->name }} · {{ $refund->customer?->contact?->phone }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Referrer') }}</span><span>{{ $refund->referrer ? $refund->referrer->contact?->name.' · '.$refund->referrer->contact?->phone : __('No referrer') }}</span></div>
                        @if($refund->referrer_decision)
                            <div class="py-2 text-xs"><span class="text-slate-500">{{ __('Referrer review') }}</span><p class="mt-1"><span class="font-semibold">{{ __(ucfirst($refund->referrer_decision)) }}</span>@if($refund->referrer_comment) — {{ $refund->referrer_comment }}@endif</p></div>
                        @endif
                        @if($refund->status === 'completed')
                            <div class="{{ $row }}"><span class="text-slate-500">{{ __('Refund sent by') }}</span><span>{{ $refund->payout_method === 'wallet' ? __('Wallet credit') : ($channels[$refund->payout_channel] ?? $refund->payout_channel).' · '.$refund->payout_reference }}</span></div>
                            <div class="{{ $row }}"><span class="text-slate-500">{{ __('Commission taken back') }}</span><span>{{ $refund->currency }} {{ $money($refund->commission_reversed_minor) }}</span></div>
                            @if($refund->commission_shortfall_minor > 0)
                                <div class="{{ $row }}"><span class="text-slate-500">{{ __('Commission not recovered (referrer balance too low)') }}</span><span class="font-semibold text-rose-600">{{ $refund->currency }} {{ $money($refund->commission_shortfall_minor) }}</span></div>
                            @endif
                        @endif
                    </div>
                </section>

                <section class="{{ $card }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Order :number', ['number' => $refund->order->number]) }}</h2>
                        <a href="{{ route('user.commerce.orders.show', $refund->order) }}" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ __('Open order') }}</a>
                    </div>
                    <div class="mt-2 divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @foreach($refund->order->items as $item)
                            <div class="{{ $row }}"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>{{ $refund->order->currency }} {{ $item->line_total }}</span></div>
                        @endforeach
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Order total') }}</span><span class="font-semibold">{{ $refund->order->currency }} {{ $refund->order->total }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Payment') }}</span><span>{{ __(ucfirst(str_replace('_', ' ', $refund->order->payment_state))) }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Already refunded') }}</span><span>{{ $refund->order->currency }} {{ $refund->order->refunded_amount }}</span></div>
                        <div class="{{ $row }}"><span class="text-slate-500">{{ __('Still refundable') }}</span><span>{{ $refund->order->currency }} {{ $money($refundable) }}</span></div>
                    </div>
                </section>

                <section class="{{ $card }}">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('History') }}</h2>
                    <ol class="mt-3 space-y-3">
                        @foreach($refund->events as $event)
                            <li class="text-xs text-slate-700 dark:text-slate-200">
                                <span class="font-semibold">{{ $actors[$event->actor_type] ?? $event->actor_type }}</span>
                                → {{ $labels[$event->to_status] ?? $event->to_status }}
                                <span class="text-slate-400">· {{ $event->created_at?->format('d M Y H:i') }}</span>
                                @if($event->comment)<p class="mt-0.5 text-slate-500">{{ $event->comment }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="space-y-6">
                @if($canManage && $allowed !== [])
                    @if(in_array('processing', $allowed, true))
                        <form method="POST" action="{{ route('user.commerce.refunds.transition', $refund->id) }}" class="{{ $card }} space-y-3">
                            @csrf @method('PUT')
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Approve refund') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Moves the request to Processing. No money moves until you complete it.') }}</p>
                            <input type="hidden" name="status" value="processing">
                            <div><label class="{{ $label }}">{{ __('Note (optional)') }}</label><textarea name="admin_note" rows="2" class="{{ $input }}"></textarea></div>
                            <button class="{{ $primary }}">{{ __('Approve and start processing') }}</button>
                        </form>
                    @endif
                    @if(in_array('completed', $allowed, true))
                        <form method="POST" action="{{ route('user.commerce.refunds.transition', $refund->id) }}" class="{{ $card }} space-y-3" x-data="{ method: '{{ old('payout_method', 'manual') }}' }">
                            @csrf @method('PUT')
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Complete refund') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Updates the order payment, the wallet ledger and the referral commission. This cannot be undone.') }}</p>
                            <input type="hidden" name="status" value="completed">
                            <div>
                                <label class="{{ $label }}">{{ __('How is the money returned?') }}</label>
                                <select name="payout_method" x-model="method" class="{{ $input }}">
                                    <option value="manual">{{ __('I sent it myself (bKash, Nagad, Rocket or bank)') }}</option>
                                    <option value="wallet">{{ __('Credit the customer\'s wallet') }}</option>
                                </select>
                            </div>
                            <div x-show="method === 'manual'" class="space-y-3">
                                <div><label class="{{ $label }}">{{ __('Sent through') }}</label><select name="payout_channel" class="{{ $input }}" :disabled="method !== 'manual'">@foreach($channels as $code => $name)<option value="{{ $code }}" @selected(old('payout_channel') === $code)>{{ $name }}</option>@endforeach</select></div>
                                <div><label class="{{ $label }}">{{ __('Transaction ID') }}</label><input name="payout_reference" value="{{ old('payout_reference') }}" maxlength="120" class="{{ $input }}" :disabled="method !== 'manual'"></div>
                            </div>
                            <div><label class="{{ $label }}">{{ __('Note (optional)') }}</label><textarea name="admin_note" rows="2" class="{{ $input }}"></textarea></div>
                            <button class="{{ $primary }}">{{ __('Complete refund of :amount', ['amount' => $refund->currency.' '.$money($refund->amount_minor)]) }}</button>
                        </form>
                    @endif
                    @if(in_array('admin_rejected', $allowed, true))
                        <form method="POST" action="{{ route('user.commerce.refunds.transition', $refund->id) }}" class="{{ $card }} space-y-3">
                            @csrf @method('PUT')
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Reject refund') }}</h2>
                            <input type="hidden" name="status" value="admin_rejected">
                            <div><label class="{{ $label }}">{{ __('Reason shown to the customer') }}</label><textarea name="admin_note" rows="2" required maxlength="2000" class="{{ $input }}"></textarea></div>
                            <button class="{{ $danger }}">{{ __('Reject request') }}</button>
                        </form>
                    @endif
                @else
                    <div class="{{ $card }} text-xs text-slate-500">{{ $allowed === [] ? __('This request is closed.') : __('You can view this request but not change it.') }}</div>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.user>
