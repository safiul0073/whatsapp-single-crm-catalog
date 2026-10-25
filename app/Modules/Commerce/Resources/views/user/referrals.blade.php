@php
    $money = fn (int $minor) => \App\Modules\Commerce\Services\OrderMoney::decimal($minor, $precision);
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $card = 'rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 transition';
    $badge = fn (string $s) => match ($s) {
        'credited', 'paid', 'approved' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        default => 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    };
    $tabs = ['settings' => __('Settings'), 'tracking' => __('Tracking'), 'slabs' => __('Target Slabs'), 'rules' => __('Customer Rules'), 'wallets' => __('Reward Wallets')];
    // Withdrawals are no longer offered; the tab only stays so requests made earlier can still be settled.
    if ($cashins->total() > 0 || request()->filled('cashin_status')) {
        $tabs['cashins'] = __('Cash-in Requests');
    }
    $activeTab = array_key_exists((string) request('tab'), $tabs) ? request('tab') : 'settings';
@endphp
<x-layouts.user :title="__('Referral Program')">
    <div x-data="{ tab: '{{ $activeTab }}', rewardType: '{{ old('reward_type', $settings->reward_type) }}', refereeType: '{{ old('referee_discount_type', $settings->referee_discount_type) }}' }" class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20">
                    <i class="ph ph-users-three text-2xl"></i>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Referral Program') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Customers invite friends with their code or link and earn reward balance they can spend on purchases.') }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-xs font-semibold {{ $settings->enabled ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                <i class="ph {{ $settings->enabled ? 'ph-check-circle' : 'ph-pause-circle' }}"></i>
                {{ $settings->enabled ? __('Program active') : __('Program off') }}
            </span>
        </header>

        <div class="grid grid-cols-2 {{ $stats['cashin_pending'] > 0 ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-3">
            <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ __('Total referral orders') }}</span><span class="text-2xl font-bold text-slate-900 dark:text-white mt-1 block">{{ $stats['total'] }}</span></div>
            <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ __('Pending (awaiting delivery)') }}</span><span class="text-2xl font-bold text-amber-600 mt-1 block">{{ $stats['pending'] }}</span></div>
            <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ __('Credited') }}</span><span class="text-2xl font-bold text-emerald-600 mt-1 block">{{ $stats['credited'] }}</span></div>
            <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ __('Rewards credited (incl. bonuses)') }}</span><span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $currency }} {{ $stats['rewarded'] }}</span></div>
            @if($stats['cashin_pending'] > 0)
            <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ __('Pending cash-in') }}</span><span class="text-2xl font-bold text-slate-900 dark:text-white mt-1 block">{{ $stats['cashin_pending'] }} <span class="text-sm font-semibold text-slate-500">· {{ $currency }} {{ $stats['cashin_pending_amount'] }}</span></span></div>
            @endif
        </div>

        @if(session('success'))
            <div class="rounded-2xl bg-emerald-500/10 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5"><i class="ph ph-check-circle text-lg text-emerald-600"></i><span>{{ session('success') }}</span></div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-2xl bg-rose-500/10 p-4 text-xs font-medium text-rose-800 dark:text-rose-300">
                <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-xs dark:bg-slate-900">
            @foreach($tabs as $key => $name)
                <button type="button" x-on:click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'" class="whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold transition">{{ $name }}</button>
            @endforeach
        </nav>

        {{-- Settings --}}
        <form x-show="tab === 'settings'" method="POST" action="{{ route('user.commerce.referrals.settings') }}" class="space-y-5">
            @csrf @method('PUT')
            <section class="{{ $card }} space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('General') }}</h2>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled)) class="rounded border-slate-300 text-emerald-600">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ __('Enable referral program') }}</span>
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('When does the referrer earn?') }}</label>
                        <select name="reward_trigger" class="{{ $input }}">
                            <option value="delivered_order" @selected(old('reward_trigger', $settings->reward_trigger) === 'delivered_order')>{{ __('When the referred friend\'s order is delivered (recommended)') }}</option>
                            <option value="signup" @selected(old('reward_trigger', $settings->reward_trigger) === 'signup')>{{ __('Immediately when the friend registers') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Reward which orders?') }}</label>
                        <select name="reward_scope" class="{{ $input }}">
                            <option value="first_order" @selected(old('reward_scope', $settings->reward_scope) === 'first_order')>{{ __('First order only') }}</option>
                            <option value="every_order" @selected(old('reward_scope', $settings->reward_scope) === 'every_order')>{{ __('Every order of the referred friend') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Max referrals per customer') }}</label>
                        <input type="number" min="1" name="max_referrals_per_customer" value="{{ old('max_referrals_per_customer', $settings->max_referrals_per_customer) }}" placeholder="{{ __('Unlimited') }}" class="{{ $input }}">
                    </div>
                </div>
            </section>

            <section class="{{ $card }} space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Referrer reward') }}</h2>
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Reward type') }}</label>
                        <select name="reward_type" x-model="rewardType" class="{{ $input }}">
                            <option value="fixed">{{ __('Fixed amount') }}</option>
                            <option value="percent">{{ __('Percentage of order') }}</option>
                            <option value="percent_capped">{{ __('Percentage (capped)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}"><span x-text="rewardType === 'fixed' ? '{{ __('Amount') }} ({{ $currency }})' : '{{ __('Percent') }} (%)'"></span></label>
                        <input type="number" step="0.01" min="0" name="reward_value" value="{{ old('reward_value', (float) $settings->reward_value) }}" class="{{ $input }}">
                    </div>
                    <div x-show="rewardType === 'percent_capped'">
                        <label class="{{ $label }}">{{ __('Cap') }} ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" name="reward_cap" value="{{ old('reward_cap', $settings->reward_cap !== null ? (float) $settings->reward_cap : '') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Minimum order amount') }} ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" name="min_order_amount" value="{{ old('min_order_amount', (float) $settings->min_order_amount) }}" class="{{ $input }}">
                    </div>
                </div>
            </section>

            <section class="{{ $card }} space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Friend\'s welcome discount (first order)') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('Discount type') }}</label>
                        <select name="referee_discount_type" x-model="refereeType" class="{{ $input }}">
                            <option value="none">{{ __('No discount') }}</option>
                            <option value="fixed">{{ __('Fixed amount') }}</option>
                            <option value="percent">{{ __('Percentage') }}</option>
                        </select>
                    </div>
                    <div x-show="refereeType !== 'none'">
                        <label class="{{ $label }}"><span x-text="refereeType === 'percent' ? '{{ __('Percent') }} (%)' : '{{ __('Amount') }} ({{ $currency }})'"></span></label>
                        <input type="number" step="0.01" min="0" name="referee_discount_value" value="{{ old('referee_discount_value', (float) $settings->referee_discount_value) }}" class="{{ $input }}">
                    </div>
                    <div x-show="refereeType !== 'none'">
                        <label class="{{ $label }}">{{ __('Minimum order') }} ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" name="referee_min_order" value="{{ old('referee_min_order', (float) $settings->referee_min_order) }}" class="{{ $input }}">
                    </div>
                </div>
            </section>

            <section class="{{ $card }} space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Spending rewards') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Customers can only spend their reward balance on orders; it cannot be withdrawn as cash.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('Minimum balance to spend') }} ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" name="min_spend_balance" value="{{ old('min_spend_balance', (float) $settings->min_spend_balance) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Max share of order payable by rewards') }} (%)</label>
                        <input type="number" min="0" max="100" name="max_redeem_percent" value="{{ old('max_redeem_percent', $settings->max_redeem_percent) }}" class="{{ $input }}">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Example: 50 means on a 2,000 order the customer can pay at most 1,000 from their reward balance; the rest is paid normally.') }}</p>
                    </div>
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('Program terms shown to customers') }}</label>
                    <textarea name="terms" rows="4" class="{{ $input }}">{{ old('terms', $settings->terms) }}</textarea>
                </div>
            </section>
            <div class="flex justify-end"><button type="submit" class="{{ $primary }}"><i class="ph ph-floppy-disk"></i>{{ __('Save settings') }}</button></div>
        </form>

        {{-- Tracking --}}
        <section x-show="tab === 'tracking'" x-cloak class="space-y-4">
            <form method="GET" class="{{ $card }} grid gap-3 sm:grid-cols-6 items-end">
                <input type="hidden" name="tab" value="tracking">
                <div>
                    <label class="{{ $label }}">{{ __('Status') }}</label>
                    <select name="status" class="{{ $input }}">
                        <option value="">{{ __('All') }}</option>
                        @foreach(['pending', 'credited', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="{{ $label }}">{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}"></div>
                <div class="sm:col-span-2"><label class="{{ $label }}">{{ __('Search referrer') }}</label><input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Name or phone') }}" class="{{ $input }}"></div>
                <div class="flex gap-2">
                    <button class="{{ $primary }}">{{ __('Filter') }}</button>
                    <a href="{{ route('user.commerce.referrals.export', request()->only('status', 'from', 'to', 'search')) }}" class="{{ $ghost }}"><i class="ph ph-download-simple"></i>CSV</a>
                </div>
            </form>
            <div class="{{ $card }} overflow-x-auto p-0">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wider"><tr>
                        <th class="px-4 py-3">{{ __('Referrer') }}</th><th class="px-4 py-3">{{ __('Referred') }}</th><th class="px-4 py-3">{{ __('Order') }}</th><th class="px-4 py-3">{{ __('Order amount') }}</th><th class="px-4 py-3">{{ __('Reward') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Date') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @forelse($transactions as $t)
                            <tr>
                                <td class="px-4 py-3">{{ $t->referral?->referrer?->contact?->name }}<span class="block text-slate-400">{{ $t->referral?->referrer?->contact?->phone }}</span></td>
                                <td class="px-4 py-3">{{ $t->referral?->referee?->contact?->name }}<span class="block text-slate-400">{{ $t->referral?->referee?->contact?->phone }}</span></td>
                                <td class="px-4 py-3">{{ $t->order?->number ?? __('Signup') }}</td>
                                <td class="px-4 py-3">{{ $currency }} {{ $money($t->order_amount_minor) }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $currency }} {{ $money($t->reward_minor) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 font-semibold {{ $badge($t->status) }}">{{ __(ucfirst($t->status)) }}</span></td>
                                <td class="px-4 py-3 text-slate-500">{{ $t->created_at?->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No referral transactions found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $transactions->appends(['tab' => 'tracking'])->links() }}
        </section>

        {{-- Target slabs --}}
        <section x-show="tab === 'slabs'" x-cloak class="space-y-4">
            <form method="POST" action="{{ route('user.commerce.referrals.slabs.store') }}" class="{{ $card }} grid gap-3 sm:grid-cols-6 items-end">
                @csrf
                <div class="sm:col-span-2"><label class="{{ $label }}">{{ __('Name') }}</label><input name="name" required class="{{ $input }}" placeholder="{{ __('e.g. Monthly Champion') }}"></div>
                <div><label class="{{ $label }}">{{ __('Period') }}</label><select name="period" class="{{ $input }}"><option value="daily">{{ __('Daily') }}</option><option value="weekly">{{ __('Weekly') }}</option><option value="monthly" selected>{{ __('Monthly') }}</option></select></div>
                <div><label class="{{ $label }}">{{ __('Min. credited referrals') }}</label><input type="number" min="1" name="min_referrals" required class="{{ $input }}"></div>
                <div><label class="{{ $label }}">{{ __('Bonus') }} ({{ $currency }})</label><input type="number" step="0.01" min="0" name="reward_amount" required class="{{ $input }}"></div>
                <div class="flex items-center gap-3"><label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="active" value="1" checked class="rounded border-slate-300 text-emerald-600">{{ __('Active') }}</label><button class="{{ $primary }}"><i class="ph ph-plus"></i>{{ __('Add') }}</button></div>
            </form>
            <p class="text-xs text-slate-500">{{ __('Bonuses are credited automatically after each period ends to every customer who reached the target.') }}</p>
            @forelse($slabs as $slab)
                <form method="POST" action="{{ route('user.commerce.referrals.slabs.update', $slab->id) }}" class="{{ $card }} grid gap-3 sm:grid-cols-6 items-end">
                    @csrf @method('PUT')
                    <div class="sm:col-span-2"><label class="{{ $label }}">{{ __('Name') }}</label><input name="name" value="{{ $slab->name }}" required class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('Period') }}</label><select name="period" class="{{ $input }}">@foreach(['daily', 'weekly', 'monthly'] as $p)<option value="{{ $p }}" @selected($slab->period === $p)>{{ __(ucfirst($p)) }}</option>@endforeach</select></div>
                    <div><label class="{{ $label }}">{{ __('Min. referrals') }}</label><input type="number" min="1" name="min_referrals" value="{{ $slab->min_referrals }}" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('Bonus') }}</label><input type="number" step="0.01" min="0" name="reward_amount" value="{{ (float) $slab->reward_amount }}" class="{{ $input }}"></div>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="active" value="1" @checked($slab->active) class="rounded border-slate-300 text-emerald-600">{{ __('Active') }}</label>
                        <button class="{{ $ghost }}">{{ __('Save') }}</button>
                        <button form="delete-slab-{{ $slab->id }}" class="inline-flex rounded-xl bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-700"><i class="ph ph-trash"></i></button>
                    </div>
                </form>
                <form id="delete-slab-{{ $slab->id }}" method="POST" action="{{ route('user.commerce.referrals.slabs.destroy', $slab->id) }}" class="hidden">@csrf @method('DELETE')</form>
            @empty
                <div class="{{ $card }} text-center text-xs text-slate-400">{{ __('No target slabs defined yet.') }}</div>
            @endforelse
        </section>

        {{-- Customer rules --}}
        <section x-show="tab === 'rules'" x-cloak class="space-y-4">
            <form method="POST" action="{{ route('user.commerce.referrals.rules.store') }}" class="{{ $card }} p-0">
                @csrf
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <span class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400"><i class="ph ph-user-gear text-lg"></i></span>
                    <div><h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Add customer rule') }}</h3><p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Override the default reward for a specific customer.') }}</p></div>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
                    <div><label class="{{ $label }}">{{ __('Customer') }}</label>@include('commerce::user.partials.referral-customer-picker')</div>
                    <div><label class="{{ $label }}">{{ __('Reward type') }}</label><select name="reward_type" class="{{ $input }}"><option value="fixed">{{ __('Fixed') }}</option><option value="percent">{{ __('Percentage') }}</option><option value="percent_capped">{{ __('Percentage (capped)') }}</option></select></div>
                    <div><label class="{{ $label }}">{{ __('Value') }}</label><input type="number" step="0.01" min="0" name="reward_value" required placeholder="0.00" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('Cap') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label><input type="number" step="0.01" min="0" name="reward_cap" placeholder="{{ __('No cap') }}" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('Min. order') }} ({{ $currency }})</label><input type="number" step="0.01" min="0" name="min_order_amount" value="0" class="{{ $input }}"></div>
                    <div class="sm:col-span-2 lg:col-span-5"><label class="{{ $label }}">{{ __('Notes') }}</label><textarea name="notes" rows="2" placeholder="{{ __('Why this customer gets a special rate') }}" class="{{ $input }} resize-y"></textarea></div>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-b-2xl border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="active" value="1" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">{{ __('Active') }}</label>
                    <button class="{{ $primary }}"><i class="ph ph-plus"></i>{{ __('Save rule') }}</button>
                </div>
            </form>
            <div class="{{ $card }} overflow-x-auto p-0">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3">{{ __('Customer') }}</th><th class="px-4 py-3">{{ __('Type') }}</th><th class="px-4 py-3">{{ __('Value') }}</th><th class="px-4 py-3">{{ __('Min. order') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Notes') }}</th><th class="px-4 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @forelse($rules as $rule)
                            <tr>
                                <td class="px-4 py-3">{{ $rule->customer?->contact?->name }}<span class="block text-slate-400">{{ $rule->customer?->contact?->phone }}</span></td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', $rule->reward_type) }}</td>
                                <td class="px-4 py-3">{{ $rule->reward_type === 'fixed' ? $currency.' '.(float) $rule->reward_value : (float) $rule->reward_value.'%' }}@if($rule->reward_cap !== null) <span class="text-slate-400">({{ __('max') }} {{ (float) $rule->reward_cap }})</span>@endif</td>
                                <td class="px-4 py-3">{{ (float) $rule->min_order_amount }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 font-semibold {{ $rule->active ? $badge('credited') : $badge('cancelled') }}">{{ $rule->active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="px-4 py-3 text-slate-500">{{ $rule->notes }}</td>
                                <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('user.commerce.referrals.rules.destroy', $rule->id) }}">@csrf @method('DELETE')<button class="rounded-xl bg-rose-500/10 px-3 py-1.5 font-semibold text-rose-700"><i class="ph ph-trash"></i></button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No custom rules. All customers use the default settings.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Wallets --}}
        <section x-show="tab === 'wallets'" x-cloak class="space-y-4">
            <form method="POST" action="{{ route('user.commerce.referrals.wallets.adjust') }}" class="{{ $card }} p-0">
                @csrf
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <span class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400"><i class="ph ph-wallet text-lg"></i></span>
                    <div><h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Adjust reward balance') }}</h3><p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Manually add to or deduct from a customer reward wallet. Every change is logged.') }}</p></div>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <div><label class="{{ $label }}">{{ __('Customer') }}</label>@include('commerce::user.partials.referral-customer-picker')</div>
                    <div><label class="{{ $label }}">{{ __('Action') }}</label><select name="direction" class="{{ $input }}"><option value="credit">{{ __('Add to balance') }}</option><option value="debit">{{ __('Deduct from balance') }}</option></select></div>
                    <div><label class="{{ $label }}">{{ __('Amount') }} ({{ $currency }})</label><input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="{{ $input }}"></div>
                    <div class="sm:col-span-3"><label class="{{ $label }}">{{ __('Reason') }}</label><textarea name="note" rows="2" required placeholder="{{ __('Shown in the customer reward history') }}" class="{{ $input }} resize-y"></textarea></div>
                </div>
                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                    <button class="{{ $primary }}"><i class="ph ph-arrows-down-up"></i>{{ __('Apply adjustment') }}</button>
                </div>
            </form>
            <div class="{{ $card }} overflow-x-auto p-0">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3">{{ __('Customer') }}</th><th class="px-4 py-3">{{ __('Balance') }}</th><th class="px-4 py-3">{{ __('Last activity') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @forelse($wallets as $wallet)
                            <tr>
                                <td class="px-4 py-3">{{ $wallet->customer?->contact?->name }}<span class="block text-slate-400">{{ $wallet->customer?->contact?->phone }}</span></td>
                                <td class="px-4 py-3 font-semibold">{{ $currency }} {{ $money((int) $wallet->balance_minor) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $wallet->last_activity }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">{{ __('No reward wallets yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $wallets->appends(['tab' => 'wallets'])->links() }}
        </section>

        {{-- Cash-in --}}
        <section x-show="tab === 'cashins'" x-cloak class="space-y-4">
            <form method="GET" class="{{ $card }} flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="cashins">
                <div><label class="{{ $label }}">{{ __('Status') }}</label><select name="cashin_status" class="{{ $input }}"><option value="">{{ __('All') }}</option>@foreach(['pending', 'approved', 'rejected', 'paid'] as $s)<option value="{{ $s }}" @selected(request('cashin_status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></div>
                <button class="{{ $primary }}">{{ __('Filter') }}</button>
            </form>
            <div class="{{ $card }} overflow-x-auto p-0">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3">{{ __('Customer') }}</th><th class="px-4 py-3">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Method') }}</th><th class="px-4 py-3">{{ __('Account') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Date') }}</th><th class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @forelse($cashins as $cashin)
                            <tr>
                                <td class="px-4 py-3">{{ $cashin->customer?->contact?->name }}<span class="block text-slate-400">{{ $cashin->customer?->contact?->phone }}</span></td>
                                <td class="px-4 py-3 font-semibold">{{ $cashin->currency }} {{ $money($cashin->amount_minor) }}</td>
                                <td class="px-4 py-3">{{ $cashin->method }}</td>
                                <td class="px-4 py-3 max-w-48 break-words">{{ $cashin->account_details }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 font-semibold {{ $badge($cashin->status) }}">{{ __(ucfirst($cashin->status)) }}</span>@if($cashin->admin_note)<span class="block text-slate-400 mt-1">{{ $cashin->admin_note }}</span>@endif</td>
                                <td class="px-4 py-3 text-slate-500">{{ $cashin->created_at?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    @if(in_array($cashin->status, ['pending', 'approved'], true))
                                        <form method="POST" action="{{ route('user.commerce.referrals.cashins.update', $cashin->id) }}" class="flex flex-wrap gap-1.5">
                                            @csrf @method('PUT')
                                            <input name="admin_note" placeholder="{{ __('Note / transaction ID') }}" class="{{ $input }} min-w-36">
                                            @if($cashin->status === 'pending')<button name="status" value="approved" class="{{ $ghost }}">{{ __('Approve') }}</button>@endif
                                            <button name="status" value="paid" class="rounded-xl bg-emerald-500/10 px-3 py-1.5 font-semibold text-emerald-700">{{ __('Mark paid') }}</button>
                                            <button name="status" value="rejected" class="rounded-xl bg-rose-500/10 px-3 py-1.5 font-semibold text-rose-700">{{ __('Reject & refund') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No cash-in requests found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $cashins->appends(['tab' => 'cashins'])->links() }}
        </section>
    </div>
</x-layouts.user>
