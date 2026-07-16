export default function ticketReply(config) {
  return {
    ticketId: config.ticketId,
    action: config.action,
    isClosed: config.isClosed,
    perspective: config.perspective,
    message: "",
    sending: false,
    error: "",
    pollTimer: null,
    pollUrl: config.pollUrl || null,

    init() {
      this.scrollToBottom();
      this.startPolling();

      this.$watch("message", () => {
        this.error = "";
      });
    },

    destroy() {
      this.stopPolling();
    },

    startPolling() {
      if (this.pollTimer) return;
      this.pollTimer = setInterval(() => this.poll(), 10000);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },

    async submit() {
      const text = this.message.trim();
      if (!text || this.sending) return;

      this.sending = true;
      this.error = "";

      try {
        const formData = new FormData();
        formData.append("message", text);
        formData.append("reopen", this.isClosed ? 1 : 0);

        const fileInput = this.$el.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
          Array.from(fileInput.files).forEach((file) => {
            formData.append("attachments[]", file);
          });
        }

        const response = await axios.post(this.action, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        if (response.data?.html) {
          this.appendReplies(response.data.html);
        }

        this.message = "";

        if (this.isClosed && response.data?.status === "open") {
          this.isClosed = false;
          this.updateStatusBadge("open");
        }

        this.scrollToBottom();
      } catch (error) {
        this.error =
          error.response?.data?.message ||
          error.response?.data?.errors?.message?.[0] ||
          __('Failed to send reply. Please try again.');
      } finally {
        this.sending = false;
      }
    },

    async poll() {
      if (!this.pollUrl || this.sending) return;

      try {
        const response = await axios.get(this.pollUrl, {
          params: { after: this.lastReplyId() },
        });

        if (response.data?.html) {
          this.appendReplies(response.data.html);
          this.scrollToBottom();
        }

        if (response.data?.status && response.data.status !== this.currentStatus()) {
          this.updateStatusBadge(response.data.status);
        }
      } catch (error) {
        // Silently ignore polling errors to avoid spamming the user.
      }
    },

    appendReplies(html) {
      const thread = document.getElementById("conversation-thread");
      if (!thread) return;

      const empty = thread.querySelector("#empty-conversation");
      if (empty) empty.remove();

      const wrapper = document.createElement("div");
      wrapper.innerHTML = html;

      const newMessages = wrapper.querySelectorAll("[data-reply-id]");
      newMessages.forEach((message) => {
        const id = message.dataset.replyId;
        if (!thread.querySelector(`[data-reply-id="${id}"]`)) {
          thread.appendChild(message);
        }
      });
    },

    lastReplyId() {
      const thread = document.getElementById("conversation-thread");
      if (!thread) return 0;

      const replies = thread.querySelectorAll("[data-reply-id]");
      if (replies.length === 0) return 0;

      return Math.max(
        ...Array.from(replies).map((el) => parseInt(el.dataset.replyId, 10) || 0),
      );
    },

    currentStatus() {
      const badge = document.querySelector("[data-ticket-status]");
      return badge?.dataset.ticketStatus || "";
    },

    updateStatusBadge(status) {
      document.querySelectorAll("[data-ticket-status]").forEach((el) => {
        el.dataset.ticketStatus = status;
        el.textContent = status.replace("_", " ");
      });
    },

    scrollToBottom() {
      const thread = document.getElementById("conversation-thread");
      if (thread) {
        thread.scrollTop = thread.scrollHeight;
      }
    },
  };
}
