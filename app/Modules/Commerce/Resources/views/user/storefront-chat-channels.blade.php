@php
    $chatConfig = array_replace_recursive(\App\Modules\Commerce\Storefront\Services\CmsService::CHAT_CHANNEL_DEFAULTS, (array) old('chat_channels', $settings['chat_channels'] ?? []));
    foreach (['whatsapp', 'messenger', 'telegram', 'custom_chatbot'] as $channel) {
        $chatConfig[$channel]['enabled'] = (bool) $chatConfig[$channel]['enabled'];
    }
    $chatConfig['enabled'] = (bool) $chatConfig['enabled'];
@endphp
<div x-show="activeTab === 'chat-channels'" x-cloak
    x-data="{
        chat: {{ Illuminate\Support\Js::from($chatConfig) }},
        mobile: false, expanded: false,
        link(channel) {
            if (channel === 'whatsapp' && /^[1-9][0-9]{6,14}$/.test(this.chat.whatsapp.phone || '')) return 'https://api.whatsapp.com/send/?phone=' + this.chat.whatsapp.phone + (this.chat.whatsapp.message ? '&text=' + encodeURIComponent(this.chat.whatsapp.message) : '');
            if (channel === 'messenger' && /^[a-zA-Z0-9.]{1,100}$/.test(this.chat.messenger.page_id || '')) return 'https://m.me/' + this.chat.messenger.page_id;
            if (channel === 'telegram' && /^[a-zA-Z][a-zA-Z0-9_]{4,31}$/.test(this.chat.telegram.username || '')) return 'https://t.me/' + this.chat.telegram.username;
            if (channel === 'custom_chatbot' && this.chat.custom_chatbot.source === 'direct_link') {
                try { const url = new URL(this.chat.custom_chatbot.direct_url); if (url.protocol === 'https:') return url.href; } catch {}
            }
            return null;
        }
    }" class="grid items-start gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('user.commerce.storefront.chat-channels') }}" class="space-y-5 rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        @csrf
        @method('PUT')
        <input type="hidden" name="_tab" value="chat-channels">
        <h2 class="text-xl font-semibold">{{ __('Channel Chatbot & Live Chat') }}</h2>
        @if (isset($errors) && $errors->any())
            <ul role="alert" class="list-inside list-disc text-sm text-red-600">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <input type="hidden" name="chat_channels[enabled]" :value="chat.enabled ? 1 : 0">
        <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="chat.enabled">{{ __('Enable floating contact widget') }}</label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1 text-sm">{{ __('Position') }}<select name="chat_channels[position]" x-model="chat.position" class="rounded-lg border border-neutral-300 bg-transparent p-2"><option value="bottom-right">Bottom Right</option><option value="bottom-left">Bottom Left</option></select></label>
            <label class="grid gap-1 text-sm">{{ __('Layout') }}<select name="chat_channels[layout]" x-model="chat.layout" class="rounded-lg border border-neutral-300 bg-transparent p-2"><option value="stacked">Stacked Icons</option><option value="expandable">Expandable Bubble</option></select></label>
        </div>
        <label class="grid gap-1 text-sm">{{ __('Greeting tooltip') }}<input name="chat_channels[tooltip]" x-model="chat.tooltip" maxlength="150" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">WhatsApp</legend>
            <input type="hidden" name="chat_channels[whatsapp][enabled]" :value="chat.whatsapp.enabled ? 1 : 0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="chat.whatsapp.enabled">Enable WhatsApp</label>
            <label class="grid gap-1 text-sm">Number with country code (digits only)<input name="chat_channels[whatsapp][phone]" x-model="chat.whatsapp.phone" maxlength="15" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <label class="grid gap-1 text-sm">Default message<input name="chat_channels[whatsapp][message]" x-model="chat.whatsapp.message" maxlength="1000" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <a x-show="link('whatsapp')" :href="link('whatsapp')" target="_blank" rel="noopener noreferrer" class="block break-all text-sm text-primary underline"><span>Test link: </span><span x-text="link('whatsapp')"></span></a>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">Facebook Messenger</legend>
            <input type="hidden" name="chat_channels[messenger][enabled]" :value="chat.messenger.enabled ? 1 : 0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="chat.messenger.enabled">Enable Facebook Messenger</label>
            <label class="grid gap-1 text-sm">Facebook Page ID / username<input name="chat_channels[messenger][page_id]" x-model="chat.messenger.page_id" maxlength="100" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <a x-show="link('messenger')" :href="link('messenger')" target="_blank" rel="noopener noreferrer" class="block break-all text-sm text-primary underline"><span>Test link: </span><span x-text="link('messenger')"></span></a>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">Telegram</legend>
            <input type="hidden" name="chat_channels[telegram][enabled]" :value="chat.telegram.enabled ? 1 : 0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="chat.telegram.enabled">Enable Telegram</label>
            <label class="grid gap-1 text-sm">Telegram username (without @)<input name="chat_channels[telegram][username]" x-model="chat.telegram.username" maxlength="32" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <a x-show="link('telegram')" :href="link('telegram')" target="_blank" rel="noopener noreferrer" class="block break-all text-sm text-primary underline"><span>Test link: </span><span x-text="link('telegram')"></span></a>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">Custom Chatbot</legend>
            <input type="hidden" name="chat_channels[custom_chatbot][enabled]" :value="chat.custom_chatbot.enabled ? 1 : 0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="chat.custom_chatbot.enabled">Enable custom chatbot</label>
            <label class="grid gap-1 text-sm">Source<select name="chat_channels[custom_chatbot][source]" x-model="chat.custom_chatbot.source" class="rounded-lg border border-neutral-300 bg-transparent p-2"><option value="marketing_chatbot">Marketing App Chatbot</option><option value="custom_script">External embed loader URL</option><option value="direct_link">Direct chat link</option></select></label>
            <label class="grid gap-1 text-sm">Title<input name="chat_channels[custom_chatbot][title]" x-model="chat.custom_chatbot.title" maxlength="80" required class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <label class="grid gap-1 text-sm">Icon<select name="chat_channels[custom_chatbot][icon]" x-model="chat.custom_chatbot.icon" class="rounded-lg border border-neutral-300 bg-transparent p-2"><option value="bot">Robot</option><option value="chat">Chat bubble</option></select></label>
            <div x-show="chat.custom_chatbot.source === 'marketing_chatbot'" class="space-y-2">
                <label class="grid gap-1 text-sm">Active workspace widget<select name="chat_channels[custom_chatbot][widget_token]" x-model="chat.custom_chatbot.widget_token" class="rounded-lg border border-neutral-300 bg-transparent p-2"><option value="">Choose a widget</option>
                    @foreach ($chatWidgets as $widget)<option value="{{ $widget->public_token }}">{{ $widget->name }}</option>@endforeach
                </select></label>
                <p class="text-xs text-neutral-500">Create an active widget in Chatbots and allow your storefront domain in its widget settings.</p>
            </div>
            <label x-show="chat.custom_chatbot.source === 'custom_script'" class="grid gap-1 text-sm">HTTPS script loader URL<input name="chat_channels[custom_chatbot][script_url]" x-model="chat.custom_chatbot.script_url" maxlength="2048" class="rounded-lg border border-neutral-300 bg-transparent p-2"><span class="text-xs text-neutral-500">Use the src URL from your provider’s embed code. The provider must support a standalone loader; it controls its own chat launcher.</span></label>
            <label x-show="chat.custom_chatbot.source === 'direct_link'" class="grid gap-1 text-sm">HTTPS chat URL<input name="chat_channels[custom_chatbot][direct_url]" x-model="chat.custom_chatbot.direct_url" maxlength="2048" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <a x-show="chat.custom_chatbot.source === 'direct_link' && link('custom_chatbot')" :href="link('custom_chatbot')" target="_blank" rel="noopener noreferrer" class="block break-all text-sm text-primary underline"><span>Test link: </span><span x-text="link('custom_chatbot')"></span></a>
        </fieldset>
        <button type="submit" class="rounded-xl bg-primary px-5 py-3 font-semibold text-white">Save contact channels</button>
    </form>
    <section class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">Live Interactive Preview</h2><div class="flex gap-2"><button type="button" @click="mobile = false" :aria-pressed="!mobile" class="rounded-lg border px-3 py-1 text-sm">Desktop</button><button type="button" @click="mobile = true" :aria-pressed="mobile" class="rounded-lg border px-3 py-1 text-sm">Mobile</button></div></div>
        <p class="text-sm text-neutral-500">Preview the position and layout. Channel links open in a new tab; chatbot scripts run only on the storefront.</p>
        <div class="relative mx-auto h-[560px] overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50 p-5 dark:border-neutral-700 dark:bg-neutral-800" :class="mobile ? 'w-full max-w-72' : 'w-full'">
            <div class="h-8 rounded bg-neutral-200 dark:bg-neutral-700"></div><div class="mt-6 h-32 rounded-xl bg-neutral-200/60 dark:bg-neutral-700/60"></div><div class="mt-5 grid grid-cols-2 gap-3"><div class="h-24 rounded-lg bg-white dark:bg-neutral-900"></div><div class="h-24 rounded-lg bg-white dark:bg-neutral-900"></div></div>
            <p x-show="!chat.enabled" class="mt-8 text-center text-sm text-neutral-500">Widget disabled</p>
            <div x-show="chat.enabled" class="absolute flex max-w-60 flex-col gap-3" :class="[(chat.position === 'bottom-left' ? 'left-4 items-start' : 'right-4 items-end'), mobile ? 'bottom-20' : 'bottom-4']">
                <p x-show="chat.tooltip" x-text="chat.tooltip" class="rounded-lg bg-white px-3 py-2 text-sm text-neutral-800 shadow"></p>
                <div x-show="chat.layout === 'stacked' || expanded" class="flex flex-col gap-3">
                    <a x-show="chat.whatsapp.enabled && link('whatsapp')" :href="link('whatsapp')" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp" class="flex size-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg"><i class="ph ph-whatsapp-logo text-2xl" aria-hidden="true"></i></a>
                    <a x-show="chat.messenger.enabled && link('messenger')" :href="link('messenger')" target="_blank" rel="noopener noreferrer" aria-label="Messenger" title="Messenger" class="flex size-12 items-center justify-center rounded-full bg-linear-to-br from-[#00B2FF] via-[#006AFF] to-[#A033FF] text-white shadow-lg"><i class="ph ph-messenger-logo text-2xl" aria-hidden="true"></i></a>
                    <a x-show="chat.telegram.enabled && link('telegram')" :href="link('telegram')" target="_blank" rel="noopener noreferrer" aria-label="Telegram" title="Telegram" class="flex size-12 items-center justify-center rounded-full bg-[#229ED9] text-white shadow-lg"><i class="ph ph-telegram-logo text-2xl" aria-hidden="true"></i></a>
                    <a x-show="chat.custom_chatbot.enabled && chat.custom_chatbot.source === 'direct_link' && link('custom_chatbot')" :href="link('custom_chatbot')" target="_blank" rel="noopener noreferrer" :title="chat.custom_chatbot.title" :aria-label="chat.custom_chatbot.title" class="flex size-12 items-center justify-center rounded-full bg-primary text-white shadow-lg"><i class="ph text-2xl" :class="chat.custom_chatbot.icon === 'bot' ? 'ph-robot' : 'ph-chat-circle'" aria-hidden="true"></i></a>
                    <button type="button" x-show="chat.custom_chatbot.enabled && (chat.custom_chatbot.source !== 'direct_link' || !link('custom_chatbot'))" :title="chat.custom_chatbot.title" :aria-label="chat.custom_chatbot.title" class="flex size-12 items-center justify-center rounded-full bg-primary text-white shadow-lg"><i class="ph text-2xl" :class="chat.custom_chatbot.icon === 'bot' ? 'ph-robot' : 'ph-chat-circle'" aria-hidden="true"></i></button>
                </div>
                <button type="button" x-show="chat.layout === 'expandable'" @click="expanded = !expanded" :aria-expanded="expanded" aria-label="Toggle preview channels" class="flex size-12 items-center justify-center rounded-full bg-primary text-white shadow-lg"><i class="ph text-2xl" :class="expanded ? 'ph-x' : 'ph-chat-circle'" aria-hidden="true"></i></button>
            </div>
        </div>
    </section>
</div>
