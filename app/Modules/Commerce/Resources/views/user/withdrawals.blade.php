@php
    $money = fn (int $minor) => \App\Modules\Commerce\Services\OrderMoney::decimal($minor, $precision);
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $card = 'rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 transition';
    $badge = fn (string $s) => match ($s) {
        'paid', 'approved' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending', 'processing' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        default => 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    };
    $days = [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')];
    $tabs = ['requests' => __('Requests'), 'schedule' => __('Schedule'), 'report' => __('Payout report')];
    $activeTab = array_key_exists((string) request('tab'), $tabs) ? request('tab') : 'requests';
    $actions = ['approved' => __('Approve'), 'processing' => __('Processing'), 'paid' => __('Mark paid'), 'rejected' => __('Reject & release')];
@endphp
<x-layouts.user :title="__('Withdrawals')">
    <div x-data="{ tab: '{{ $activeTab }}', frequency: '{{ old('withdrawal_frequency', $settings->withdrawal_frequency ?: 'monthly') }}' }" class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20"><i class="ph ph-hand-coins text-2xl"></i></span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Withdrawals') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Customers and referrers can ask to withdraw their wallet balance only while the window is open. All times are Bangladesh time (:tz).', ['tz' => $timezone]) }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-xs font-semibold {{ $window ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                <i class="ph {{ $window ? 'ph-door-open' : 'ph-lock' }}"></i>
                @if(! $settings->enabled || ! $settings->cashin_enabled)
                    {{ __('Withdrawals off') }}
                @elseif($window)
                    {{ __('Window open until :time', ['time' => $window['ends_at']->format('h:i A')]) }}
                @else
                    {{ isset($upcoming[0]) ? __('Next window: :time', ['time' => $upcoming[0]['starts_at']->format('D, d M Y h:i A')]) : __('No window scheduled') }}
                @endif
            </span>
        </header>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'processing' => __('Processing'), 'paid' => __('Paid')] as $key => $name)
                <div class="{{ $card }}"><span class="text-xs text-slate-400 font-medium">{{ $name }}</span><span class="text-2xl font-bold text-slate-900 dark:text-white mt-1 block">{{ $totals[$key]['requests'] }} <span class="text-sm font-semibold text-slate-500">· {{ $currency }} {{ $money($totals[$key]['amount_minor']) }}</span></span></div>
            @endforeach
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

        {{-- Requests --}}
        <section x-show="tab === 'requests'" class="space-y-4">
            <form method="GET" class="{{ $card }} flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="requests">
                <div><label class="{{ $label }}">{{ __('Status') }}</label><select name="status" class="{{ $input }}"><option value="">{{ __('All') }}</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></div>
                <div><label class="{{ $label }}">{{ __('Method') }}</label><select name="method" class="{{ $input }}"><option value="">{{ __('All') }}</option>@foreach($methods as $code => $name)<option value="{{ $code }}" @selected(request('method') === $code)>{{ $name }}</option>@endforeach</select></div>
                <div><label class="{{ $label }}">{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}"></div>
                <button class="{{ $primary }}">{{ __('Filter') }}</button>
                <a href="{{ route('user.commerce.withdrawals.export', request()->only(['status', 'method', 'from', 'to'])) }}" class="{{ $ghost }}"><i class="ph ph-download-simple"></i> {{ __('Export CSV') }}</a>
            </form>
            <div class="{{ $card }} overflow-x-auto p-0">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3">{{ __('Customer') }}</th><th class="px-4 py-3">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Method') }}</th><th class="px-4 py-3">{{ __('Account') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Requested') }}</th><th class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                        @forelse($requests as $request)
                            <tr>
                                <td class="px-4 py-3">{{ $request->customer?->contact?->name }}<span class="block text-slate-400">{{ $request->customer?->contact?->phone }}</span></td>
                                <td class="px-4 py-3 font-semibold">{{ $request->currency }} {{ $money($request->amount_minor) }}</td>
                                <td class="px-4 py-3">{{ $methods[$request->method] ?? $request->method }}</td>
                                <td class="px-4 py-3 max-w-48 break-words">{{ $request->account_details }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 font-semibold {{ $badge($request->status) }}">{{ __(ucfirst($request->status)) }}</span>@if($request->payout_reference)<span class="block text-slate-400 mt-1">{{ $request->payout_reference }}</span>@endif @if($request->admin_note)<span class="block text-slate-400 mt-1">{{ $request->admin_note }}</span>@endif</td>
                                <td class="px-4 py-3 text-slate-500">{{ $request->created_at?->timezone($timezone)->format('d M Y h:i A') }}</td>
                                <td class="px-4 py-3">
                                    @if($canManage && isset($transitions[$request->status]))
                                        <form method="POST" action="{{ route('user.commerce.withdrawals.update', $request->id) }}" class="flex flex-wrap gap-1.5">
                                            @csrf @method('PUT')
                                            <input name="payout_reference" placeholder="{{ __('Transaction ID') }}" maxlength="120" class="{{ $input }} min-w-32">
                                            <input name="admin_note" placeholder="{{ __('Note') }}" maxlength="255" class="{{ $input }} min-w-32">
                                            @foreach($transitions[$request->status] as $next)
                                                <button name="status" value="{{ $next }}" class="{{ $next === 'rejected' ? 'rounded-xl bg-rose-500/10 px-3 py-1.5 font-semibold text-rose-700' : ($next === 'paid' ? 'rounded-xl bg-emerald-500/10 px-3 py-1.5 font-semibold text-emerald-700' : $ghost) }}">{{ $actions[$next] }}</button>
                                            @endforeach
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No withdrawal requests found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requests->appends(['tab' => 'requests'])->links() }}
        </section>

        {{-- Schedule --}}
        <section x-show="tab === 'schedule'" x-cloak class="grid gap-6 lg:grid-cols-3">
            <form method="POST" action="{{ route('user.commerce.withdrawals.schedule') }}" class="{{ $card }} space-y-4 lg:col-span-2">
                @csrf @method('PUT')
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <input type="checkbox" name="cashin_enabled" value="1" @checked(old('cashin_enabled', $settings->cashin_enabled)) class="rounded border-slate-300 text-emerald-600" @disabled(! $canManage)>
                    {{ __('Scheduled withdrawals are on') }}
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('How often') }}</label>
                        <select name="withdrawal_frequency" x-model="frequency" class="{{ $input }}"><option value="weekly">{{ __('Weekly') }}</option><option value="monthly">{{ __('Monthly') }}</option></select>
                    </div>
                    <div x-show="frequency === 'weekly'">
                        <label class="{{ $label }}">{{ __('Day of the week') }}</label>
                        <select name="withdrawal_weekly_day" class="{{ $input }}">@foreach($days as $index => $day)<option value="{{ $index }}" @selected((int) old('withdrawal_weekly_day', $settings->withdrawal_weekly_day ?? 5) === $index)>{{ $day }}</option>@endforeach</select>
                    </div>
                    <div x-show="frequency === 'monthly'">
                        <label class="{{ $label }}">{{ __('Date of the month') }}</label>
                        <input type="number" name="withdrawal_monthly_day" min="1" max="31" value="{{ old('withdrawal_monthly_day', $settings->withdrawal_monthly_day ?? 1) }}" class="{{ $input }}">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('In shorter months the last day is used.') }}</p>
                    </div>
                    <div><label class="{{ $label }}">{{ __('Opens at') }}</label><input type="time" name="withdrawal_opens_at" value="{{ old('withdrawal_opens_at', $settings->withdrawal_opens_at ?: '09:00') }}" class="{{ $input }}" required></div>
                    <div><label class="{{ $label }}">{{ __('Closes at') }}</label><input type="time" name="withdrawal_closes_at" value="{{ old('withdrawal_closes_at', $settings->withdrawal_closes_at ?: '18:00') }}" class="{{ $input }}" required></div>
                    <div><label class="{{ $label }}">{{ __('Minimum amount (:currency)', ['currency' => $currency]) }}</label><input type="number" step="any" min="0" name="min_cashin_balance" value="{{ old('min_cashin_balance', (float) $settings->min_cashin_balance) }}" class="{{ $input }}" required></div>
                    <div><label class="{{ $label }}">{{ __('Maximum amount (optional)') }}</label><input type="number" step="any" min="0" name="max_cashin_amount" value="{{ old('max_cashin_amount', $settings->max_cashin_amount === null ? '' : (float) $settings->max_cashin_amount) }}" class="{{ $input }}"></div>
                </div>
                <div>
                    <span class="{{ $label }}">{{ __('Payout methods') }}</span>
                    <div class="flex flex-wrap gap-4">
                        @foreach($methods as $code => $name)
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200"><input type="checkbox" name="cashin_methods[]" value="{{ $code }}" @checked(in_array($code, (array) old('cashin_methods', $activeMethods), true)) class="rounded border-slate-300 text-emerald-600"> {{ $name }}</label>
                        @endforeach
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">{{ __('Changing the schedule only affects future windows. Requests already submitted keep their place.') }}</p>
                @if($canManage)<button class="{{ $primary }}">{{ __('Save schedule') }}</button>@endif
            </form>
            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Upcoming windows') }}</h2>
                @if($window)
                    <p class="mt-2 rounded-xl bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ __('Open now until :time', ['time' => $window['ends_at']->format('h:i A')]) }}</p>
                @endif
                <ul class="mt-3 space-y-2 text-xs text-slate-700 dark:text-slate-200">
                    @forelse($upcoming as $next)
                        <li class="flex justify-between gap-3"><span>{{ $next['starts_at']->format('D, d M Y') }}</span><span class="text-slate-500">{{ $next['starts_at']->format('h:i A') }} – {{ $next['ends_at']->format('h:i A') }}</span></li>
                    @empty
                        <li class="text-slate-400">{{ __('No upcoming windows.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- Report --}}
        <section x-show="tab === 'report'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <div class="{{ $card }} overflow-x-auto">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('By status') }}</h2>
                <table class="mt-3 min-w-full text-xs text-slate-700 dark:text-slate-200">
                    <thead class="text-left text-slate-500"><tr><th class="py-2">{{ __('Status') }}</th><th class="py-2">{{ __('Requests') }}</th><th class="py-2 text-right">{{ __('Amount') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($totals as $status => $total)
                            <tr><td class="py-2">{{ __(ucfirst($status)) }}</td><td class="py-2">{{ $total['requests'] }}</td><td class="py-2 text-right font-semibold">{{ $currency }} {{ $money($total['amount_minor']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="{{ $card }} overflow-x-auto">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Paid out by method') }}</h2>
                <table class="mt-3 min-w-full text-xs text-slate-700 dark:text-slate-200">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($paidByMethod as $method => $amount)
                            <tr><td class="py-2">{{ $methods[$method] ?? $method }}</td><td class="py-2 text-right font-semibold">{{ $currency }} {{ $money($amount) }}</td></tr>
                        @empty
                            <tr><td class="py-6 text-center text-slate-400">{{ __('Nothing has been paid out yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.user>
