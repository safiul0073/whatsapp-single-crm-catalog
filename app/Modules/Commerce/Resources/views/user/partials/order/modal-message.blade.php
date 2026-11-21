@can('commerce.orders.update')
<div
    x-data="{
        isOpen: false,
        sending: false,
        orderId: null,
        orderNumber: '',
        customerName: '',
        sendUrl: '',
        channelsUrl: {{ Js::from(route('user.channels.index')) }},
        hasWorkspaceChannels: false,
        channels: [],
        selectedChannelId: null,
        subject: '',
        body: '',
        statusType: '',
        statusMessage: '',
        statusDetail: '',
        conversationUrl: null,
        get selectedChannel() {
            return this.channels.find((channel) => Number(channel.channel_account_id) === Number(this.selectedChannelId)) || null;
        },
        open(payload) {
            if (!payload) {
                return;
            }
            const availableChannels = Array.isArray(payload.channels) ? payload.channels : [];
            this.orderId = payload.order_id;
            this.orderNumber = payload.order_number || '';
            this.customerName = payload.customer_name || '';
            this.sendUrl = payload.send_url || '';
            this.channelsUrl = payload.channels_url || {{ Js::from(route('user.channels.index')) }};
            this.hasWorkspaceChannels = Boolean(payload.has_workspace_channels);
            this.channels = availableChannels;
            this.selectedChannelId = availableChannels.length > 0 ? availableChannels[0].channel_account_id : null;
            this.subject = payload.default_subject || '';
            this.body = '';
            this.statusType = '';
            this.statusMessage = '';
            this.statusDetail = '';
            this.conversationUrl = null;
            this.sending = false;
            this.isOpen = true;
            this.$nextTick(() => {
                if (this.$refs.messageBody && availableChannels.length > 0) {
                    this.$refs.messageBody.focus();
                }
            });
        },
        close() {
            if (this.sending) {
                return;
            }
            this.isOpen = false;
        },
        selectChannel(channelId) {
            this.selectedChannelId = channelId;
            this.statusType = '';
            this.statusMessage = '';
            this.statusDetail = '';
        },
        providerTone(provider) {
            switch (provider) {
                case 'whatsapp':
                    return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                case 'sms':
                    return 'bg-violet-50 text-violet-700 border-violet-200';
                case 'email':
                    return 'bg-blue-50 text-blue-700 border-blue-200';
                case 'messenger':
                    return 'bg-sky-50 text-sky-700 border-sky-200';
                case 'instagram':
                    return 'bg-pink-50 text-pink-700 border-pink-200';
                case 'telegram':
                    return 'bg-cyan-50 text-cyan-700 border-cyan-200';
                default:
                    return 'bg-neutral-100 text-neutral-700 border-neutral-200';
            }
        },
        async submit() {
            if (this.sending || !this.selectedChannel || !this.sendUrl) {
                return;
            }

            const trimmedBody = this.body.trim();
            if (trimmedBody === '') {
                this.statusType = 'error';
                this.statusMessage = {{ Js::from(__('Enter a message to send to the customer.')) }};
                this.statusDetail = '';
                return;
            }

            if (this.selectedChannel.requires_subject && this.subject.trim() === '') {
                this.statusType = 'error';
                this.statusMessage = {{ Js::from(__('Enter a subject for the email message.')) }};
                this.statusDetail = '';
                return;
            }

            this.sending = true;
            this.statusType = '';
            this.statusMessage = '';
            this.statusDetail = '';
            this.conversationUrl = null;

            const csrfToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || {{ Js::from(csrf_token()) }};

            try {
                const response = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        channel_account_id: this.selectedChannelId,
                        subject: this.selectedChannel.requires_subject ? this.subject.trim() : null,
                        body: trimmedBody,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.ok) {
                    this.statusType = 'success';
                    this.statusMessage = data.message || {{ Js::from(__('Message sent.')) }};
                    this.conversationUrl = data.conversation_url || null;
                    this.body = '';
                } else {
                    const firstValidationError = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : null;
                    this.statusType = 'error';
                    this.statusMessage = firstValidationError || data.error || data.message || {{ Js::from(__('Message could not be sent.')) }};
                    this.statusDetail = data.error_detail || '';
                }
            } catch (error) {
                this.statusType = 'error';
                this.statusMessage = {{ Js::from(__('Network error while sending message. Please try again.')) }};
                this.statusDetail = '';
            } finally {
                this.sending = false;
            }
        }
    }"
    @open-order-message.window="open($event.detail)"
    @keydown.escape.window="isOpen && close()"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="orderDirectMessageTitle"
