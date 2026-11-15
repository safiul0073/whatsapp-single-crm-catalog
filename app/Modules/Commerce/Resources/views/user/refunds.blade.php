@php
    $money = fn (int $minor) => \App\Modules\Commerce\Services\OrderMoney::decimal($minor, $precision);
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $card = 'rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition';
    $labels = [
        'pending_referrer' => __('Pending referrer review'),
        'referrer_approved' => __('Approved by referrer'),
        'referrer_rejected' => __('Rejected by referrer'),
        'awaiting_admin' => __('Awaiting your decision'),
        'processing' => __('Processing'),
        'completed' => __('Completed'),
        'admin_rejected' => __('Rejected by you'),
    ];
    $badge = fn (string $s) => match ($s) {
        'completed' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending_referrer', 'awaiting_admin', 'processing' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        default => 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    };
@endphp
<x-layouts.user :title="__('Refund Management')">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20">
                    <i class="ph ph-arrow-u-up-left text-2xl"></i>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Refund Management') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('A customer\'s referrer reviews each refund request first. You make the final decision and are the only one who can complete a refund.') }}</p>
                </div>
            </div>
            @if($canManage)
                <form method="POST" action="{{ route('user.commerce.refunds.settings') }}" class="flex items-center gap-3 self-start">
                    @csrf @method('PUT')
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="refunds_enabled" value="1" @checked($settings->refunds_enabled) class="rounded border-slate-300 text-emerald-600">
                        {{ __('Customers can request refunds') }}
                    </label>
                    <button class="{{ $primary }}">{{ __('Save') }}</button>
                </form>
            @endif
        </header>

        @if(! $settings->enabled)
            <div class="rounded-2xl bg-amber-500/10 p-4 text-xs font-medium text-amber-800 dark:text-amber-300">{{ __('The referral program is off, so customers cannot send refund requests yet.') }} <a class="underline" href="{{ route('user.commerce.referrals.index') }}">{{ __('Open referral settings') }}</a></div>
        @endif
        @if(session('success'))
            <div class="rounded-2xl bg-emerald-500/10 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5"><i class="ph ph-check-circle text-lg text-emerald-600"></i><span>{{ session('success') }}</span></div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-2xl bg-rose-500/10 p-4 text-xs font-medium text-rose-800 dark:text-rose-300">
                <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-xs dark:bg-slate-900">
            @foreach($tabs as $key)
                <a href="{{ route('user.commerce.refunds.index', ['tab' => $key]) }}" class="whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold transition {{ $tab === $key ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{ $labels[$key] }} <span class="ml-1 rounded-full px-1.5 py-0.5 {{ $tab === $key ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-800' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" class="{{ $card }} flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="grow sm:max-w-xs"><input name="search" value="{{ request('search') }}" placeholder="{{ __('Refund or order number') }}" class="{{ $input }}"></div>
            <button class="{{ $primary }}">{{ __('Search') }}</button>
        </form>

        <div class="{{ $card }} overflow-x-auto p-0">
            <table class="min-w-full text-xs">
                <thead class="text-left text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3">{{ __('Refund') }}</th><th class="px-4 py-3">{{ __('Order') }}</th><th class="px-4 py-3">{{ __('Customer') }}</th><th class="px-4 py-3">{{ __('Referrer') }}</th><th class="px-4 py-3">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Requested') }}</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                    @forelse($requests as $refund)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $refund->reference }}</td>
                            <td class="px-4 py-3">{{ $refund->order?->number }}</td>
                            <td class="px-4 py-3">{{ $refund->customer?->contact?->name }}<span class="block text-slate-400">{{ $refund->customer?->contact?->phone }}</span></td>
                            <td class="px-4 py-3">{{ $refund->referrer?->contact?->name ?? __('No referrer') }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $refund->currency }} {{ $money($refund->amount_minor) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 font-semibold {{ $badge($refund->status) }}">{{ $labels[$refund->status] ?? $refund->status }}</span></td>
                            <td class="px-4 py-3 text-slate-500">{{ $refund->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('user.commerce.refunds.show', $refund->id) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No refund requests here.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</x-layouts.user>
