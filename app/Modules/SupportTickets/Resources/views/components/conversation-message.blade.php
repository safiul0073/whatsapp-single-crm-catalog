@php
    $isMe = $perspective === 'user' ? ! $reply->is_staff : $reply->is_staff;
    $isStaff = $reply->is_staff;
    $authorName = $reply->authorName();
    $initial = strtoupper(substr($authorName, 0, 1));
@endphp

<div class="flex items-end gap-3 {{ $isMe ? 'flex-row-reverse' : '' }}"
    data-reply-id="{{ $reply->id }}">
    {{-- Avatar --}}
    <div class="flex-none {{ $isMe ? 'ml-1' : 'mr-1' }}">
        @if ($isStaff)
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-primary to-accent inline-grid place-items-center shadow-sm">
                <i class="ph ph-headset w-4.5 h-4.5 text-white"></i>
            </div>
        @else
            @if ($reply->user?->avatar)
                <img src="{{ Storage::url($reply->user->avatar) }}" alt="{{ $authorName }}"
                    class="w-9 h-9 rounded-xl object-cover ring-2 ring-white shadow-sm">
            @else
                <div class="w-9 h-9 rounded-xl bg-brand-navy-ink inline-grid place-items-center font-display font-bold text-white text-[13px] shadow-sm">
                    {{ $initial }}
                </div>
            @endif
        @endif
    </div>

    {{-- Bubble --}}
    <div class="flex-1 min-w-0 {{ $isMe ? 'text-right' : 'text-left' }} max-w-[85%] sm:max-w-[75%]">
        <div class="inline-block text-left">
            <div class="flex items-center gap-2 mb-1 {{ $isMe ? 'justify-end' : 'justify-start' }}">
                <span class="font-body text-[12px] font-bold text-text-strong">
                    {{ $authorName }}
                </span>
                @if ($isStaff)
                    <span class="inline-flex items-center gap-1 rounded-full bg-tint-blue px-2 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-brand-blue">
                        {{ __('Staff') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-tint-green px-2 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-brand-green">
                        {{ __('Owner') }}
                    </span>
                @endif
            </div>

            <div class="rounded-2xl px-4 py-3 shadow-sm {{ $isMe ? 'bg-brand-blue text-white rounded-br-md' : 'bg-bg-soft text-text-strong rounded-bl-md border border-border-soft' }}">
                <div class="font-body text-[13.5px] leading-relaxed whitespace-pre-wrap break-words {{ $isMe ? 'text-white' : '' }}">
                    {{ $reply->message }}
                </div>

                {{-- Attachments --}}
                @if ($reply->attachments?->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach ($reply->attachments as $attachment)
                            <x-support-tickets::attachment-card :attachment="$attachment" :in-bubble="true" />
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-1 flex items-center gap-1.5 {{ $isMe ? 'justify-end' : 'justify-start' }}">
                <span class="font-mono text-[10px] text-text-muted" title="{{ $reply->created_at->format('M j, Y g:i A') }}">
                    {{ $reply->created_at->diffForHumans() }}
                </span>
            </div>
        </div>
    </div>
</div>
