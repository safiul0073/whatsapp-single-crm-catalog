<x-layouts.user :title="__('Inbox')">
  <div
    class="inbox"
    :class="{ 'is-rail-collapsed': railCollapsed, 'is-list-collapsed': listCollapsed }"
    x-data="liveInbox(@js($inboxConfig))"
    x-init="init()"
    @keydown.window.escape="threadOpen = false"
  >
    <aside class="inbox__rail">
      <div class="p-3">
        <div class="inbox__rail-header">
          <span class="inbox__rail-title">{{ __('Inbox') }}</span>
          <button
            type="button"
            class="row-action"
            @click="toggleRail()"
            :aria-label="railCollapsed ? '{{ __('Expand views panel') }}' : '{{ __('Collapse views panel') }}'"
            :title="railCollapsed ? '{{ __('Expand views panel') }}' : '{{ __('Collapse views panel') }}'"
          >
            <i class="ph text-base" :class="railCollapsed ? 'ph-caret-double-right' : 'ph-caret-double-left'"></i>
          </button>
        </div>
        <p class="app-nav__group inbox__rail-heading pt-1!">{{ __('Views') }}</p>
        <nav class="flex flex-col gap-0.5">
          <button type="button" class="inbox-view" :class="{ 'is-active': status === 'all' }" @click="setStatus('all')" title="{{ __('All chats') }}">
            <span class="flex items-center gap-2.5"><i class="ph ph-tray text-lg"></i><span class="inbox__rail-label">{{ __('All chats') }}</span></span>
            <span class="badge badge-soft" x-text="counts.all ?? 0"></span>
          </button>
          <button type="button" class="inbox-view" :class="{ 'is-active': status === 'open' }" @click="setStatus('open')" title="{{ __('Open') }}">
            <span class="flex items-center gap-2.5"><i class="ph ph-chat-circle-text text-lg"></i><span class="inbox__rail-label">{{ __('Open') }}</span></span>
            <span class="badge badge-success" x-text="counts.open ?? 0"></span>
          </button>
          <button type="button" class="inbox-view" :class="{ 'is-active': status === 'resolved' }" @click="setStatus('resolved')" title="{{ __('Resolved') }}">
            <span class="flex items-center gap-2.5"><i class="ph ph-check-circle text-lg"></i><span class="inbox__rail-label">{{ __('Resolved') }}</span></span>
            <span class="badge badge-soft" x-text="counts.resolved ?? 0"></span>
          </button>
        </nav>

        <p class="app-nav__group inbox__rail-heading">{{ __('Channel') }}</p>
        <div class="space-y-2">
          <template x-for="channel in channels" :key="channel.value">
            <button type="button" class="inbox-view w-full" :class="{ 'is-active': provider === channel.value }" @click="setProvider(channel.value)" :title="channel.label">
              <span class="flex min-w-0 items-center gap-2.5">
                <i class="ph text-lg" :class="[channel.icon, channel.connected ? 'text-success' : 'text-neutral-400']"></i>
                <span class="inbox__rail-label truncate font-semibold" x-text="channel.label"></span>
              </span>
              <span class="badge" :class="channel.connected ? 'badge-success' : 'badge-soft'" x-text="channel.count"></span>
            </button>
          </template>
          <div class="inbox__rail-helper rounded-lg border border-neutral-200 bg-neutral-0 p-3 text-sm">
            <p class="text-xs text-body" x-show="hasChannel">{{ __('Selected channel is ready for replies.') }}</p>
            <p class="text-xs text-error" x-show="!hasChannel">{{ __('Connect this channel before sending replies.') }}</p>
          </div>
        </div>
      </div>
    </aside>

    <section class="inbox__list" :class="{ 'hidden lg:flex': threadOpen }">
      <div class="border-b border-neutral-200 p-3">
        <div class="mb-3 flex items-center justify-between gap-3">
          <p class="text-sm font-semibold text-title">{{ __('Conversations') }}</p>
          <button
            type="button"
            class="row-action hidden lg:grid"
            @click="toggleConversationList()"
            aria-label="{{ __('Collapse conversations') }}"
            title="{{ __('Collapse conversations') }}"
          >
            <i class="ph ph-caret-double-left text-base"></i>
          </button>
        </div>
        <form class="relative" role="search" @submit.prevent="refreshConversations()">
          <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
          <input
            type="search"
            name="q"
            placeholder="{{ __('Search conversations...') }}"
            class="form-input input-search"
            x-model.debounce.350ms="query"
            @input="refreshConversations()"
          />
        </form>
        <div class="mt-3 flex items-center justify-between gap-3">
          <div class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
            <button type="button" class="range-btn" :class="{ 'is-active': status === 'all' }" @click="setStatus('all')">{{ __('All') }}</button>
            <button type="button" class="range-btn" :class="{ 'is-active': status === 'open' }" @click="setStatus('open')">{{ __('Open') }}</button>
            <button type="button" class="range-btn" :class="{ 'is-active': status === 'resolved' }" @click="setStatus('resolved')">{{ __('Resolved') }}</button>
          </div>
          <span class="text-xs text-body" x-show="loading">{{ __('Updating...') }}</span>
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <ul class="divide-y divide-neutral-100" x-show="conversations.length > 0">
          <template x-for="conversation in conversations" :key="conversation.id">
            <li
              class="convo-item"
              :class="{ 'is-active': activeConversation?.id === conversation.id }"
              role="button"
              tabindex="0"
              @click="selectConversation(conversation.id)"
              @keydown.enter.prevent="selectConversation(conversation.id)"
            >
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-bold text-primary" x-text="conversation.initials"></span>
              <div class="min-w-0 flex-1">
                <div class="f-between gap-2">
                  <p class="truncate font-semibold text-title" x-text="conversation.name"></p>
                  <span class="shrink-0 text-xs text-body" x-text="conversation.last_message_time || ''"></span>
                </div>
                <p class="mt-0.5 truncate text-sm text-body" x-text="conversation.last_message"></p>
                <div class="mt-1 flex items-center gap-1.5">
                  <span class="badge badge-soft">
                    <i class="ph text-xs" :class="conversation.provider_icon"></i>
                    <span x-text="conversation.provider_label"></span>
                  </span>
                  <template x-for="label in conversation.labels" :key="label">
                    <span class="badge badge-soft" x-text="label"></span>
                  </template>
                  <span class="badge badge-neutral" x-show="conversation.status === 'resolved'">{{ __('Resolved') }}</span>
                  <span class="badge badge-warning" x-show="conversation.last_message_status === 'failed'">{{ __('Failed') }}</span>
                </div>
              </div>
            </li>
          </template>
        </ul>

        <div class="flex flex-col items-center justify-center px-6 py-16 text-center" x-show="!loading && conversations.length === 0">
          <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
            <i class="ph ph-chats-circle text-2xl"></i>
          </span>
          <h3 class="heading-4 mt-4">{{ __('No conversations') }}</h3>
          <p class="m-text mt-1">{{ __('WhatsApp messages will appear here as contacts reply.') }}</p>
        </div>
      </div>
    </section>

    <section class="inbox__thread" :class="{ 'hidden lg:flex': !threadOpen && !activeConversation }">
      <template x-if="activeConversation">
        <div class="flex min-h-0 flex-1 flex-col">
          <header class="flex items-center gap-3 border-b border-neutral-200 bg-neutral-0 px-4 py-3">
            <button type="button" class="row-action lg:hidden" @click="threadOpen = false" aria-label="{{ __('Back to conversations') }}">
              <i class="ph ph-arrow-left text-base"></i>
            </button>
            <button
              type="button"
              class="row-action hidden lg:grid"
              x-show="listCollapsed"
              x-cloak
              @click="openConversationList()"
              aria-label="{{ __('Open conversations') }}"
              title="{{ __('Open conversations') }}"
            >
              <i class="ph ph-list text-base"></i>
            </button>
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-bold text-primary" x-text="activeConversation.initials"></span>
            <div class="min-w-0 flex-1">
              <div class="flex min-w-0 items-center gap-2">
                <p class="truncate font-semibold text-title" x-text="activeConversation.name"></p>
                <span class="badge badge-soft shrink-0">
                  <i class="ph text-xs" :class="activeConversation.provider_icon"></i>
                  <span x-text="activeConversation.provider_label"></span>
                </span>
              </div>
              <p class="truncate text-xs" :class="hasChannel ? 'text-success' : 'text-error'">
                <i class="ph ph-circle-fill text-[8px]"></i>
                <span x-text="activeConversation.channel_name || activeConversation.phone || activeConversation.email || '{{ __('No recipient identity') }}'"></span>
              </p>
            </div>
            <a :href="contactUrl(activeConversation.contact_id)" class="row-action" x-show="activeConversation.contact_id" aria-label="{{ __('Open contact') }}">
              <i class="ph ph-user-circle text-base"></i>
            </a>
            <button
              type="button"
              class="row-action"
              :class="{ 'bg-primary/10 text-primary': crmPanelOpen }"
              x-show="activeConversation.contact_id && routes.crm"
              @click="toggleCrmPanel()"
              :aria-label="crmPanelOpen ? '{{ __('Hide CRM profile') }}' : '{{ __('Open CRM profile') }}'"
              :title="crmPanelOpen ? '{{ __('Hide CRM profile') }}' : '{{ __('Open CRM profile') }}'"
            >
              <i class="ph text-base" :class="crmPanelOpen ? 'ph-sidebar-simple' : 'ph-address-book-tabs'"></i>
            </button>
            <button type="button" class="row-action" x-show="activeConversation.provider === 'whatsapp' && routes.commerceCatalog" @click="openCommerceDrawer()" :disabled="sending" aria-label="{{ __('Open WhatsApp products') }}">
              <i class="ph ph-shopping-bag-open text-base"></i>
            </button>
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-section px-2.5 py-1.5" x-show="activeConversation.provider === 'website_widget'" x-cloak>
              <span class="hidden text-xs font-semibold text-body sm:inline">{{ __('Auto reply') }}</span>
              <button
                type="button"
                role="switch"
                class="relative h-6 w-11 rounded-full transition"
                :aria-checked="activeConversation.automated_reply_enabled ? 'true' : 'false'"
                :class="activeConversation.automated_reply_enabled ? 'bg-primary' : 'bg-neutral-300'"
                :disabled="automationUpdating"
                @click="toggleAutomatedReply(!activeConversation.automated_reply_enabled)"
              >
                <span class="sr-only">{{ __('Toggle automated chatbot replies') }}</span>
                <span class="absolute top-1 left-1 h-4 w-4 rounded-full bg-neutral-0 shadow transition" :class="activeConversation.automated_reply_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
              </button>
            </div>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto bg-section px-3 py-4 sm:px-5" x-ref="messagesPane">
            <div class="flex justify-center" x-show="threadLoading">
              <span class="rounded-full bg-neutral-0 px-3 py-1 text-xs font-medium text-body shadow-sm">{{ __('Loading messages...') }}</span>
            </div>
            <div class="mx-auto flex w-full max-w-4xl flex-col gap-1.5">
              <template x-for="message in messages" :key="message.id">
                <div>
                  <div class="chat-date-separator" x-show="message.show_date_separator">
                    <span x-text="message.date_label"></span>
                  </div>
                  <div
                    class="chat-message"
                    :class="[
                      message.direction === 'outbound' ? 'chat-message--out' : 'chat-message--in',
                      message.grouped_with_previous ? 'chat-message--grouped' : ''
                    ]"
                  >
                    <div class="chat-bubble" :class="message.direction === 'outbound' ? 'chat-bubble--out' : 'chat-bubble--in'">
                      <template x-if="message.attachment">
                        <div class="mb-2">
                          <template x-if="message.attachment.type === 'image'">
                            <a :href="message.attachment.url" target="_blank" class="chat-attachment chat-attachment--image">
                              <img :src="message.attachment.url" :alt="message.attachment.name">
                            </a>
                          </template>
                          <template x-if="message.attachment.type !== 'image'">
                            <a :href="message.attachment.url" target="_blank" class="chat-attachment chat-attachment--file">
                              <span class="chat-attachment__icon">
                                <i class="ph text-lg" :class="attachmentIcon(message.attachment.type)"></i>
                              </span>
                              <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold" x-text="message.attachment.name"></span>
                                <span class="block text-xs opacity-75" x-text="formatFileSize(message.attachment.size)"></span>
                              </span>
                            </a>
                          </template>
                        </div>
                      </template>
                      <p class="chat-bubble__body" x-show="message.body && (!message.attachment || message.body !== message.attachment.name)" x-text="message.body || ''"></p>
                      <p class="chat-bubble__meta" :class="message.status === 'failed' ? 'text-error' : ''">
                        <span x-text="message.time"></span>
                        <i class="ph text-xs" :class="statusIcon(message.status)" x-show="message.direction === 'outbound'"></i>
                        <span x-show="message.status === 'failed'">{{ __('Failed') }}</span>
                      </p>
                    </div>
                  </div>
                </div>
              </template>
              <div class="flex flex-col items-center justify-center px-6 py-16 text-center" x-show="!threadLoading && messages.length === 0">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                  <i class="ph ph-chat-circle-text text-2xl"></i>
                </span>
                <h3 class="heading-4 mt-4">{{ __('No messages yet') }}</h3>
                <p class="m-text mt-1">{{ __('Send the first direct message below.') }}</p>
              </div>
            </div>
          </div>

          <div class="border-t border-neutral-200 bg-neutral-0 px-3 py-2" x-show="sendError">
            <p class="text-sm font-medium text-error" x-text="sendError"></p>
          </div>

          <div class="border-t border-neutral-200 bg-warning/10 px-3 py-3" x-show="activeConversation?.provider === 'telegram' && !recipientReady" x-cloak>
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-title">{{ __('Telegram opt-in required') }}</p>
                <p class="text-xs text-body">{{ __('Share this bot link with the contact before sending Telegram messages.') }}</p>
              </div>
              <div class="flex min-w-0 items-center gap-2">
                <input type="text" class="form-input h-9 min-w-0 text-xs" readonly :value="telegramOptIn?.url || ''" placeholder="{{ __('Create opt-in link') }}">
                <button type="button" class="btn-sm btn-outline shrink-0" @click="copyTelegramOptIn()">
                  <i class="ph ph-copy"></i>
                  {{ __('Copy') }}
                </button>
              </div>
            </div>
          </div>

          <form class="inbox-composer" @submit.prevent="sendMessage()">
            <input
              x-ref="attachmentInput"
              type="file"
              class="sr-only"
              accept="image/*,video/mp4,video/quicktime,audio/*,.pdf,.txt,.doc,.docx,.xls,.xlsx"
              @change="selectAttachment($event)"
              :disabled="!attachmentsSupported()"
            >
            <label for="composer" class="sr-only">{{ __('Message') }}</label>
            <div class="inbox-composer__field">
              <div class="inbox-composer__attachment" x-show="attachment" x-cloak>
                <template x-if="attachmentPreviewUrl">
                  <img :src="attachmentPreviewUrl" alt="" class="h-12 w-12 rounded-lg object-cover">
                </template>
                <template x-if="!attachmentPreviewUrl">
                  <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-neutral-0 text-primary">
                    <i class="ph ph-file text-xl"></i>
                  </span>
                </template>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-semibold text-title" x-text="attachment?.name"></span>
                  <span class="block text-xs text-body" x-text="formatFileSize(attachment?.size)"></span>
                </span>
                <button type="button" class="row-action" @click="clearAttachment()" aria-label="{{ __('Remove attachment') }}">
                  <i class="ph ph-x text-base"></i>
                </button>
              </div>
              <p class="inbox-composer__notice" x-show="activeConversation && !attachmentsSupported()" x-cloak>{{ __('This channel supports text replies only.') }}</p>
              <p class="inbox-composer__notice text-warning" x-show="activeConversation && !canReply" x-cloak x-text="activeConversation?.reply_disabled_reason || @js(__('Replies are disabled for this conversation.'))"></p>
              <div class="inbox-composer__input-row">
                <div class="inbox-composer__actions" aria-label="{{ __('Message tools') }}">
                  <button
                    type="button"
                    class="inbox-composer__icon"
                    :disabled="sending || !hasChannel || !recipientReady || !canReply || !attachmentsSupported()"
                    @click="$refs.attachmentInput.click()"
                    aria-label="{{ __('Attach file') }}"
                    title="{{ __('Attach image, video, audio, or document') }}"
                  >
                    <i class="ph ph-paperclip text-xl"></i>
                    <span class="sr-only">{{ __('Attach file') }}</span>
                  </button>
                  <button
                    type="button"
                    class="inbox-composer__icon inbox-composer__icon--ai"
                    :disabled="sending || aiGenerating || !activeConversation?.id || !hasChannel || !recipientReady || !canReply"
                    @click="generateAiReply()"
                    aria-label="{{ __('Generate AI reply') }}"
                    title="{{ __('Generate AI reply') }}"
                  >
                    <i class="ph text-xl" :class="aiGenerating ? 'ph-circle-notch animate-spin' : 'ph-sparkle'"></i>
                    <span class="sr-only">{{ __('Generate AI reply') }}</span>
                  </button>
                </div>
                <textarea
                  id="composer"
                  name="message"
                  rows="1"
                  placeholder="{{ __('Type a message or add a caption...') }}"
                  class="inbox-composer__input"
                  x-model="composer"
                  :disabled="sending || !hasChannel || !recipientReady || !canReply"
                  @keydown.enter.prevent="sendMessage()"
                ></textarea>
              </div>
            </div>
            <button
              type="submit"
              class="inbox-composer__send"
              :class="{ 'is-ready': composer.trim() || attachment }"
              :disabled="sending || (!composer.trim() && !attachment) || !hasChannel || !recipientReady || !canReply"
              aria-label="{{ __('Send message') }}"
              title="{{ __('Send message') }}"
            >
              <i class="ph text-xl" :class="sending ? 'ph-circle-notch animate-spin' : 'ph-paper-plane-tilt'"></i>
              <span class="sr-only">{{ __('Send message') }}</span>
            </button>
          </form>
        </div>
      </template>

      <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 text-center" x-show="!activeConversation">
        <button
          type="button"
          class="btn btn-secondary btn-sm mb-4 hidden lg:inline-flex"
          x-show="listCollapsed"
          x-cloak
          @click="openConversationList()"
        >
          <i class="ph ph-list"></i>
          {{ __('Open conversations') }}
        </button>
        <span class="grid h-14 w-14 place-items-center rounded-xl bg-primary/10 text-primary">
          <i class="ph ph-chats-circle text-3xl"></i>
        </span>
        <h3 class="heading-4 mt-4">{{ __('Select a conversation') }}</h3>
        <p class="m-text mt-1 max-w-sm">{{ __('Choose a chat from the list or start one from Contacts.') }}</p>
      </div>
    </section>

    <aside class="inbox__crm" :class="{ 'is-open': crmPanelOpen && activeConversation?.contact_id && routes.crm }" x-cloak>
      <header class="flex items-center justify-between border-b border-neutral-200 px-4 py-3">
        <div><p class="font-semibold text-title">{{ __('Contact CRM') }}</p><p class="text-xs text-body">{{ __('Profile and follow-up') }}</p></div>
        <button type="button" class="row-action" @click="closeCrmPanel()" aria-label="{{ __('Close CRM profile') }}" title="{{ __('Close CRM profile') }}"><i class="ph ph-x"></i></button>
      </header>

      <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-4">
        @include('commerce::user.partials.help', ['helpKey' => 'inbox', 'compact' => true, 'minimal' => true])

        <p class="text-sm text-body" x-show="crmLoading">{{ __('Loading CRM details...') }}</p>
        <template x-if="crm?.contact">
          <div class="space-y-5">
            <section>
              <div class="flex items-center gap-3">
                <span class="avatar h-11 w-11" x-text="activeConversation?.initials"></span>
                <div class="min-w-0"><p class="truncate font-semibold text-title" x-text="crm.contact.name || activeConversation?.name"></p><p class="truncate text-xs text-body" x-text="crm.contact.phone || crm.contact.email || ''"></p></div>
              </div>
              <div class="mt-3 flex flex-wrap gap-1.5"><template x-for="tag in crm.contact.tags" :key="tag.id"><span class="badge badge-soft" x-text="tag.name"></span></template></div>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-section p-3">
              <template x-if="crm.current_lead">
                <div class="space-y-2">
                  <div class="flex items-center justify-between gap-2"><p class="font-semibold text-title" x-text="crm.current_lead.title"></p><span class="badge badge-success" x-text="crm.current_lead.status"></span></div>
                  <p class="text-xs text-body"><span x-text="crm.current_lead.pipeline"></span> · <span x-text="crm.current_lead.stage"></span></p>
                  <p class="text-xs text-body" x-text="crm.current_lead.assignee || '{{ __('Unassigned') }}'"></p>
                </div>
              </template>
              <p class="text-sm text-body" x-show="!crm.current_lead">{{ __('No open CRM lead for this contact.') }}</p>
            </section>

            <div class="grid grid-cols-2 gap-2" x-show="crmPermissions.manage">
              <button type="button" class="btn-sm btn-primary justify-center" x-show="!crm.current_lead" @click="openCrmAction('create')">{{ __('Create Lead') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center" x-show="crm.current_lead" @click="openCrmAction('note')">{{ __('Add Note') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center" @click="openCrmAction('task')">{{ __('Add Task') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center" x-show="crm.current_lead" @click="openCrmAction('stage')">{{ __('Move Stage') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center" x-show="crm.current_lead" @click="openCrmAction('assign')">{{ __('Assign Agent') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center text-success" x-show="crm.current_lead" @click="markCrmWon()">{{ __('Mark Won') }}</button>
              <button type="button" class="btn-sm btn-outline justify-center text-error" x-show="crm.current_lead" @click="openCrmAction('lost')">{{ __('Mark Lost') }}</button>
            </div>

            <form class="space-y-3 rounded-xl border border-primary/30 bg-primary/5 p-3" x-show="crmAction" @submit.prevent="saveCrmAction()">
              <div class="flex items-center justify-between"><p class="text-sm font-semibold text-title" x-text="crmAction.replace('_', ' ')"></p><button type="button" class="row-action" @click="crmAction = ''"><i class="ph ph-x"></i></button></div>
              <div x-show="crmAction === 'create'">
                <label class="form-label" for="crmPipeline">{{ __('Pipeline') }}</label>
                <select id="crmPipeline" class="form-input" x-model="crmForm.pipeline_id" @change="syncCrmStageForPipeline($event.target.value)"><template x-for="pipeline in crm.pipelines" :key="pipeline.id"><option :value="pipeline.id" x-text="pipeline.name"></option></template></select>
              </div>
              <div x-show="['create', 'stage'].includes(crmAction)">
                <label class="form-label" for="crmStage">{{ __('Stage') }}</label>
                <select id="crmStage" class="form-input" x-model="crmForm.stage_id"><template x-for="stage in crmStages()" :key="stage.id"><option :value="stage.id" x-text="stage.name"></option></template></select>
              </div>
              <div x-show="['create', 'task'].includes(crmAction)"><label class="form-label" for="crmTitle">{{ __('Title') }}</label><input id="crmTitle" class="form-input" x-model="crmForm.title" :required="crmAction === 'task'"></div>
              <div x-show="crmAction === 'create'"><label class="form-label" for="crmValue">{{ __('Value') }}</label><input id="crmValue" type="number" min="0" step="0.01" class="form-input" x-model="crmForm.value"></div>
              <div x-show="['note', 'task'].includes(crmAction)"><label class="form-label" for="crmDescription">{{ __('Description') }}</label><textarea id="crmDescription" rows="3" class="form-input" x-model="crmForm.description" :required="crmAction === 'note'"></textarea></div>
              <div x-show="['assign', 'task'].includes(crmAction)"><label class="form-label" for="crmAgent">{{ __('Agent') }}</label><select id="crmAgent" class="form-input" x-model="crmForm.assigned_to"><option value="">{{ __('Workspace owner') }}</option><template x-for="agent in crm.agents" :key="agent.id"><option :value="agent.id" x-text="agent.name"></option></template></select></div>
              <div x-show="crmAction === 'task'" class="grid grid-cols-2 gap-2"><div><label class="form-label" for="crmDue">{{ __('Due') }}</label><input id="crmDue" type="datetime-local" class="form-input" x-model="crmForm.due_at" :required="crmAction === 'task'"></div><div><label class="form-label" for="crmPriority">{{ __('Priority') }}</label><select id="crmPriority" class="form-input" x-model="crmForm.priority"><option value="low">{{ __('Low') }}</option><option value="normal">{{ __('Normal') }}</option><option value="high">{{ __('High') }}</option></select></div></div>
              <div x-show="crmAction === 'lost'"><label class="form-label" for="crmLostReason">{{ __('Lost reason') }}</label><textarea id="crmLostReason" rows="3" class="form-input" x-model="crmForm.lost_reason"></textarea></div>
              <button type="submit" class="btn-sm btn-primary w-full justify-center" :disabled="crmSaving" x-text="crmSaving ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></button>
            </form>

            <section>
              <h3 class="text-sm font-semibold text-title">{{ __('Follow-up tasks') }}</h3>
              <div class="mt-2 space-y-2">
                <template x-for="task in crm.tasks" :key="task.id"><div class="rounded-lg border border-neutral-200 p-2.5"><div class="flex items-start justify-between gap-2"><div><p class="text-sm font-medium text-title" x-text="task.title"></p><p class="text-xs" :class="task.overdue ? 'text-error' : 'text-body'" x-text="formatCrmDate(task.due_at)"></p></div><button type="button" class="row-action" x-show="crmPermissions.manage" @click="completeCrmTask(task.id)" aria-label="{{ __('Complete task') }}"><i class="ph ph-check"></i></button></div></div></template>
                <p class="text-xs text-body" x-show="crm.tasks.length === 0">{{ __('No pending tasks.') }}</p>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-title">{{ __('Activity') }}</h3>
              <div class="mt-2 space-y-3"><template x-for="item in crm.timeline" :key="item.id"><div class="border-l-2 border-primary/30 pl-3"><p class="text-sm font-medium text-title" x-text="item.title"></p><p class="line-clamp-3 text-xs text-body" x-text="item.description || ''"></p><p class="mt-1 text-[11px] text-neutral-400" x-text="formatCrmDate(item.occurred_at)"></p></div></template></div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-title">{{ __('Campaign history') }}</h3>
              <div class="mt-2 space-y-2"><template x-for="campaign in crm.campaign_history" :key="campaign.id"><div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 p-2.5"><p class="truncate text-sm text-title" x-text="campaign.name || '{{ __('Campaign') }}'"></p><span class="badge badge-soft" x-text="campaign.status"></span></div></template><p class="text-xs text-body" x-show="crm.campaign_history.length === 0">{{ __('No campaign history.') }}</p></div>
            </section>
          </div>
        </template>
      </div>
    </aside>

    <div class="fixed inset-0 z-50" x-show="commerceDrawerOpen" x-cloak @keydown.escape.window="commerceDrawerOpen = false">
      <button type="button" class="absolute inset-0 bg-deep/50" @click="commerceDrawerOpen = false" aria-label="{{ __('Close product drawer') }}"></button>
      <aside class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-neutral-0 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="commerceDrawerTitle">
        <header class="flex items-start justify-between gap-3 border-b border-border p-4"><div><p class="text-sm font-semibold text-primary">{{ __('WhatsApp store') }}</p><h2 id="commerceDrawerTitle" class="heading-5 text-title">{{ __('Send products') }}</h2><p class="text-xs text-body">{{ __('Send the full catalog, one variant, a curated selection, or a product video.') }}</p></div><button type="button" class="row-action" @click="commerceDrawerOpen = false" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button></header>
        <div class="border-b border-border p-4">
          <div class="p-3 text-sm" :class="commerceSessionActive ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
            <div class="flex items-start gap-2">
              <i class="ph mt-0.5" :class="commerceSessionActive ? 'ph-clock-countdown' : 'ph-warning-circle'"></i>
              <div class="min-w-0 flex-1">
                <p class="font-semibold" x-text="commerceSessionActive ? '{{ __('24-hour session active') }}' : '{{ __('Waiting for buyer reply') }}'"></p>
                <p class="text-xs" x-text="commerceSessionActive ? '{{ __('Interactive products can be sent now.') }}' : '{{ __('Sending a template or text does not reopen the service window. The buyer must reply first.') }}'"></p>
                <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold underline underline-offset-2 disabled:pointer-events-none disabled:opacity-50" x-show="!commerceSessionActive" @click="checkCommerceReadiness()" :disabled="commerceLoading">
                  <i class="ph ph-arrow-clockwise"></i>
                  <span x-text="commerceLoading ? '{{ __('Checking…') }}' : '{{ __('Check again after buyer replies') }}'"></span>
                </button>
              </div>
            </div>
          </div>

          <div class="mt-2 flex items-start gap-2 bg-warning/10 p-3 text-sm text-warning" x-show="commerceCatalogStatus && !commerceCatalogReady">
            <i class="ph ph-storefront mt-0.5"></i>
            <div class="min-w-0 flex-1">
              <p class="font-semibold">{{ __('Catalog is not synchronized') }}</p>
              <p class="text-xs" x-text="commerceCatalogStatus?.connected ? '{{ __('Meta has not successfully fetched or synchronized these products yet.') }}' : '{{ __('Connect a Meta catalog to this WhatsApp channel first.') }}'"></p>
              <a href="{{ route('user.commerce.catalog') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold underline underline-offset-2">
                {{ __('Open Meta Catalog setup') }}
                <i class="ph ph-arrow-up-right"></i>
              </a>
            </div>
          </div>

          <div class="mt-2 flex items-start gap-2 p-3 text-sm" x-show="commerceNotice" x-cloak :class="commerceNoticeTone === 'success' ? 'bg-success/10 text-success' : commerceNoticeTone === 'error' ? 'bg-error/10 text-error' : commerceNoticeTone === 'info' ? 'bg-primary/10 text-primary' : 'bg-warning/10 text-warning'">
            <i class="ph mt-0.5" :class="commerceNoticeTone === 'success' ? 'ph-check-circle' : commerceNoticeTone === 'error' ? 'ph-x-circle' : commerceNoticeTone === 'info' ? 'ph-info' : 'ph-warning-circle'"></i>
            <p class="min-w-0 flex-1 text-xs font-medium" x-text="commerceNotice"></p>
            <button type="button" class="text-current opacity-60 transition hover:opacity-100" @click="commerceNotice = ''" aria-label="{{ __('Dismiss message') }}"><i class="ph ph-x"></i></button>
          </div>

          <div class="mt-3 flex items-center gap-3 bg-primary/5 p-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"><i class="ph ph-storefront text-xl"></i></span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-title">{{ __('Share complete catalog') }}</p>
              <p class="text-xs text-body">{{ __('Opens the synchronized Meta catalog in WhatsApp so the buyer can browse every available product.') }}</p>
            </div>
            <button type="button" class="btn btn-primary shrink-0 whitespace-nowrap disabled:opacity-50" @click="sendCommerceCatalog()" :disabled="sending"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send catalog') }}</button>
          </div>

          <div class="mt-3 flex gap-2">
            <input class="form-input" type="search" x-model="commerceQuery" @keydown.enter.prevent="loadCommerceProducts()" placeholder="{{ __('Search products') }}">
            <button type="button" class="btn btn-outline" @click="loadCommerceProducts()" :disabled="commerceLoading" :class="commerceLoading ? 'pointer-events-none opacity-50' : ''" aria-label="{{ __('Search products') }}"><i class="ph ph-magnifying-glass"></i></button>
          </div>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-4">
          <p class="py-8 text-center text-sm text-body" x-show="commerceLoading">{{ __('Loading products…') }}</p>
          <div class="space-y-4" x-show="!commerceLoading">
            <template x-for="product in commerceProducts" :key="product.id">
              <article class="overflow-hidden rounded-2xl bg-neutral-0 shadow-sm ring-1 ring-neutral-200/70">
                <div class="flex gap-3 bg-section/60 p-3">
                  <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-neutral-100">
                    <img x-show="product.image" :src="product.image" :alt="product.name" class="h-full w-full object-cover">
                    <span x-show="!product.image" class="grid h-full place-items-center text-2xl text-neutral-300"><i class="ph ph-t-shirt"></i></span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-title" x-text="product.name"></p>
                    <p class="text-xs text-body" x-text="`${product.variants.length} variants`"></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                      <template x-for="video in product.videos" :key="video.id"><button type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary hover:text-neutral-0 disabled:opacity-50" @click="sendCommerceVideo(video.id)" :disabled="sending"><i class="ph ph-video"></i> {{ __('Send video') }}</button></template>
                    </div>
                  </div>
                </div>
                <div class="divide-y divide-border-soft">
                  <template x-for="variant in product.variants" :key="variant.id">
                    <div class="flex items-center gap-3 p-3 transition hover:bg-primary/5">
                      <input type="checkbox" :checked="commerceSelected.includes(variant.id)" @change="toggleCommerceVariant(variant.id)" :aria-label="`Select ${product.name} ${variant.label}`">
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-title" x-text="variant.label || product.name"></p>
                        <p class="text-xs text-body"><span x-text="new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(variant.price)"></span> · <span x-text="variant.stock > 0 ? `${variant.stock} in stock` : 'Out of stock'"></span></p>
                      </div>
                      <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary hover:text-neutral-0 disabled:opacity-50" @click="sendCommerceProduct(variant.id)" :disabled="sending"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send') }}</button>
                    </div>
                  </template>
                </div>
              </article>
            </template>
            <p class="py-8 text-center text-sm text-body" x-show="commerceProducts.length === 0">{{ __('No active products match your search.') }}</p>
          </div>
        </div>
        <footer class="flex items-center justify-between gap-3 bg-section p-4 shadow-[0_-4px_16px_rgba(16,24,40,0.06)]"><div><p class="text-sm text-body"><strong class="text-title" x-text="commerceSelected.length"></strong> {{ __('selected') }}</p><p class="text-xs text-warning" x-show="!commerceSessionActive">{{ __('Click send to see what action is required.') }}</p></div><button type="button" class="btn btn-primary disabled:opacity-50" @click="sendCommerceSelection()" :disabled="sending"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send selection') }}</button></footer>
      </aside>
    </div>
  </div>
</x-layouts.user>
