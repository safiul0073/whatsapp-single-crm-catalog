@props([
    'ticket',
    'action',
    'pollUrl' => null,
    'label' => __('Send Reply'),
    'placeholder' => __('Type your reply here...'),
    'perspective' => 'user',
])

<div x-data="ticketReply({
        ticketId: {{ $ticket->id }},
        action: '{{ $action }}',
        pollUrl: '{{ $pollUrl }}',
        isClosed: {{ $ticket->status === 'closed' ? 'true' : 'false' }},
        perspective: '{{ $perspective }}'
    })"
    class="border-t border-border-soft pt-6"
    id="ticket-reply-form">

    <form @submit.prevent="submit" class="space-y-4">
        <div class="flex items-center gap-2 mb-2">
            <i class="ph ph-chat-circle-text w-4.5 h-4.5 text-brand-blue"></i>
            <h4 class="font-display font-bold text-text-strong text-[14px]">
                <span x-show="!isClosed">{{ __('Quick Reply') }}</span>
                <span x-cloak x-show="isClosed">{{ __('Reopen & Reply') }}</span>
            </h4>
        </div>

        <div class="relative">
            <textarea x-model="message" rows="4" required
                :disabled="sending"
                placeholder="{{ $placeholder }}"
                class="portal-input w-full resize-none focus:ring-4 focus:ring-brand-blue/10 min-h-[100px] transition-all"
                @keydown.enter.prevent.stop></textarea>

            <template x-if="error">
                <p class="text-[11px] text-error mt-1.5 flex items-center gap-1">
                    <i class="ph ph-warning-circle w-3.5 h-3.5"></i>
                    <span x-text="error"></span>
                </p>
            </template>
        </div>

        {{-- Attachments --}}
        <div>
            <label class="block text-[12px] font-semibold text-text-strong mb-1.5">
                {{ __('Attachments') }}
                <span class="text-text-muted font-normal">({{ __('optional') }})</span>
            </label>
            <div
                x-data="{ files: [] }"
                class="relative">
                <input type="file" name="attachments[]" multiple
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                    class="block w-full text-[12px] text-text-muted file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-bg-soft file:text-text-strong file:font-medium hover:file:bg-border-soft transition-colors"
                    @change="files = Array.from($event.target.files)"
                    :disabled="sending">
                <p class="text-[11px] text-text-muted mt-1.5">
                    {{ __('Max :size MB each. Images, PDF, DOC, XLS, CSV, TXT, ZIP.', ['size' => config('support-tickets.attachments.max_size') / 1024]) }}
                </p>
                <template x-if="files.length > 0">
                    <ul class="mt-2 space-y-1">
                        <template x-for="(file, index) in files" :key="index">
                            <li class="flex items-center gap-2 text-[11px] text-text-strong">
                                <i class="ph ph-paperclip w-3.5 h-3.5 text-brand-blue"></i>
                                <span x-text="file.name"></span>
                                <span class="text-text-muted" x-text="(file.size / 1024).toFixed(1) + ' KB'"></span>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap pt-2">
            <span class="text-xs text-text-muted flex items-center gap-1.5">
                <i class="ph ph-info w-4 h-4 text-brand-blue/70"></i>
                <span x-show="!isClosed">{{ __('Submitting will notify our staff immediately.') }}</span>
                <span x-cloak x-show="isClosed">{{ __('This reply will reopen the ticket.') }}</span>
            </span>
            <button type="submit"
                :disabled="sending || !message.trim()"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-blue px-6 py-2.5 font-body font-semibold text-[13px] text-white shadow-brand hover:bg-primary-hover transition-all duration-300 hover:shadow-lg active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="ph ph-paper-plane-tilt w-4 h-4" x-show="!sending"></i>
                <i class="ph ph-spinner w-4 h-4 animate-spin" x-cloak x-show="sending"></i>
                <span x-text="isClosed ? '{{ __('Reopen & Reply') }}' : '{{ $label }}'"></span>
            </button>
        </div>
    </form>
</div>