>
    <div class="fixed inset-0 bg-neutral-900/45 backdrop-blur-xs" @click="close()"></div>

    <div
        x-show="isOpen"
        x-transition
        class="relative z-10 w-full max-w-lg rounded-xl border border-border-soft bg-neutral-0 shadow-2xl overflow-hidden"
    >
        <div class="flex items-start justify-between gap-3 border-b border-border-soft px-5 py-4 bg-neutral-50/60">
            <div class="min-w-0">
                <h3 id="orderDirectMessageTitle" class="text-base font-semibold text-title flex items-center gap-2">
                    <i class="ph ph-paper-plane-tilt text-primary text-lg"></i>
                    <span>{{ __('Message customer') }}</span>
                </h3>
                <p class="mt-0.5 text-xs text-body truncate">
                    <span class="font-mono font-semibold text-primary" x-text="orderNumber"></span>
                    <span>·</span>
                    <span x-text="customerName"></span>
                </p>
            </div>
            <button
                type="button"
                @click="close()"
                class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition"
                aria-label="{{ __('Close') }}"
            >
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <template x-if="channels.length === 0">
            <div class="p-6 text-center space-y-4">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-500">
                    <i class="ph ph-plugs text-2xl"></i>
                </div>
                <div class="space-y-1">
                    <h4
                        class="text-sm font-semibold text-title"
                        x-text="hasWorkspaceChannels
                            ? {{ Js::from(__('No active channel can reach this customer')) }}
                            : {{ Js::from(__('No active channels connected')) }}"
                    ></h4>
                    <p
                        class="text-xs text-body max-w-sm mx-auto"
                        x-text="hasWorkspaceChannels
                            ? {{ Js::from(__('Your active channels need a matching phone number, email address, or channel opt-in for this customer before you can message them.')) }}
                            : {{ Js::from(__('Connect and activate at least one channel (WhatsApp, Email, SMS, Messenger, Instagram, or Telegram) to send direct messages from Orders.')) }}"
                    ></p>
                </div>
                <div class="flex items-center justify-center gap-2 pt-1">
                    <button type="button" @click="close()" class="btn btn-sm btn-outline">
                        {{ __('Close') }}
                    </button>
                    <a :href="channelsUrl" class="btn btn-sm btn-primary inline-flex items-center gap-1.5">
                        <i class="ph ph-plugs-connected text-base"></i>
                        <span x-text="hasWorkspaceChannels ? {{ Js::from(__('Manage channels')) }} : {{ Js::from(__('Connect a channel')) }}"></span>
                    </a>
                </div>
            </div>
        </template>

        <form x-show="channels.length > 0" @submit.prevent="submit()" class="space-y-4 p-5">
            {{-- Feedback Alert --}}
            <template x-if="statusType === 'success'">
                <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 space-y-1.5">
                    <div class="flex items-start gap-2">
                        <i class="ph ph-check-circle text-base text-emerald-600 shrink-0 mt-0.5"></i>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium" x-text="statusMessage"></p>
                            <template x-if="conversationUrl">
                                <a :href="conversationUrl" class="mt-1 inline-flex items-center gap-1 font-semibold text-emerald-700 hover:underline">
                                    <i class="ph ph-chat-circle"></i>
                                    <span>{{ __('Open conversation in Inbox') }}</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="statusType === 'error'">
                <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 space-y-1">
                    <div class="flex items-start gap-2">
                        <i class="ph ph-warning-circle text-base text-rose-600 shrink-0 mt-0.5"></i>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="font-medium" x-text="statusMessage"></p>
                            <template x-if="statusDetail">
                                <p class="text-rose-700" x-text="statusDetail"></p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Active & Available Channel Picker --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="form-label mb-0">{{ __('Choose active channel') }}</span>
                    <span class="text-[11px] font-medium text-neutral-500" x-text="channels.length + ' ' + (channels.length === 1 ? {{ Js::from(__('channel available')) }} : {{ Js::from(__('channels available')) }})"></span>
                </div>

                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('Available channels') }}">
                    <template x-for="channel in channels" :key="channel.channel_account_id">
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="Number(selectedChannelId) === Number(channel.channel_account_id)"
                            @click="selectChannel(channel.channel_account_id)"
                            class="flex items-start gap-2.5 rounded-lg border p-3 text-left transition"
                            :class="Number(selectedChannelId) === Number(channel.channel_account_id)
                                ? 'border-primary bg-primary/5 ring-2 ring-primary/15'
                                : 'border-border-soft bg-neutral-0 hover:border-neutral-300 hover:bg-neutral-50/70'"
                        >
                            <span
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border text-base"
                                :class="providerTone(channel.provider)"
                            >
                                <i class="ph" :class="channel.icon"></i>
                            </span>
                            <span class="min-w-0 flex-1 block">
                                <span class="flex items-center justify-between gap-1.5">
                                    <span class="text-xs font-semibold text-title truncate" x-text="channel.provider_label"></span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 shrink-0">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Active') }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block truncate text-[11px] text-neutral-500" x-text="channel.account_name"></span>
                                <span class="mt-1 block truncate font-mono text-[11px] text-body" x-text="channel.recipient" :title="channel.recipient"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Email Subject (shown only when Email channel is selected) --}}
            <div x-show="selectedChannel && selectedChannel.requires_subject" x-cloak>
                <label for="orderDirectMessageSubject" class="form-label">
                    {{ __('Email subject') }} <span class="text-error">*</span>
                </label>
                <input
                    id="orderDirectMessageSubject"
                    type="text"
                    name="subject"
                    maxlength="255"
                    x-model="subject"
                    :required="Boolean(selectedChannel && selectedChannel.requires_subject)"
                    placeholder="{{ __('Enter email subject...') }}"
                    class="form-input w-full text-sm"
                >
            </div>

            {{-- Message Body --}}
            <div>
                <div class="flex items-center justify-between gap-2 mb-1">
                    <label for="orderDirectMessageBody" class="form-label mb-0">
                        {{ __('Message') }} <span class="text-error">*</span>
                    </label>
                    <span class="text-[11px] text-neutral-400" x-text="body.length + ' / 4096'"></span>
                </div>
                <textarea
                    id="orderDirectMessageBody"
                    x-ref="messageBody"
                    name="body"
                    rows="4"
                    maxlength="4096"
                    required
                    x-model="body"
                    placeholder="{{ __('Write a direct message to this customer...') }}"
                    class="form-input w-full text-sm"
                ></textarea>
            </div>

            {{-- Footer Actions --}}
            <div class="flex items-center justify-between gap-3 border-t border-border-soft pt-4">
                <p class="text-xs text-neutral-500 truncate" x-show="selectedChannel">
                    {{ __('To:') }} <span class="font-medium text-title" x-text="selectedChannel ? selectedChannel.recipient : ''"></span>
                </p>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="close()" class="btn btn-sm btn-outline" :disabled="sending">
                        {{ __('Close') }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-sm btn-primary inline-flex items-center gap-1.5"
                        :disabled="sending || !selectedChannel || body.trim() === ''"
                    >
                        <i class="ph text-base" :class="sending ? 'ph-spinner animate-spin' : 'ph-paper-plane-tilt'"></i>
                        <span x-text="sending ? {{ Js::from(__('Sending...')) }} : (selectedChannel ? {{ Js::from(__('Send via')) }} + ' ' + selectedChannel.provider_label : {{ Js::from(__('Send')) }})"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan
