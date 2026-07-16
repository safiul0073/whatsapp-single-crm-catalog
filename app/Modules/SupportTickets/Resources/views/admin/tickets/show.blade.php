<x-layouts.admin :title="__('Ticket') . ' ' . $ticket->formatted_id">
    <div class="space-y-6">

        {{-- Header Card --}}
        <div
            class="section-card p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
            <div>
                <x-ui.button variant="outline" href="{{ route('admin.support-tickets.index') }}" class="mb-3">
                    <i class="ph ph-arrow-left"></i> {{ __('All Tickets') }}
                </x-ui.button>
                <h1 class="heading-4 text-neutral-950 font-bold leading-tight">{{ $ticket->subject }}</h1>
                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    <span class="s-body text-neutral-500 font-mono">{{ $ticket->formatted_id }}</span>
                    <x-ui.badge :variant="$ticket->status_badge_variant">{{ $ticket->status_label }}</x-ui.badge>
                    <x-ui.badge :variant="$ticket->priority_badge_variant">{{ $ticket->priority_label }}</x-ui.badge>
                    <span class="s-body text-neutral-400">{{ __('Opened') }}
                        {{ $ticket->created_at->diffForHumans() }}</span>
                </div>
            </div>

            {{-- Status Update Form --}}
            <form method="POST" action="{{ route('admin.support-tickets.update-status', $ticket) }}"
                class="flex items-center gap-2 bg-neutral-50 p-2.5 rounded-xl border border-neutral-100 self-start md:self-auto">
                @csrf
                <select name="status"
                    class="select-field bg-white border border-neutral-200 rounded-lg text-sm px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/20">
                    @foreach (['open' => __('Open'), 'in_progress' => __('In Progress'), 'resolved' => __('Resolved'), 'closed' => __('Closed')] as $value => $label)
                        <option value="{{ $value }}" {{ $ticket->status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Update') }}
                </x-ui.button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_300px]">

            {{-- Conversation Card --}}
            <div class="space-y-6">
                <div class="section-card p-6 space-y-6 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <div class="flex items-center gap-2 pb-3 border-b border-neutral-100">
                        <i class="ph ph-chat-centered-text text-lg text-primary"></i>
                        <h3 class="text-sm font-bold text-neutral-900">{{ __('Conversation History') }}</h3>
                    </div>

                    <div class="max-h-[600px] overflow-y-auto pr-1">
                        <x-support-tickets::conversation-thread :ticket="$ticket" :replies="$replies" perspective="admin" />
                    </div>

                    {{-- Admin Reply Form --}}
                    @if ($ticket->status !== 'closed')
                        <x-support-tickets::reply-form
                            :ticket="$ticket"
                            :action="route('admin.support-tickets.reply-ajax', $ticket)"
                            :poll-url="route('admin.support-tickets.poll', $ticket)"
                            perspective="admin"
                            :label="__('Send Reply')"
                            :placeholder="__('Type your reply to the user...')" />
                    @else
                        <div
                            class="rounded-xl border border-neutral-100 bg-neutral-50 px-6 py-6 text-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
                            <div
                                class="w-10 h-10 bg-neutral-200 rounded-full inline-flex items-center justify-center mb-2 mx-auto">
                                <i class="ph ph-lock text-neutral-500 text-lg"></i>
                            </div>
                            <p class="text-sm font-bold text-neutral-900 mb-1">
                                {{ __('This ticket is closed.') }}
                            </p>
                            <p class="s-body text-neutral-500 max-w-sm mx-auto">
                                {{ __('Update the status dropdown above to reopen the ticket and post a reply.') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sidebar Details Card --}}
            <div class="space-y-4">
                <div class="section-card p-6 space-y-4 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <h3
                        class="text-sm font-bold text-neutral-900 pb-3 border-b border-neutral-100 flex items-center gap-2">
                        <i class="ph ph-info text-neutral-500"></i>
                        {{ __('Ticket Details') }}
                    </h3>
                    <dl class="space-y-4">
                        <div>
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold mb-1">
                                {{ __('Status') }}</dt>
                            <dd><x-ui.badge
                                    :variant="$ticket->status_badge_variant">{{ $ticket->status_label }}</x-ui.badge>
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold mb-1">
                                {{ __('Priority') }}</dt>
                            <dd><x-ui.badge
                                    :variant="$ticket->priority_badge_variant">{{ $ticket->priority_label }}</x-ui.badge>
                            </dd>
                        </div>
                        <div class="border-t border-neutral-100/60 pt-3">
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold mb-1 flex items-center gap-1">
                                <i class="ph ph-user text-neutral-400"></i>
                                {{ __('Submitted by') }}
                            </dt>
                            <dd class="text-sm font-semibold text-neutral-900">{{ $ticket->user?->name ?? '—' }}</dd>
                            <dd class="s-body text-neutral-500 text-xs mt-0.5">{{ $ticket->user?->email ?? '' }}</dd>
                        </div>
                        <div class="border-t border-neutral-100/60 pt-3">
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold mb-1.5 flex items-center gap-1">
                                <i class="ph ph-calendar text-neutral-400"></i>
                                {{ __('Opened On') }}
                            </dt>
                            <dd class="text-sm text-neutral-700 font-medium">
                                {{ $ticket->created_at->format('M j, Y g:i A') }}</dd>
                            <dd class="s-body text-neutral-400 text-xs mt-0.5">
                                ({{ $ticket->created_at->diffForHumans() }})</dd>
                        </div>
                        <div class="border-t border-neutral-100/60 pt-3">
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold mb-1.5 flex items-center gap-1">
                                <i class="ph ph-clock text-neutral-400"></i>
                                {{ __('Last Updated') }}
                            </dt>
                            <dd class="text-sm text-neutral-700 font-medium">
                                {{ $ticket->updated_at->format('M j, Y g:i A') }}</dd>
                            <dd class="s-body text-neutral-400 text-xs mt-0.5">
                                ({{ $ticket->updated_at->diffForHumans() }})</dd>
                        </div>
                        <div class="border-t border-neutral-100/60 pt-3 flex items-center justify-between">
                            <dt
                                class="s-body text-neutral-400 uppercase tracking-widest text-[10px] font-semibold flex items-center gap-1">
                                <i class="ph ph-chats-teardrop text-neutral-400"></i>
                                {{ __('Replies') }}
                            </dt>
                            <dd
                                class="text-sm font-semibold text-neutral-900 font-mono px-2 py-0.5 bg-neutral-50 rounded border border-neutral-100">
                                {{ $replies->count() }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Action Panel --}}
                <div class="section-card p-6 space-y-4 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <h3 class="text-sm font-bold text-neutral-900 flex items-center gap-2">
                        <i class="ph ph-shield-warning text-error"></i>
                        {{ __('Admin Actions') }}
                    </h3>
                    <x-ui.button type="button" variant="danger" class="w-full flex items-center justify-center gap-2"
                        data-modal-trigger="globalConfirmModal" data-confirm-title="{{ __('Delete Ticket?') }}"
                        data-confirm-message="{{ __('Delete this ticket and all replies? This cannot be undone.') }}"
                        data-confirm-action="{{ route('admin.support-tickets.destroy', $ticket) }}"
                        data-confirm-method="DELETE" data-confirm-button="{{ __('Yes, Delete') }}">
                        <i class="ph ph-trash"></i> {{ __('Delete Ticket') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
