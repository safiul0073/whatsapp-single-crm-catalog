<x-layouts.user :title="__('Support Tickets')">

    <div class="flex items-center justify-between gap-4 flex-wrap mb-2">
        <div>
            <h2 class="font-display font-extrabold text-brand-navy-ink text-[20px] tracking-[-0.02em]">
                {{ __('Support Tickets') }}</h2>
            <p class="font-body text-body-sm text-text-muted mt-0.5">
                {{ __('Track and manage all your support requests') }}</p>
        </div>
        <a href="{{ route('user.support-tickets.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-5 py-2.5 font-body font-semibold text-[13px] text-white shadow-brand hover:bg-primary-hover transition-colors">
            <i class="ph ph-plus w-4 h-4"></i>
            {{ __('Open New Ticket') }}
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-border-soft shadow-xs overflow-hidden">

        {{-- Search & Filter tabs --}}
        <div class="border-b border-border-soft px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
            <form method="GET" action="{{ route('user.support-tickets.index') }}" class="flex items-center gap-2 flex-1">
                <div class="relative flex-1 max-w-md">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Search by ticket ID or subject...') }}"
                        class="portal-input w-full pl-9 text-[13px]">
                </div>
                @if (request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-blue px-4 py-2 font-body font-semibold text-[12px] text-white hover:bg-primary-hover transition-colors">
                    {{ __('Search') }}
                </button>
                @if (request()->filled('search'))
                    <a href="{{ route('user.support-tickets.index', request()->only('status')) }}" class="font-body text-[12px] text-text-muted hover:text-text-strong transition-colors">
                        {{ __('Clear') }}
                    </a>
                @endif
            </form>
        </div>

        {{-- Filter tabs --}}
        <div class="flex items-center gap-0 border-b border-border-soft px-4 overflow-x-auto">
            <a href="{{ route('user.support-tickets.index') }}"
                class="portal-tab-btn {{ !request('status') ? 'active' : '' }} px-4 py-3.5 font-body text-[13px] whitespace-nowrap">
                {{ __('All Tickets') }}
                <span class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-tint-blue text-brand-blue text-[9px] font-bold">{{ $tickets->total() }}</span>
            </a>
            @foreach (['open' => __('Open'), 'in_progress' => __('In Progress'), 'resolved' => __('Resolved'), 'closed' => __('Closed')] as $status => $label)
                <a href="{{ route('user.support-tickets.index', ['status' => $status]) }}"
                    class="portal-tab-btn {{ request('status') === $status ? 'active' : '' }} px-4 py-3.5 font-body text-[13px] whitespace-nowrap">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($tickets->isEmpty())
            <div class="py-16 text-center">
                <div class="mx-auto mb-4 w-14 h-14 rounded-2xl bg-tint-blue text-brand-blue inline-grid place-items-center">
                    <i class="ph ph-lifebuoy w-6 h-6"></i>
                </div>
                <p class="font-display font-bold text-text-strong text-[15px]">{{ __('No tickets found') }}</p>
                <p class="font-body text-body-sm text-text-muted mt-1.5">
                    {{ __('Open a new support ticket and our team will get back to you shortly.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px]">
                    <thead>
                        <tr class="border-b border-border-soft bg-bg-soft">
                            <th class="text-left px-5 py-3 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-muted">{{ __('Ticket ID') }}</th>
                            <th class="text-left px-5 py-3 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-muted">{{ __('Subject') }}</th>
                            <th class="text-left px-5 py-3 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-muted">{{ __('Priority') }}</th>
                            <th class="text-left px-5 py-3 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-muted">{{ __('Status') }}</th>
                            <th class="text-left px-5 py-3 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-muted">{{ __('Last Update') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @foreach ($tickets as $ticket)
                            <tr class="portal-tbl-row">
                                <td class="px-5 py-3.5 font-mono text-[12px] text-text-muted">
                                    {{ $ticket->formatted_id }}</td>
                                <td class="px-5 py-3.5">
                                    <p class="font-body font-semibold text-text-strong text-[13px]">{{ $ticket->subject }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 font-body text-[12px] font-semibold portal-pri-label-{{ $ticket->priority_color }}">
                                        <span class="w-2 h-2 rounded-full portal-pri-{{ $ticket->priority_color }}"></span>
                                        {{ $ticket->priority_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="portal-badge-{{ $ticket->status_color }} px-2.5 py-1 rounded-pill text-[11px] font-bold">
                                        {{ $ticket->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[11px] text-text-muted">
                                    {{ $ticket->updated_at->diffForHumans() }}</td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('user.support-tickets.show', $ticket) }}"
                                        class="font-body text-[12px] font-semibold text-brand-blue hover:underline">{{ __('View') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-border-soft">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</x-layouts.user>
