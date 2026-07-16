import Alpine from "alpinejs";

const escapeHtml = (value = "") =>
  String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const uniqueVariables = (value = "") => {
  const matches = String(value).matchAll(/\{\{\s*([^}]+)\s*\}\}/g);
  return [...new Set([...matches].map((match) => match[1].trim()))].sort((a, b) => {
    if (/^\d+$/.test(a) && /^\d+$/.test(b)) return Number(a) - Number(b);
    return a.localeCompare(b);
  });
};

Alpine.data("messageTemplateEditor", (initialState = {}) => ({
  provider: initialState.provider || "whatsapp",
  selectedToken: "",
  tokenOptions: [
    { value: "full_name", label: "Full name" },
    { value: "first_name", label: "First name" },
    { value: "last_name", label: "Last name" },
    { value: "phone", label: "Phone" },
    { value: "email", label: "Email" },
    { value: "city", label: "City" },
    { value: "country", label: "Country" },
    { value: "location", label: "Location" },
    { value: "website", label: "Website" },
  ],
  headerTypes: [
    { value: "none", label: "None" },
    { value: "text", label: "Text" },
    { value: "image", label: "Image" },
    { value: "video", label: "Video" },
    { value: "document", label: "Document" },
  ],
  header: {
    type: initialState.header?.type || "none",
    text: initialState.header?.text || "",
    example: initialState.header?.example || "",
    mediaId: initialState.header?.mediaId || "",
    mediaName: initialState.header?.mediaName || "",
    mediaUrl: initialState.header?.mediaUrl || "",
    handle: initialState.header?.handle || "",
  },
  body: initialState.body || "",
  bodyExamples: {},
  footer: {
    text: initialState.footer?.text || "",
  },
  buttons: [],

  init() {
    const examples = initialState.bodyExamples || {};
    if (Array.isArray(examples)) {
      examples.forEach((value, index) => {
        this.bodyExamples[String(index + 1)] = value || "";
      });
    } else {
      this.bodyExamples = { ...examples };
    }

    this.buttons = (initialState.buttons || []).map((button) => ({
      id: crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`,
      type: button.type || "quick_reply",
      text: button.text || "",
      url: button.url || "",
      phone_number: button.phone_number || "",
      callback_data: button.callback_data || "",
      example: button.example || "",
    }));
  },

  get isMediaHeader() {
    return ["image", "video", "document"].includes(this.header.type);
  },

  get bodyVariables() {
    return uniqueVariables(this.body);
  },

  get headerExampleMap() {
    const map = {};
    uniqueVariables(this.header.text).forEach((variable) => {
      map[variable] = this.header.example || `Sample ${variable}`;
    });
    return map;
  },

  get bodyExampleMap() {
    const map = {};
    this.bodyVariables.forEach((variable) => {
      map[variable] = this.bodyExamples[variable] || `Sample ${variable}`;
    });
    return map;
  },

  get nextVariable() {
    const used = this.bodyVariables.map((value) => Number(value));
    return used.length ? Math.max(...used) + 1 : 1;
  },

  get renderedBody() {
    const text = this.renderPlain(this.body || "Your message preview appears here...", this.bodyExampleMap);
    return this.renderMarkdown(text).replaceAll("\n", "<br>");
  },

  get previewTime() {
    return new Intl.DateTimeFormat([], { hour: "numeric", minute: "2-digit" }).format(new Date());
  },

  get canAddUrl() {
    return this.buttons.length < 10 && this.buttons.filter((button) => button.type === "url").length < 2;
  },

  get canAddPhone() {
    return this.buttons.length < 10 && !this.buttons.some((button) => button.type === "phone_number");
  },

  get mediaIcon() {
    return {
      image: "ph ph-image text-2xl",
      video: "ph ph-video text-2xl",
      document: "ph ph-file-text text-2xl",
    }[this.header.type] || "ph ph-paperclip text-2xl";
  },

  get mediaLabel() {
    return {
      image: "Image header",
      video: "Video header",
      document: "Document header",
    }[this.header.type] || "Media header";
  },

  variablesFor(value) {
    return uniqueVariables(value);
  },

  renderPlain(value, examples = {}) {
    return String(value || "").replace(/\{\{\s*([^}]+)\s*\}\}/g, (_match, variable) => {
      const key = variable.trim();
      return examples[key] || this.defaultExample(key);
    });
  },

  defaultExample(key) {
    return {
      full_name: "Ada Lovelace",
      name: "Ada Lovelace",
      first_name: "Ada",
      last_name: "Lovelace",
      phone: "+15555550123",
      email: "ada@example.com",
      city: "Portland",
      country: "US",
      location: "Portland, US",
      website: "example.com",
    }[key] || `{{${key}}}`;
  },

  renderMarkdown(value) {
    let output = escapeHtml(value);
    output = output.replace(/```([^`]+)```/g, "<code>$1</code>");
    output = output.replace(/\*([^*\n]+)\*/g, "<strong>$1</strong>");
    output = output.replace(/_([^_\n]+)_/g, "<em>$1</em>");
    output = output.replace(/~([^~\n]+)~/g, "<del>$1</del>");
    return output;
  },

  wrapBody(before, after) {
    const input = this.$refs.bodyInput;
    if (!input) return;

    const start = input.selectionStart ?? this.body.length;
    const end = input.selectionEnd ?? this.body.length;
    const selected = this.body.slice(start, end) || "text";
    this.body = `${this.body.slice(0, start)}${before}${selected}${after}${this.body.slice(end)}`;

    this.$nextTick(() => {
      input.focus();
      input.setSelectionRange(start + before.length, start + before.length + selected.length);
    });
  },

  insertVariable() {
    const input = this.$refs.bodyInput;
    const token = `{{${this.nextVariable}}}`;
    const start = input?.selectionStart ?? this.body.length;
    const end = input?.selectionEnd ?? this.body.length;
    this.body = `${this.body.slice(0, start)}${token}${this.body.slice(end)}`;

    this.$nextTick(() => {
      input?.focus();
      input?.setSelectionRange(start + token.length, start + token.length);
    });
  },

  insertSelectedToken() {
    if (!this.selectedToken) return;

    this.insertToken(this.selectedToken);
    this.selectedToken = "";
  },

  insertToken(tokenName) {
    const input = this.$refs.bodyInput;
    const token = `{{${tokenName}}}`;
    const start = input?.selectionStart ?? this.body.length;
    const end = input?.selectionEnd ?? this.body.length;
    this.body = `${this.body.slice(0, start)}${token}${this.body.slice(end)}`;

    this.$nextTick(() => {
      input?.focus();
      input?.setSelectionRange(start + token.length, start + token.length);
    });
  },

  chooseMedia(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (this.header.mediaUrl?.startsWith("blob:")) {
      URL.revokeObjectURL(this.header.mediaUrl);
    }

    this.header.mediaId = "";
    this.header.handle = "";
    this.header.mediaName = file.name;
    this.header.mediaUrl = URL.createObjectURL(file);
  },

  addButton(type) {
    if (this.buttons.length >= 10) return;
    if (type === "url" && !this.canAddUrl) return;
    if (type === "phone_number" && !this.canAddPhone) return;

    this.buttons.push({
      id: crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`,
      type,
      text: this.buttonLabel(type),
      url: "",
      phone_number: "",
      callback_data: "",
      example: "",
    });
  },

  removeButton(index) {
    this.buttons.splice(index, 1);
  },

  buttonLabel(type) {
    return {
      quick_reply: "Quick Reply",
      url: "Visit Website",
      phone_number: "Call Phone",
      callback: "Callback",
    }[type] || "Button";
  },

  previewButtonIcon(type) {
    return {
      quick_reply: "ph ph-arrow-bend-up-left text-base",
      url: "ph ph-arrow-square-out text-base",
      phone_number: "ph ph-phone text-base",
      callback: "ph ph-cursor-click text-base",
    }[type] || "ph ph-cursor-click text-base";
  },

}));
