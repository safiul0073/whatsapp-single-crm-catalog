(function () {
  if (window.WaProChatbotWidgetLoaded) return;
  window.WaProChatbotWidgetLoaded = true;

  const token = @json($token);
  const configUrl = @json($configUrl);
  const baseUrl = @json($baseUrl);
  const storageKey = `wapro_widget_${token}`;
  let config = null;
  let session = JSON.parse(localStorage.getItem(storageKey) || "null");
  let lastMessageId = 0;
  let polling = null;
  let selectedAttachment = null;

  const style = document.createElement("style");
  style.textContent = `
    .wapro-widget{position:fixed;z-index:2147483647;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211b}
    .wapro-widget.right{right:20px;bottom:20px}.wapro-widget.left{left:20px;bottom:20px}
    .wapro-launcher{border:0;border-radius:999px;padding:13px 18px;background:var(--wapro-primary,#16a34a);color:#fff;font-weight:800;box-shadow:0 18px 45px rgba(15,23,42,.22);cursor:pointer}
    .wapro-panel{display:none;width:min(380px,calc(100vw - 32px));height:min(620px,calc(100vh - 96px));overflow:hidden;border:1px solid #dce5df;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.24)}
    .wapro-panel.open{display:flex;flex-direction:column}.wapro-head{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--wapro-primary,#16a34a);color:#fff;padding:16px}
    .wapro-title{font-weight:900}.wapro-close{border:0;background:rgba(255,255,255,.16);color:#fff;border-radius:10px;width:32px;height:32px;cursor:pointer}
    .wapro-messages{flex:1;overflow:auto;background:#f6faf7;padding:14px;display:flex;flex-direction:column;gap:10px}
    .wapro-msg{max-width:82%;border-radius:14px;padding:10px 12px;font-size:14px;line-height:1.45}.wapro-msg.inbound{align-self:flex-end;background:var(--wapro-primary,#16a34a);color:#fff;border-bottom-right-radius:4px}.wapro-msg.outbound{align-self:flex-start;background:#fff;border:1px solid #e3ebe6;border-bottom-left-radius:4px}
    .wapro-form{display:grid;gap:8px;border-top:1px solid #e4ece7;background:#fff;padding:12px}.wapro-fields{display:grid;gap:8px}.wapro-input,.wapro-text{width:100%;box-sizing:border-box;border:1px solid #d8e2dc;border-radius:12px;padding:10px 12px;font:inherit;font-size:14px;outline:none}
    .wapro-send,.wapro-attach{border:0;border-radius:12px;background:var(--wapro-primary,#16a34a);color:#fff;font-weight:800;padding:10px 14px;cursor:pointer}.wapro-attach{width:42px;padding:0;background:#eef6f1;color:var(--wapro-primary,#16a34a)}
    .wapro-row{display:flex;gap:8px}.wapro-row .wapro-text{flex:1}.wapro-note{font-size:12px;color:#647067}.wapro-file-input{display:none}.wapro-file-pill{display:none;align-items:center;justify-content:space-between;gap:8px;border:1px solid #d8e2dc;border-radius:12px;background:#f6faf7;padding:8px 10px;font-size:12px;color:#17211b}.wapro-file-pill.visible{display:flex}.wapro-file-clear{border:0;background:transparent;color:#647067;cursor:pointer;font-size:16px}
    .wapro-attachment{display:block;margin-bottom:7px;overflow:hidden;border-radius:12px;color:inherit;text-decoration:none}.wapro-attachment img{display:block;max-width:100%;max-height:220px;object-fit:cover}.wapro-file-card{display:flex;align-items:center;gap:10px;border:1px solid #e3ebe6;background:rgba(255,255,255,.35);padding:10px}.wapro-file-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:rgba(22,163,74,.12);font-weight:900}.wapro-file-name{display:block;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:800}.wapro-file-size{display:block;font-size:11px;opacity:.72}
  `;
  document.head.appendChild(style);

  const root = document.createElement("div");
  root.className = "wapro-widget right";
  root.innerHTML = `
    <button class="wapro-launcher" type="button">Chat</button>
    <section class="wapro-panel" aria-live="polite">
      <header class="wapro-head"><div><div class="wapro-title">Chat</div><small>Usually replies instantly</small></div><button class="wapro-close" type="button">×</button></header>
      <main class="wapro-messages"></main>
      <form class="wapro-form">
        <div class="wapro-fields"></div>
        <div class="wapro-file-pill"><span></span><button class="wapro-file-clear" type="button" aria-label="Remove attachment">×</button></div>
        <div class="wapro-row"><button class="wapro-attach" type="button" aria-label="Attach file">+</button><input class="wapro-file-input" name="attachment" type="file" accept="image/*,video/mp4,video/quicktime,audio/*,.pdf,.txt,.doc,.docx,.xls,.xlsx"><input class="wapro-text" name="message" placeholder="Type your message..." autocomplete="off"><button class="wapro-send" type="submit">Send</button></div>
        <div class="wapro-note"></div>
      </form>
    </section>
  `;
  document.body.appendChild(root);

  const launcher = root.querySelector(".wapro-launcher");
  const panel = root.querySelector(".wapro-panel");
  const close = root.querySelector(".wapro-close");
  const messages = root.querySelector(".wapro-messages");
  const form = root.querySelector(".wapro-form");
  const fields = root.querySelector(".wapro-fields");
  const note = root.querySelector(".wapro-note");
  const attachmentInput = root.querySelector(".wapro-file-input");
  const attachButton = root.querySelector(".wapro-attach");
  const filePill = root.querySelector(".wapro-file-pill");
  const filePillText = root.querySelector(".wapro-file-pill span");
  const fileClear = root.querySelector(".wapro-file-clear");

  function postJson(url, payload) {
    return fetch(url, {
      method: "POST",
      headers: {"Accept":"application/json","Content-Type":"application/json"},
      body: JSON.stringify(payload || {})
    }).then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || "Request failed.");
      return data;
    });
  }

  function appendMessage(message) {
    if (!message || document.querySelector(`[data-wapro-id="${message.id}"]`)) return;
    const bubble = document.createElement("div");
    bubble.className = `wapro-msg ${message.direction}`;
    bubble.dataset.waproId = message.id;
    if (message.attachment) bubble.appendChild(renderAttachment(message.attachment));
    if (message.body && (!message.attachment || message.body !== message.attachment.name)) {
      const text = document.createElement("div");
      text.textContent = message.body;
      bubble.appendChild(text);
    }
    messages.appendChild(bubble);
    if (!Number.isNaN(Number(message.id))) lastMessageId = Math.max(lastMessageId, Number(message.id));
    messages.scrollTop = messages.scrollHeight;
  }

  function renderAttachment(attachment) {
    const link = document.createElement("a");
    link.className = "wapro-attachment";
    link.href = attachment.url;
    link.target = "_blank";
    link.rel = "noopener";

    if (attachment.type === "image") {
      const image = document.createElement("img");
      image.src = attachment.url;
      image.alt = attachment.name || "";
      link.appendChild(image);
      return link;
    }

    const card = document.createElement("span");
    card.className = "wapro-file-card";
    const icon = document.createElement("span");
    icon.className = "wapro-file-icon";
    icon.textContent = "FILE";
    const meta = document.createElement("span");
    const name = document.createElement("span");
    name.className = "wapro-file-name";
    name.textContent = attachment.name || "Attachment";
    const size = document.createElement("span");
    size.className = "wapro-file-size";
    size.textContent = formatFileSize(attachment.size);
    meta.appendChild(name);
    meta.appendChild(size);
    card.appendChild(icon);
    card.appendChild(meta);
    link.appendChild(card);
    return link;
  }

  function formatFileSize(size) {
    if (!size) return "";
    if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`;
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
  }

  function renderFields() {
    fields.innerHTML = "";
    if (session) return;
    (config.lead_fields || []).forEach((field) => {
      const input = document.createElement("input");
      input.className = "wapro-input";
      input.name = field;
      input.placeholder = field.charAt(0).toUpperCase() + field.slice(1) + " (optional)";
      input.type = field === "email" ? "email" : "text";
      fields.appendChild(input);
    });
  }

  async function ensureSession() {
    if (session?.session_token) return session;
    const payload = {visitor_uid: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()), page_url: location.href, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone};
    fields.querySelectorAll("input").forEach((input) => payload[input.name] = input.value);
    session = await postJson(`${baseUrl}/widgets/chatbot/${token}/sessions`, payload);
    localStorage.setItem(storageKey, JSON.stringify(session));
    renderFields();
    return session;
  }

  async function poll() {
    if (!session?.session_token) return;
    const response = await fetch(`${baseUrl}/widgets/chatbot/${token}/sessions/${session.session_token}/messages?after_id=${lastMessageId}`, {headers: {"Accept":"application/json"}});
    if (!response.ok) return;
    const data = await response.json();
    (data.messages || []).forEach(appendMessage);
  }

  function setAttachment(file) {
    selectedAttachment = file || null;
    filePill.classList.toggle("visible", Boolean(selectedAttachment));
    filePillText.textContent = selectedAttachment ? `${selectedAttachment.name} - ${formatFileSize(selectedAttachment.size)}` : "";
  }

  async function boot() {
    config = await fetch(configUrl, {headers: {"Accept":"application/json"}}).then((response) => response.json());
    root.className = `wapro-widget ${config.settings.position || "right"}`;
    root.style.setProperty("--wapro-primary", config.settings.primary_color || "#16a34a");
    launcher.textContent = config.settings.launcher_label || "Chat";
    root.querySelector(".wapro-title").textContent = config.name || "Chat";
    renderFields();
    if (config.greeting) appendMessage({id: "greeting", direction: "outbound", body: config.greeting});
  }

  launcher.addEventListener("click", () => {
    panel.classList.add("open");
    launcher.style.display = "none";
    polling = polling || setInterval(poll, 4000);
    poll();
  });

  close.addEventListener("click", () => {
    panel.classList.remove("open");
    launcher.style.display = "";
  });

  attachButton.addEventListener("click", () => attachmentInput.click());
  attachmentInput.addEventListener("change", () => setAttachment(attachmentInput.files?.[0] || null));
  fileClear.addEventListener("click", () => {
    attachmentInput.value = "";
    setAttachment(null);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const input = form.elements.message;
    const text = input.value.trim();
    if (!text && !selectedAttachment) return;
    note.textContent = "Sending...";
    input.value = "";
    try {
      await ensureSession();
      const payload = new FormData();
      payload.append("session_token", session.session_token);
      payload.append("message", text);
      if (selectedAttachment) payload.append("attachment", selectedAttachment);
      const data = await fetch(`${baseUrl}/widgets/chatbot/${token}/messages`, {
        method: "POST",
        headers: {"Accept":"application/json"},
        body: payload
      }).then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || "Request failed.");
        return data;
      });
      attachmentInput.value = "";
      setAttachment(null);
      appendMessage(data.message);
      appendMessage(data.reply);
      note.textContent = data.error || "";
    } catch (error) {
      note.textContent = error.message || "Unable to send message.";
    }
  });

  boot().catch(() => {
    launcher.textContent = "Chat unavailable";
    launcher.disabled = true;
  });
})();
