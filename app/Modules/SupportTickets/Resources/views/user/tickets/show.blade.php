<x-layouts.user :title="__('Ticket') . ' ' . $ticket->formatted_id">

    {{-- Breadcrumbs & Back Link --}}
    <div class="mb-6">
        <a href="{{ route('user.support-tickets.index') }}"
            class="inline-flex items-center gap-2 font-body text-[13px] font-medium text-text-muted hover:text-brand-blue transition-all group">
            <i class="ph ph-arrow-left w-4 h-4 transition-transform group-hover:-translate-x-1"></i>
            {{ __('Back to All Tickets') }}
        </a>
    </div>

    {{-- Header Panel --}}
    <div class="bg-white rounded-2xl border border-border-soft p-6 mb-6 shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-2 flex-wrap">
                    <span class="font-mono text-xs text-text-muted px-2 py-0.5 bg-bg-soft rounded border border-border-soft">
                        {{ $ticket->formatted_id }}
                    </span>
                    <span data-ticket-status="{{ $ticket->status }}"
                        class="portal-badge-{{ $ticket->status_color }} px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider">
                        {{ $ticket->status_label }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-body text-[12px] font-semibold portal-pri-label-{{ $ticket->priority_color }} px-2.5 py-0.5 rounded-full bg-bg-soft">
                        <span class="w-2 h-2 rounded-full portal-pri-{{ $ticket->priority_color }}"></span>
                        {{ $ticket->priority_label }} {{ __('priority') }}
                    </span>
                </div>
                <h2 class="font-display font-extrabold text-brand-navy-ink text-[22px] md:text-[26px] tracking-[-0.02em] leading-tight">
                    {{ $ticket->subject }}
                </h2>
            </div>
            <div class="flex items-center gap-2 text-text-muted font-body text-[13px] bg-bg-soft px-4 py-2.5 rounded-xl border border-border-soft self-start md:self-auto">
                <i class="ph ph-calendar w-4 h-4 text-brand-blue"></i>
                <span>{{ __('Opened') }} {{ $ticket->created_at->diffForHumans() }}</span>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">

        {{-- Thread & Reply Card --}}
        <div class="bg-white rounded-2xl border border-border-soft shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 space-y-6">

            <div class="flex items-center gap-2 pb-3 border-b border-border-soft">
                <i class="ph ph-chat-square-text w-5 h-5 text-brand-blue"></i>
                <h3 class="font-display font-bold text-text-strong text-[15px]">{{ __('Conversation History') }}</h3>
            </div>

            {{-- Conversation Thread --}}
            <div class="max-h-[600px] overflow-y-auto pr-1">
                <x-support-tickets::conversation-thread :ticket="$ticket" :replies="$replies" perspective="user" />
            </div>

            {{-- Reply Form Section --}}
            <x-support-tickets::reply-form
                :ticket="$ticket"
                :action="route('user.support-tickets.reply-ajax', $ticket)"
                :poll-url="route('user.support-tickets.poll', $ticket)"
                perspective="user"
                :label="__('Send Reply')"
                :placeholder="__('Type your reply here...')" />

        </div>

        {{-- Sidebar: Ticket Meta Info --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-border-soft shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 space-y-5">
                <h3 class="font-display font-bold text-text-strong text-[15px] pb-3 border-b border-border-soft flex items-center gap-2">
                    <i class="ph ph-info w-4.5 h-4.5 text-text-muted"></i>
                    {{ __('Ticket Details') }}
                </h3>

                <div class="space-y-4">
                    <div class="flex justify-between items-start gap-4 py-1">
                        <div>
                            <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-light mb-1">{{ __('Status') }}</p>
                            <span data-ticket-status="{{ $ticket->status }}"
                                class="portal-badge-{{ $ticket->status_color }} px-2.5 py-0.5 rounded-pill text-[11px] font-bold">
                                {{ $ticket->status_label }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-light mb-1">{{ __('Priority') }}</p>
                            <span class="inline-flex items-center gap-1.5 font-body text-[12px] font-semibold portal-pri-label-{{ $ticket->priority_color }}">
                                <span class="w-2 h-2 rounded-full portal-pri-{{ $ticket->priority_color }}"></span>
                                {{ $ticket->priority_label }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-border-soft/60 pt-3">
                        <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-light mb-1.5 flex items-center gap-1.5">
                            <i class="ph ph-calendar w-3.5 h-3.5 text-text-muted"></i>
                            {{ __('Opened On') }}
                        </p>
                        <p class="font-body text-[13.5px] font-medium text-text-strong">{{ $ticket->created_at->format('M j, Y') }}</p>
                        <p class="font-mono text-[11px] text-text-muted mt-0.5">{{ $ticket->created_at->format('g:i A') }} ({{ $ticket->created_at->diffForHumans() }})</p>
                    </div>

                    <div class="border-t border-border-soft/60 pt-3">
                        <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-light mb-1.5 flex items-center gap-1.5">
                            <i class="ph ph-clock w-3.5 h-3.5 text-text-muted"></i>
                            {{ __('Last Updated') }}
                        </p>
                        <p class="font-body text-[13.5px] font-medium text-text-strong">{{ $ticket->updated_at->format('M j, Y') }}</p>
                        <p class="font-mono text-[11px] text-text-muted mt-0.5">{{ $ticket->updated_at->format('g:i A') }} ({{ $ticket->updated_at->diffForHumans() }})</p>
                    </div>

                    <div class="border-t border-border-soft/60 pt-3 flex items-center justify-between">
                        <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-text-light flex items-center gap-1.5">
                            <i class="ph ph-chat-square-text w-3.5 h-3.5 text-text-muted"></i>
                            {{ __('Total Replies') }}
                        </p>
                        <span class="font-mono text-xs font-bold px-2 py-0.5 bg-bg-soft rounded-lg border border-border-soft text-text-strong">
                            {{ $replies->count() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.user>
