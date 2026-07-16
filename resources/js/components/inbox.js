import Alpine from "alpinejs";

Alpine.data("liveInbox", (config) => ({
  conversations: [],
  messages: [],
  counts: { all: 0, open: 0, resolved: 0 },
  channels: config.channels || [],
  activeConversation: null,
  query: "",
  status: "all",
  provider: config.initialProvider || "all",
  composer: "",
  attachment: null,
  attachmentPreviewUrl: "",
  loading: false,
  threadLoading: false,
  sending: false,
  aiGenerating: false,
  automationUpdating: false,
  hasChannel: true,
  recipientReady: true,
  canReply: true,
  telegramOptIn: null,
  threadOpen: false,
  sendError: "",
  pollTimer: null,
  routes: config.routes || {},
  initialConversationId: config.initialConversationId || null,
  initialContactId: config.initialContactId || null,
  crm: null,
  crmPermissions: { view: false, manage: false },
  crmLoading: false,
  crmSaving: false,
  crmPanelOpen: false,
  crmAction: '',
  commerceDrawerOpen: false,
  commerceLoading: false,
  commerceProducts: [],
  commerceQuery: '',
  commerceSelected: [],
  commerceSessionActive: false,
  commerceSessionExpiresAt: null,
  crmForm: { pipeline_id: '', stage_id: '', title: '', value: '', description: '', assigned_to: '', priority: 'normal', due_at: '', lost_reason: '' },

  async init() {
    await this.refreshConversations();

    if (this.initialContactId) {
      await this.openContact(this.initialContactId);
    } else if (this.initialConversationId) {
      await this.selectConversation(this.initialConversationId);
    } else if (this.conversations.length > 0) {
      await this.selectConversation(this.conversations[0].id, false);
    }

    this.pollTimer = window.setInterval(() => {
      this.refreshConversations(true);
      if (this.activeConversation?.id) {
        this.loadThread(this.activeConversation.id, true);
      }
    }, 4000);
  },

  async refreshConversations(silent = false) {
    if (!silent) {
      this.loading = true;
    }

    try {
      const url = new URL(this.routes.conversations, window.location.origin);
      if (this.query.trim()) {
        url.searchParams.set("q", this.query.trim());
      }
      url.searchParams.set("status", this.status);
      url.searchParams.set("provider", this.provider);

      const response = await window.axios.get(url.toString(), {
        headers: { Accept: "application/json" },
      });

      this.conversations = response.data.conversations || [];
      this.counts = response.data.counts || this.counts;
      this.channels = response.data.channels || this.channels;
      this.hasChannel = Boolean(response.data.has_channel);

      if (this.activeConversation) {
        const fresh = this.conversations.find(
          (conversation) => conversation.id === this.activeConversation.id,
        );
        if (fresh) {
          this.activeConversation = fresh;
          this.recipientReady = Boolean(fresh.recipient_ready ?? this.recipientReady);
          this.canReply = Boolean(fresh.can_reply ?? this.canReply);
        } else if (!silent) {
          this.activeConversation = null;
          this.messages = [];
          this.recipientReady = true;
          this.canReply = true;
          this.telegramOptIn = null;
        }
      }
    } catch (error) {
      if (!silent) {
        this.sendError = this.errorMessage(error, "Unable to load conversations.");
      }
    } finally {
      this.loading = false;
    }
  },

  setStatus(status) {
    this.status = status;
    this.refreshConversations();
  },

  async setProvider(provider) {
    this.provider = provider;
    this.activeConversation = null;
    this.messages = [];
    this.threadOpen = false;
    await this.refreshConversations();

    if (this.conversations.length > 0) {
      await this.selectConversation(this.conversations[0].id, false);
    }
  },

  async selectConversation(conversationId, openThread = true) {
    const conversation = this.conversations.find(
      (item) => String(item.id) === String(conversationId),
    );
    if (conversation) {
      this.activeConversation = conversation;
    }
    if (openThread) {
      this.threadOpen = true;
    }
    await this.loadThread(conversationId);
  },

  async openContact(contactId) {
    const url = this.routes.contactConversation.replace("__CONTACT__", contactId);

    try {
      const response = await window.axios.post(
        url,
        { provider: this.provider === "all" ? "whatsapp" : this.provider },
        {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken(),
          },
        },
      );

      this.activeConversation = response.data.conversation;
      this.messages = this.decorateMessages(response.data.messages || []);
      this.hasChannel = Boolean(response.data.has_channel);
      this.recipientReady = Boolean(response.data.recipient_ready ?? true);
      this.canReply = Boolean(response.data.conversation?.can_reply ?? true);
      this.telegramOptIn = response.data.telegram_opt_in || null;
      await this.loadCrm();
      if (!this.attachmentsSupported()) {
        this.clearAttachment();
      }
      this.threadOpen = true;
      await this.refreshConversations(true);
      this.scrollToBottom();
    } catch (error) {
      this.sendError = this.errorMessage(error, "Unable to open this contact.");
    }
  },

  async loadThread(conversationId, silent = false) {
    if (!silent) {
      this.threadLoading = true;
    }

    const wasNearBottom = this.isNearBottom();

    try {
      const url = this.routes.conversation.replace(
        "__CONVERSATION__",
        conversationId,
      );
      const response = await window.axios.get(url, {
        headers: { Accept: "application/json" },
      });

      const previousLastId = this.messages.at(-1)?.id;
      this.activeConversation = response.data.conversation;
      this.messages = this.decorateMessages(response.data.messages || []);
      this.hasChannel = Boolean(response.data.has_channel);
      this.recipientReady = Boolean(response.data.recipient_ready ?? true);
      this.canReply = Boolean(response.data.conversation?.can_reply ?? true);
      this.telegramOptIn = response.data.telegram_opt_in || null;
      if (!silent) {
        await this.loadCrm();
      }
      if (!this.attachmentsSupported()) {
        this.clearAttachment();
      }

      const newLastId = this.messages.at(-1)?.id;
      if (!silent || wasNearBottom || previousLastId !== newLastId) {
        this.scrollToBottom();
      }
    } catch (error) {
      if (!silent) {
        this.sendError = this.errorMessage(error, "Unable to load messages.");
      }
    } finally {
      this.threadLoading = false;
    }
  },

  async sendMessage() {
    const body = this.composer.trim();
    if ((!body && !this.attachment) || this.sending || !this.activeConversation?.id || !this.recipientReady || !this.canReply) {
      return;
    }

    if (this.attachment && !this.attachmentsSupported()) {
      this.clearAttachment();
      this.sendError = "File attachments are not supported for this channel.";
      return;
    }

    this.sending = true;
    this.sendError = "";

    try {
      const url = this.routes.send.replace(
        "__CONVERSATION__",
        this.activeConversation.id,
      );
      const payload = new FormData();
      payload.append("body", body);
      if (this.attachment) {
        payload.append("attachment", this.attachment);
      }

      const response = await window.axios.post(
        url,
        payload,
        {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken(),
          },
        },
      );

      this.composer = "";
      this.clearAttachment();
      this.messages = this.decorateMessages([...this.messages, response.data.message]);
      this.activeConversation = response.data.conversation;
      await this.refreshConversations(true);
      this.scrollToBottom();
    } catch (error) {
      const failed = error.response?.data?.message;
      if (failed && failed.id) {
        this.messages.push(failed);
        this.composer = "";
        await this.refreshConversations(true);
        this.scrollToBottom();
      }
      this.sendError = this.errorMessage(error, "Message failed to send.");
    } finally {
      this.sending = false;
    }
  },

  async sendCommerceCatalog() {
    if (this.sending || !this.activeConversation?.id || !this.routes.commerceCatalog) return;
    this.sending = true;
    this.sendError = "";
    try {
      const url = this.routes.commerceCatalog.replace("__CONVERSATION__", this.activeConversation.id);
      const response = await window.axios.post(url, {}, { headers: { Accept: "application/json", "X-CSRF-TOKEN": this.csrfToken() } });
      this.messages = this.decorateMessages([...this.messages, response.data.message]);
      this.scrollToBottom();
    } catch (error) {
      this.sendError = this.errorMessage(error, "Catalog message failed to send.");
    } finally {
      this.sending = false;
    }
  },

  async openCommerceDrawer() {
    if (!this.activeConversation?.id || !this.routes.commerceProducts) return;
    this.commerceDrawerOpen = true;
    await this.loadCommerceProducts();
  },

  async loadCommerceProducts() {
    this.commerceLoading = true;
    this.sendError = '';
    try {
      const route = this.routes.commerceProducts.replace('__CONVERSATION__', this.activeConversation.id);
      const url = new URL(route, window.location.origin);
      if (this.commerceQuery.trim()) url.searchParams.set('q', this.commerceQuery.trim());
      const response = await window.axios.get(url.toString(), { headers: { Accept: 'application/json' } });
      this.commerceProducts = response.data.products || [];
      this.commerceSessionActive = Boolean(response.data.session_active);
      this.commerceSessionExpiresAt = response.data.session_expires_at;
    } catch (error) {
      this.sendError = this.errorMessage(error, 'Products could not be loaded.');
    } finally {
      this.commerceLoading = false;
    }
  },

  toggleCommerceVariant(variantId) {
    const index = this.commerceSelected.indexOf(variantId);
    if (index >= 0) this.commerceSelected.splice(index, 1);
    else if (this.commerceSelected.length < 30) this.commerceSelected.push(variantId);
  },

  async sendCommerceProduct(variantId) {
    await this.sendCommerceRequest('commerceProduct', { variant_id: variantId }, 'Product message failed to send.');
  },

  async sendCommerceSelection() {
    if (this.commerceSelected.length === 0) return;
    await this.sendCommerceRequest('commerceProductList', { variant_ids: this.commerceSelected }, 'Product selection failed to send.');
  },

  async sendCommerceVideo(productMediaId) {
    await this.sendCommerceRequest('commerceProductVideo', { product_media_id: productMediaId }, 'Product video failed to send.');
  },

  async sendCommerceRequest(routeKey, payload, fallback) {
    if (this.sending || !this.commerceSessionActive || !this.routes[routeKey]) return;
    this.sending = true;
    this.sendError = '';
    try {
      const url = this.routes[routeKey].replace('__CONVERSATION__', this.activeConversation.id);
      const response = await window.axios.post(url, payload, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken() } });
      this.messages = this.decorateMessages([...this.messages, response.data.message]);
      this.commerceSelected = [];
      this.commerceDrawerOpen = false;
      this.scrollToBottom();
    } catch (error) {
      this.sendError = this.errorMessage(error, fallback);
    } finally {
      this.sending = false;
    }
  },

  async generateAiReply() {
    if (this.aiGenerating || this.sending || !this.activeConversation?.id || !this.hasChannel || !this.recipientReady || !this.canReply) {
      return;
    }

    if (!this.routes.aiReply) {
      this.sendError = "AI reply generation is not available on this page.";
      return;
    }

    this.aiGenerating = true;
    this.sendError = "";

    try {
      const url = this.routes.aiReply.replace(
        "__CONVERSATION__",
        this.activeConversation.id,
      );

      const response = await window.axios.post(
        url,
        { instruction: this.composer.trim() || null },
        {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken(),
          },
        },
      );

      this.composer = response.data.reply || "";
      this.$nextTick(() => {
        document.getElementById("composer")?.focus();
      });
    } catch (error) {
      this.sendError = this.errorMessage(error, "AI could not generate a reply.");
    } finally {
      this.aiGenerating = false;
    }
  },

  async toggleAutomatedReply(enabled) {
    if (this.automationUpdating || !this.activeConversation?.id || this.activeConversation.provider !== "website_widget") {
      return;
    }

    if (!this.routes.automation) {
      this.sendError = "Automation switching is not available on this page.";
      return;
    }

    this.automationUpdating = true;
    this.sendError = "";

    try {
      const url = this.routes.automation.replace(
        "__CONVERSATION__",
        this.activeConversation.id,
      );

      const response = await window.axios.post(
        url,
        { automated_reply_enabled: enabled },
        {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken(),
          },
        },
      );

      this.activeConversation = response.data.conversation;
      this.messages = this.decorateMessages(response.data.messages || this.messages);
      this.hasChannel = Boolean(response.data.has_channel);
      this.recipientReady = Boolean(response.data.recipient_ready ?? true);
      this.canReply = Boolean(response.data.conversation?.can_reply ?? true);
      await this.refreshConversations(true);
    } catch (error) {
      this.sendError = this.errorMessage(error, "Unable to update automated replies.");
    } finally {
      this.automationUpdating = false;
    }
  },

  async loadCrm() {
    if (!this.activeConversation?.id || !this.routes.crm) {
      this.crm = null;
      return;
    }

    this.crmLoading = true;
    try {
      const url = this.routes.crm.replace('__CONVERSATION__', this.activeConversation.id);
      const response = await window.axios.get(url, { headers: { Accept: 'application/json' } });
      this.crm = response.data.crm;
      this.crmPermissions = response.data.permissions || this.crmPermissions;
      const defaultPipeline = this.crm?.pipelines?.find((pipeline) => pipeline.is_default) || this.crm?.pipelines?.[0];
      this.crmForm.pipeline_id = this.crm?.current_lead?.pipeline_id || defaultPipeline?.id || '';
      this.crmForm.stage_id = this.crm?.current_lead?.stage_id || defaultPipeline?.stages?.[0]?.id || '';
      this.crmForm.assigned_to = this.crm?.current_lead?.assigned_to || '';
    } catch (error) {
      this.sendError = this.errorMessage(error, 'Unable to load CRM details.');
    } finally {
      this.crmLoading = false;
    }
  },

  crmStages() {
    return this.crm?.pipelines?.find((pipeline) => String(pipeline.id) === String(this.crmForm.pipeline_id))?.stages || [];
  },

  openCrmAction(action) {
    this.crmAction = action;
    this.crmPanelOpen = true;
    this.sendError = '';
  },

  async saveCrmAction() {
    if (this.crmSaving || !this.crm?.contact) {
      return;
    }
    this.crmSaving = true;
    this.sendError = '';
    const lead = this.crm.current_lead;

    try {
      if (this.crmAction === 'create') {
        await window.axios.post(this.routes.crmLead, {
          contact_id: this.crm.contact.id,
          conversation_id: this.activeConversation.id,
          pipeline_id: this.crmForm.pipeline_id || null,
          stage_id: this.crmForm.stage_id || null,
          title: this.crmForm.title || null,
          value: this.crmForm.value || null,
          source: 'whatsapp',
        }, this.crmRequestConfig());
      } else if (this.crmAction === 'stage' && lead) {
        await window.axios.patch(this.routes.crmStage.replace('__LEAD__', lead.id), { stage_id: this.crmForm.stage_id }, this.crmRequestConfig());
      } else if (this.crmAction === 'assign' && lead) {
        await window.axios.patch(this.routes.crmAssign.replace('__LEAD__', lead.id), { assigned_to: this.crmForm.assigned_to }, this.crmRequestConfig());
      } else if (this.crmAction === 'note' && lead) {
        await window.axios.post(this.routes.crmNote.replace('__LEAD__', lead.id), { description: this.crmForm.description }, this.crmRequestConfig());
      } else if (this.crmAction === 'task') {
        await window.axios.post(this.routes.crmTask, {
          lead_id: lead?.id || null,
          contact_id: this.crm.contact.id,
          assigned_to: this.crmForm.assigned_to || null,
          title: this.crmForm.title,
          description: this.crmForm.description || null,
          priority: this.crmForm.priority,
          due_at: this.crmForm.due_at,
        }, this.crmRequestConfig());
      } else if (this.crmAction === 'lost' && lead) {
        await window.axios.post(this.routes.crmLost.replace('__LEAD__', lead.id), { lost_reason: this.crmForm.lost_reason || null }, this.crmRequestConfig());
      }

      this.crmAction = '';
      this.crmForm.title = '';
      this.crmForm.value = '';
      this.crmForm.description = '';
      await this.loadCrm();
    } catch (error) {
      this.sendError = this.errorMessage(error, 'Unable to save this CRM update.');
    } finally {
      this.crmSaving = false;
    }
  },

  async markCrmWon() {
    if (!this.crm?.current_lead) {
      return;
    }
    try {
      await window.axios.post(this.routes.crmWon.replace('__LEAD__', this.crm.current_lead.id), {}, this.crmRequestConfig());
      await this.loadCrm();
    } catch (error) {
      this.sendError = this.errorMessage(error, 'Unable to mark this lead won.');
    }
  },

  async completeCrmTask(taskId) {
    try {
      await window.axios.post(this.routes.crmTaskComplete.replace('__TASK__', taskId), {}, this.crmRequestConfig());
      await this.loadCrm();
    } catch (error) {
      this.sendError = this.errorMessage(error, 'Unable to complete this task.');
    }
  },

  crmRequestConfig() {
    return { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken() } };
  },

  formatCrmDate(value) {
    if (!value) {
      return '';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
  },

  statusIcon(status) {
    if (status === "read" || status === "delivered") {
      return "ph-checks";
    }
    if (status === "failed") {
      return "ph-warning-circle";
    }
    return "ph-check";
  },

  selectAttachment(event) {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    if (!this.attachmentsSupported()) {
      this.clearAttachment();
      this.sendError = "File attachments are not supported for this channel.";
      return;
    }

    if (this.attachmentPreviewUrl) {
      URL.revokeObjectURL(this.attachmentPreviewUrl);
    }

    this.attachment = file;
    this.attachmentPreviewUrl = file.type.startsWith("image/")
      ? URL.createObjectURL(file)
      : "";
  },

  clearAttachment() {
    if (this.attachmentPreviewUrl) {
      URL.revokeObjectURL(this.attachmentPreviewUrl);
    }

    this.attachment = null;
    this.attachmentPreviewUrl = "";
    if (this.$refs.attachmentInput) {
      this.$refs.attachmentInput.value = "";
    }
  },

  attachmentIcon(type) {
    if (type === "image") return "ph-image";
    if (type === "video") return "ph-video";
    if (type === "audio") return "ph-music-note";
    return "ph-file";
  },

  formatFileSize(size) {
    if (!size) return "";
    if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`;
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
  },

  attachmentsSupported() {
    return Boolean(this.activeConversation?.attachment_supported ?? true);
  },

  decorateMessages(messages) {
    return messages.map((message, index) => {
      const previous = messages[index - 1] || null;
      const messageDate = this.messageDate(message);
      const previousDate = previous ? this.messageDate(previous) : null;
      const sameDate = messageDate && previousDate
        ? messageDate.toDateString() === previousDate.toDateString()
        : false;

      return {
        ...message,
        date_label: this.messageDateLabel(messageDate),
        show_date_separator: !previous || !sameDate,
        grouped_with_previous: Boolean(
          previous
            && sameDate
            && previous.direction === message.direction
            && this.minutesBetween(previous, message) <= 5,
        ),
      };
    });
  },

  messageDate(message) {
    const value = message?.created_at;
    if (!value) {
      return null;
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  },

  messageDateLabel(date) {
    if (!date) {
      return "";
    }

    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
      return "Today";
    }

    if (date.toDateString() === yesterday.toDateString()) {
      return "Yesterday";
    }

    return date.toLocaleDateString(undefined, {
      month: "short",
      day: "numeric",
      year: date.getFullYear() === today.getFullYear() ? undefined : "numeric",
    });
  },

  minutesBetween(first, second) {
    const firstDate = this.messageDate(first);
    const secondDate = this.messageDate(second);
    if (!firstDate || !secondDate) {
      return Number.POSITIVE_INFINITY;
    }

    return Math.abs(secondDate.getTime() - firstDate.getTime()) / 60000;
  },

  channelIcon(provider) {
    return this.channels.find((channel) => channel.value === provider)?.icon || "ph-chat-circle";
  },

  contactUrl() {
    return this.routes.contacts || "/dashboard/contacts";
  },

  async refreshTelegramOptIn() {
    if (!this.activeConversation?.contact_id || !this.routes.telegramOptIn) {
      return;
    }

    const url = this.routes.telegramOptIn.replace(
      "__CONTACT__",
      this.activeConversation.contact_id,
    );

    try {
      const response = await window.axios.post(
        url,
        {},
        {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken(),
          },
        },
      );

      this.telegramOptIn = response.data;
    } catch (error) {
      this.sendError = this.errorMessage(error, "Unable to create Telegram opt-in link.");
    }
  },

  async copyTelegramOptIn() {
    if (!this.telegramOptIn?.url) {
      await this.refreshTelegramOptIn();
    }

    if (!this.telegramOptIn?.url) {
      return;
    }

    await navigator.clipboard?.writeText(this.telegramOptIn.url);
  },

  csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || "";
  },

  isNearBottom() {
    const pane = this.$refs.messagesPane;
    if (!pane) {
      return true;
    }

    return pane.scrollHeight - pane.scrollTop - pane.clientHeight < 120;
  },

  scrollToBottom() {
    this.$nextTick(() => {
      const pane = this.$refs.messagesPane;
      if (pane) {
        pane.scrollTop = pane.scrollHeight;
      }
    });
  },

  errorMessage(error, fallback) {
    const errors = error.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      if (Array.isArray(first) && first.length > 0) {
        return first[0];
      }
    }

    return error.response?.data?.error || error.response?.data?.message || fallback;
  },
}));
