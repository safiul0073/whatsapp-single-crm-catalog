@props(['ticket', 'replies', 'perspective' => 'user'])

@php
    $grouped = $replies->groupBy(fn ($reply) => $reply->created_at->format('Y-m-d'));
@endphp

<div class="space-y-6" id="conversation-thread" data-ticket-id="{{ $ticket->id }}" data-perspective="{{ $perspective }}">
    @forelse ($grouped as $date => $dayReplies)
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border-soft/70"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-3 font-mono text-[10px] text-text-muted uppercase tracking-wider">
                    {{ \Illuminate\Support\Carbon::parse($date)->isToday() ? __('Today') : (\Illuminate\Support\Carbon::parse($date)->isYesterday() ? __('Yesterday') : \Illuminate\Support\Carbon::parse($date)->format('M j, Y')) }}
                </span>
            </div>
        </div>

        <div class="space-y-5">
            @foreach ($dayReplies as $reply)
                <x-support-tickets::conversation-message :reply="$reply" :perspective="$perspective" />
            @endforeach
        </div>
    @empty
        <div class="text-center py-10" id="empty-conversation">
            <div class="w-12 h-12 rounded-2xl bg-tint-blue text-brand-blue inline-grid place-items-center mb-3">
                <i class="ph ph-chat-circle-dots w-5 h-5"></i>
            </div>
            <p class="font-display font-bold text-text-strong text-[14px]">{{ __('No messages yet') }}</p>
            <p class="font-body text-[12px] text-text-muted mt-1">{{ __('Start the conversation below.') }}</p>
        </div>
    @endforelse
</div>
