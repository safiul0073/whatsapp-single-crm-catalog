'use strict';

document.addEventListener("alpine:init", () => {
  'use strict';
  Alpine.data("automationBuilder", (initialFlow = {}, options = {}) => ({
    nodes: Array.isArray(initialFlow.nodes) ? initialFlow.nodes : [],
    edges: Array.isArray(initialFlow.edges) ? initialFlow.edges : [],
    generateUrl: options.generateUrl || "",
    testUrl: options.testUrl || "",
    initialAiDraft: options.aiDraft || null,
    chatbots: Array.isArray(options.chatbots) ? options.chatbots : [],
    crmPipelines: Array.isArray(options.crm?.pipelines) ? options.crm.pipelines : [],
    crmStages: Array.isArray(options.crm?.stages) ? options.crm.stages : [],
    crmTags: Array.isArray(options.crm?.tags) ? options.crm.tags : [],
    crmAgents: Array.isArray(options.crm?.agents) ? options.crm.agents : [],
    selectedNodeId: null,
    inspectorOpen: false,
    inspectorMinimized: false,
    sourcePort: null,
    connectionDraft: null,
    hotInputPort: null,
    draggingNodeId: null,
    dragOffset: { x: 0, y: 0 },
    dragStartSnapshot: null,
    paletteDragType: null,
    paletteCollapsed: false,
    nodeSearch: "",
    aiPrompt: "",
    aiError: "",
    aiNotice: "",
    aiSummary: [],
    aiGenerating: false,
    testMessage: "Can I get pricing details?",
    testRunning: false,
    testResult: null,
    testError: "",
    showAiPanel: Boolean(options.aiDraft),
    isDropping: false,
    zoom: 95,
    minZoom: 50,
    maxZoom: 140,
    nodeWidth: 268,
    canvasWidth: 1472,
    canvasHeight: 832,
    viewportWidth: 0,
    viewportHeight: 0,
    canvasRevision: 0,
    undoStack: [],
    redoStack: [],
    nodeGroups: [
      {
        label: "Trigger",
        nodes: [
          { type: "trigger", kind: "message_received", label: "Message received", hint: "Any inbound message", icon: "ph ph-chat-circle", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "keyword_matched", label: "Keyword matched", hint: "Inbound text contains a word", icon: "ph ph-magnifying-glass", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "campaign_replied", label: "Campaign replied", hint: "A campaign recipient replies", icon: "ph ph-megaphone-simple", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "button_clicked", label: "Button clicked", hint: "Quick reply or list option", icon: "ph ph-cursor-click", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "tag_added", label: "Tag added", hint: "Contact receives a tag", icon: "ph ph-tag", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "contact_created", label: "Contact created", hint: "A new contact appears", icon: "ph ph-user-plus", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "no_reply_after_delay", label: "No reply after delay", hint: "A delayed timeout event", icon: "ph ph-hourglass", tone: "warning", badge: "TRIGGER" },
          { type: "trigger", kind: "conversation_opened", label: "Conversation opened", hint: "A chat is opened", icon: "ph ph-chats-circle", tone: "success", badge: "TRIGGER" },
          { type: "trigger", kind: "template_delivered", label: "Template delivered", hint: "Template delivery status", icon: "ph ph-check-circle", tone: "success", badge: "TRIGGER" },
        ],
      },
      {
        label: "Condition",
        nodes: [
          { type: "condition", kind: "message_contains", label: "Message contains", hint: "Check inbound text", icon: "ph ph-git-branch", tone: "purple", badge: "IF" },
          { type: "condition", kind: "contact_has_tag", label: "Contact has tag", hint: "Branch by tag", icon: "ph ph-tag", tone: "purple", badge: "IF" },
          { type: "condition", kind: "contact_city", label: "Contact is from city", hint: "Branch by city", icon: "ph ph-map-pin", tone: "purple", badge: "IF" },
          { type: "condition", kind: "inside_business_hours", label: "Inside business hours", hint: "9 AM to 5 PM", icon: "ph ph-clock", tone: "purple", badge: "IF" },
          { type: "condition", kind: "reply_matches", label: "Reply matches", hint: "Check for YES or custom text", icon: "ph ph-check-square", tone: "purple", badge: "IF" },
          { type: "condition", kind: "no_reply_elapsed", label: "No reply elapsed", hint: "No inbound reply in minutes", icon: "ph ph-timer", tone: "purple", badge: "IF" },
          { type: "condition", kind: "conversation_assignment", label: "Assigned or unassigned", hint: "Check conversation owner", icon: "ph ph-user-switch", tone: "purple", badge: "IF" },
        ],
      },
      {
        label: "Action",
        nodes: [
          { type: "action", kind: "send_whatsapp_message", label: "Send WhatsApp message", hint: "Plain text reply", icon: "ph ph-chat-circle", tone: "success", badge: "ACTION" },
          { type: "action", kind: "send_approved_template", label: "Send approved template", hint: "WhatsApp template", icon: "ph ph-layout", tone: "info", badge: "ACTION" },
          { type: "action", kind: "add_contact_tag", label: "Add contact tag", hint: "Tag the contact", icon: "ph ph-tag", tone: "success", badge: "ACTION" },
          { type: "action", kind: "remove_tag", label: "Remove tag", hint: "Remove a contact tag", icon: "ph ph-tag-chevron", tone: "warning", badge: "ACTION" },
          { type: "action", kind: "assign_conversation", label: "Assign conversation", hint: "Hand off to a workspace agent", icon: "ph ph-user-switch", tone: "info", badge: "ACTION" },
          { type: "action", kind: "create_lead", label: "Create lead", hint: "Create CRM lead record", icon: "ph ph-funnel", tone: "success", badge: "ACTION" },
          { type: "action", kind: "update_lead_stage", label: "Move lead stage", hint: "Move the open CRM lead", icon: "ph ph-kanban", tone: "info", badge: "ACTION" },
          { type: "action", kind: "create_task", label: "Create follow-up task", hint: "Schedule agent follow-up", icon: "ph ph-calendar-check", tone: "warning", badge: "ACTION" },
          { type: "action", kind: "mark_lead_won", label: "Mark lead won", hint: "Close the opportunity as won", icon: "ph ph-trophy", tone: "success", badge: "ACTION" },
          { type: "action", kind: "mark_lead_lost", label: "Mark lead lost", hint: "Close the opportunity as lost", icon: "ph ph-x-circle", tone: "error", badge: "ACTION" },
          { type: "action", kind: "call_webhook", label: "Call webhook", hint: "POST to an external URL", icon: "ph ph-webhooks-logo", tone: "success", badge: "ACTION" },
          { type: "action", kind: "generate_chatbot_reply", label: "Ask chatbot", hint: "Generate and send chatbot answer", icon: "ph ph-robot", tone: "purple", badge: "ACTION" },
          { type: "action", kind: "notify_admin", label: "Notify admin", hint: "Log an internal notification", icon: "ph ph-bell-ringing", tone: "info", badge: "ACTION" },
          { type: "action", kind: "mark_conversation_resolved", label: "Resolve conversation", hint: "Mark chat resolved", icon: "ph ph-check-circle", tone: "success", badge: "ACTION" },
        ],
      },
      {
        label: "Delay",
        nodes: [
          { type: "delay", kind: "wait_duration", label: "Wait duration", hint: "Wait minutes, hours, or days", icon: "ph ph-timer", tone: "warning", badge: "DELAY" },
          { type: "delay", kind: "wait_until_time", label: "Wait until time", hint: "Resume at a fixed time", icon: "ph ph-clock-countdown", tone: "warning", badge: "DELAY" },
          { type: "delay", kind: "wait_until_business_hour", label: "Wait until business hour", hint: "Resume during working hours", icon: "ph ph-buildings", tone: "warning", badge: "DELAY" },
        ],
      },
      {
        label: "Exit / Goal",
        nodes: [
          { type: "end", kind: "customer_replied_yes", label: "Customer replied YES", hint: "End after positive reply", icon: "ph ph-thumbs-up", tone: "error", badge: "GOAL" },
          { type: "end", kind: "customer_booked_appointment", label: "Booked appointment", hint: "Appointment reached", icon: "ph ph-calendar-check", tone: "error", badge: "GOAL" },
          { type: "end", kind: "customer_paid", label: "Customer paid", hint: "Payment reached", icon: "ph ph-credit-card", tone: "error", badge: "GOAL" },
          { type: "end", kind: "customer_became_lead", label: "Became lead", hint: "Lead record exists", icon: "ph ph-funnel", tone: "error", badge: "GOAL" },
          { type: "end", kind: "customer_unsubscribed", label: "Unsubscribed", hint: "Stop after opt-out", icon: "ph ph-prohibit", tone: "error", badge: "GOAL" },
          { type: "end", kind: "human_agent_joined", label: "Human agent joined", hint: "Conversation assigned", icon: "ph ph-user-focus", tone: "error", badge: "GOAL" },
        ],
      },
    ],

    init() {
      this.nodes = this.nodes.map((node) => this.normalizeNode(node));
      this.edges = this.edges.map((edge) => this.normalizeEdge(edge)).filter(Boolean);
      this.aiSummary = Array.isArray(this.initialAiDraft?.summary) ? this.initialAiDraft.summary : [];
      this.aiNotice = this.initialAiDraft ? "AI generated a draft flow. Review it, adjust anything you need, then save." : "";
      this.$nextTick(() => this.syncCanvasSize());

      window.addEventListener("resize", () => this.syncCanvasSize());
      document.addEventListener("mousemove", (event) => this.dragNode(event));
      document.addEventListener("mouseup", () => this.stopNodeDrag());
      document.addEventListener("pointermove", (event) => this.dragConnection(event));
      document.addEventListener("pointerup", (event) => this.stopConnectionDrag(event));
      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          this.cancelConnection();
        }

        this.handleCanvasKeydown(event);
      });
    },

    get selectedNode() {
      return this.nodes.find((node) => node.id === this.selectedNodeId) || null;
    },

    get stepTypes() {
      return this.nodeGroups.flatMap((group) => group.nodes);
    },

    get filteredGroups() {
      const search = this.nodeSearch.trim().toLowerCase();

      if (!search) {
        return this.nodeGroups;
      }

      return this.nodeGroups
        .map((group) => ({
          ...group,
          nodes: group.nodes.filter((node) => `${node.label} ${node.hint}`.toLowerCase().includes(search)),
        }))
        .filter((group) => group.nodes.length > 0);
    },

    crmStagesFor(pipelineId) {
      if (!pipelineId) {
        return this.crmStages;
      }

      return this.crmStages.filter((stage) => String(stage.pipeline_id) === String(pipelineId));
    },

    get nodeCount() {
      return this.stepTypes.length;
    },

    get placedNodeCount() {
      return this.nodes.length;
    },

    get connectionCount() {
      return this.edges.length;
    },

    get hasNodes() {
      return this.nodes.length > 0;
    },

    get nodesPayload() {
      return JSON.stringify(this.nodes);
    },

    get edgesPayload() {
      return JSON.stringify(this.edges);
    },

    get selectedNodePorts() {
      return this.selectedNode?.ports || this.defaultPortsFor(this.selectedNode);
    },

    get canUndo() {
      return this.undoStack.length > 0;
    },

    get canRedo() {
      return this.redoStack.length > 0;
    },

    get zoomScale() {
      return this.zoom / 100;
    },

    syncCanvasSize() {
      this.viewportWidth = this.$refs.canvas?.clientWidth || 0;
      this.viewportHeight = this.$refs.canvas?.clientHeight || 0;
      this.redrawCanvas();
    },

    redrawCanvas() {
      this.canvasRevision += 1;
    },

    toggleAiPanel() {
      this.showAiPanel = !this.showAiPanel;
      this.aiError = "";

      if (this.showAiPanel) {
        this.$nextTick(() => this.$refs.aiPrompt?.focus());
      }
    },

    async generateWithAi() {
      const prompt = this.aiPrompt.trim();

      this.aiError = "";
      this.aiNotice = "";

      if (prompt.length < 10) {
        this.aiError = "Describe the automation in at least 10 characters.";
        return;
      }

      if (!this.generateUrl) {
        this.aiError = "AI generation is not available on this page.";
        return;
      }

      this.aiGenerating = true;

      try {
        const response = await fetch(this.generateUrl, {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
          },
          body: JSON.stringify({ prompt }),
        });

        const payload = await response.json();

        if (!response.ok) {
          this.aiError = payload.message || "AI could not generate the flow. Please try again.";
          return;
        }

        this.applyGeneratedFlow(payload);
      } catch (error) {
        this.aiError = "AI generation failed. Please check your connection and try again.";
      } finally {
        this.aiGenerating = false;
      }
    },

    applyGeneratedFlow(payload) {
      const flow = payload.flow || {};

      this.nodes = (Array.isArray(flow.nodes) ? flow.nodes : []).map((node) => this.normalizeNode(node));
      this.edges = (Array.isArray(flow.edges) ? flow.edges : []).map((edge) => this.normalizeEdge(edge)).filter(Boolean);
      this.removeInvalidEdges();
      this.selectedNodeId = this.nodes[0]?.id || null;
      this.aiSummary = Array.isArray(payload.summary) ? payload.summary : [];
      this.aiNotice = payload.source === "ai"
        ? "AI generated the flow and placed it on the canvas."
        : "Generated a guided AI-style draft. Connect an AI provider for deeper prompt interpretation.";

      if (payload.name && this.$refs.nameInput) {
        this.$refs.nameInput.value = payload.name;
      }

      if (payload.description && this.$refs.descriptionInput) {
        this.$refs.descriptionInput.value = payload.description;
      }
    },

    prepareSubmit() {
      this.edges = this.edges.filter((edge) => this.edgeIsValid(edge));
      this.enforceSingleInboundEdges();
    },

    async testFlow() {
      this.testError = "";
      this.testResult = null;

      if (!this.hasNodes) {
        this.testError = "Add at least one trigger or action before testing.";
        return;
      }

      if (!this.testUrl) {
        this.testError = "Automation testing is not available on this page.";
        return;
      }

      this.prepareSubmit();
      this.testRunning = true;

      try {
        const response = await fetch(this.testUrl, {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
          },
          body: JSON.stringify({
            nodes: this.nodes,
            edges: this.edges,
            message: this.testMessage,
          }),
        });

        const payload = await response.json();

        if (!response.ok) {
          this.testError = payload.message || "Automation test failed.";
          return;
        }

        this.testResult = payload;
        this.$nextTick(() => {
          document.querySelector("[x-show='testResult || testError']")?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      } catch (error) {
        this.testError = "Automation test failed. Please try again.";
      } finally {
        this.testRunning = false;
      }
    },

    clearTestResult() {
      this.testResult = null;
      this.testError = "";
    },

    togglePalette() {
      this.paletteCollapsed = !this.paletteCollapsed;
      this.$nextTick(() => this.syncCanvasSize());
    },

    commitFlowState() {
      this.undoStack.push(this.flowSnapshot());

      if (this.undoStack.length > 50) {
        this.undoStack.shift();
      }

      this.redoStack = [];
    },

    flowSnapshot() {
      return JSON.stringify({
        nodes: this.nodes,
        edges: this.edges,
        selectedNodeId: this.selectedNodeId,
      });
    },

    restoreFlowSnapshot(snapshot) {
      if (!snapshot) return;

      const flow = JSON.parse(snapshot);
      this.nodes = (Array.isArray(flow.nodes) ? flow.nodes : []).map((node) => this.normalizeNode(node));
      this.edges = (Array.isArray(flow.edges) ? flow.edges : []).map((edge) => this.normalizeEdge(edge)).filter(Boolean);
      this.selectedNodeId = flow.selectedNodeId || this.nodes[0]?.id || null;
      this.cancelConnection();
    },

    undoFlow() {
      if (!this.canUndo) return;

      this.redoStack.push(this.flowSnapshot());
      this.restoreFlowSnapshot(this.undoStack.pop());
    },

    redoFlow() {
      if (!this.canRedo) return;

      this.undoStack.push(this.flowSnapshot());
      this.restoreFlowSnapshot(this.redoStack.pop());
    },

    resetViewport() {
      this.zoom = 95;
      this.$nextTick(() => {
        if (this.$refs.canvas) {
          this.$refs.canvas.scrollLeft = 0;
          this.$refs.canvas.scrollTop = 0;
        }
      });
    },

    zoomIn() {
      this.setZoom(this.zoom + 5);
    },

    zoomOut() {
      this.setZoom(this.zoom - 5);
    },

    resetZoom() {
      this.setZoom(95);
    },

    clampZoom(value) {
      return Math.max(this.minZoom, Math.min(this.maxZoom, Math.round(value)));
    },

    setZoom(value, focalPoint = null) {
      const canvas = this.$refs.canvas;
      const previousScale = this.zoomScale;
      const nextZoom = this.clampZoom(value);

      if (!canvas || nextZoom === this.zoom) {
        this.zoom = nextZoom;
        return;
      }

      const focal = focalPoint || {
        x: canvas.clientWidth / 2,
        y: canvas.clientHeight / 2,
      };
      const worldX = (canvas.scrollLeft + focal.x) / previousScale;
      const worldY = (canvas.scrollTop + focal.y) / previousScale;

      this.zoom = nextZoom;

      this.$nextTick(() => {
        canvas.scrollLeft = Math.max(0, worldX * this.zoomScale - focal.x);
        canvas.scrollTop = Math.max(0, worldY * this.zoomScale - focal.y);
        this.syncCanvasSize();
      });
    },

    handleCanvasWheel(event) {
      if (!event.ctrlKey && !event.metaKey) {
        return;
      }

      event.preventDefault();

      const canvas = this.$refs.canvas;

      if (!canvas) {
        return;
      }

      const rect = canvas.getBoundingClientRect();
      const direction = event.deltaY > 0 ? -1 : 1;

      this.setZoom(this.zoom + direction * 5, {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
      });
    },

    handleCanvasKeydown(event) {
      if (!event.ctrlKey && !event.metaKey) {
        return;
      }

      const target = event.target;

      if (target?.closest?.("input, textarea, select, [contenteditable='true']")) {
        return;
      }

      const zoomInKeys = ["+", "="];
      const zoomOutKeys = ["-", "_"];

      if (zoomInKeys.includes(event.key)) {
        event.preventDefault();
        this.zoomIn();
        return;
      }

      if (zoomOutKeys.includes(event.key)) {
        event.preventDefault();
        this.zoomOut();
        return;
      }

      if (event.key === "0") {
        event.preventDefault();
        this.resetZoom();
      }
    },

    fitToNodes() {
      if (!this.hasNodes || !this.$refs.canvas) {
        this.resetViewport();
        return;
      }

      const padding = 120;
      const maxX = Math.max(...this.nodes.map((node) => Number(node.x || 0) + this.nodeWidth));
      const maxY = Math.max(...this.nodes.map((node) => Number(node.y || 0) + this.nodeHeight(node)));
      const minX = Math.min(...this.nodes.map((node) => Number(node.x || 0)));
      const minY = Math.min(...this.nodes.map((node) => Number(node.y || 0)));
      const widthScale = this.viewportWidth / Math.max(1, maxX - minX + padding * 2);
      const heightScale = this.viewportHeight / Math.max(1, maxY - minY + padding * 2);

      this.zoom = Math.max(55, Math.min(110, Math.floor(Math.min(widthScale, heightScale) * 100)));

      this.$nextTick(() => {
        this.$refs.canvas.scrollLeft = Math.max(0, (minX - padding) * this.zoomScale);
        this.$refs.canvas.scrollTop = Math.max(0, (minY - padding) * this.zoomScale);
      });
    },

    canvasCenterPoint() {
      const canvas = this.$refs.canvas;

      if (!canvas) {
        return null;
      }

      return {
        x: Math.round((canvas.scrollLeft + canvas.clientWidth / 2) / this.zoomScale - this.nodeWidth / 2),
        y: Math.round((canvas.scrollTop + canvas.clientHeight / 2) / this.zoomScale - 70),
      };
    },

    startPaletteDrag(step, event) {
      this.paletteDragType = step.kind;
      event.dataTransfer.effectAllowed = "copy";
      event.dataTransfer.setData("application/json", JSON.stringify(step));
    },

    dropNode(event) {
      const raw = event.dataTransfer.getData("application/json");
      const step = raw ? JSON.parse(raw) : this.stepByKind(this.paletteDragType);
      this.addNode(step || this.stepByKind("send_whatsapp_message"), this.eventPoint(event));
      this.paletteDragType = null;
      this.isDropping = false;
    },

    addNode(stepOrType, point = null) {
      const step = typeof stepOrType === "string" ? this.stepByKind(stepOrType) || this.stepByType(stepOrType) : stepOrType;
      const resolved = step || this.stepByKind("send_whatsapp_message");
      const index = this.nodes.length + 1;
      const fallback = {
        x: 220 + ((index - 1) % 3) * 380,
        y: 120 + Math.floor((index - 1) / 3) * 190,
      };
      const node = this.normalizeNode({
        id: this.uniqueId("n"),
        type: resolved.type,
        kind: resolved.kind,
        label: resolved.label,
        x: point?.x ?? fallback.x,
        y: point?.y ?? fallback.y,
        data: this.defaultDataFor(resolved),
      });

      this.commitFlowState();
      this.nodes.push(node);
      this.selectNode(node.id);
    },

    addStarterNode(kind = "trigger") {
      this.addNode(kind, {
        x: 460,
        y: 210,
      });
    },

    selectNode(id) {
      this.selectedNodeId = id;
    },

    closeInspector() {
      this.selectedNodeId = null;
      this.inspectorOpen = false;
      this.inspectorMinimized = false;
      this.sourcePort = null;
      this.cancelConnection();
    },

    focusInspector(id) {
      this.selectNode(id);
      this.inspectorOpen = true;
      this.inspectorMinimized = false;
    },

    minimizeInspector() {
      if (!this.selectedNodeId) return;

      this.inspectorOpen = true;
      this.inspectorMinimized = true;
    },

    expandInspector() {
      if (!this.selectedNodeId) return;

      this.inspectorOpen = true;
      this.inspectorMinimized = false;
    },

    clickPort(nodeId, portId, direction) {
      const node = this.findNode(nodeId);
      const port = this.findPort(node, portId);

      if (!node || !port) return;

      this.selectNode(nodeId);

      if (direction === "output") {
        this.sourcePort = { nodeId, portId };
        return;
      }

      if (!this.sourcePort) {
        return;
      }

      this.connectPorts(this.sourcePort.nodeId, this.sourcePort.portId, nodeId, portId);
      this.sourcePort = null;
    },

    startConnectionDrag(nodeId, portId, event) {
      const node = this.findNode(nodeId);
      const port = this.findPort(node, portId);

      if (!node || port?.direction !== "output") return;

      event.preventDefault();
      event.stopPropagation();

      const point = this.eventPoint(event);
      this.selectNode(nodeId);
      this.sourcePort = { nodeId, portId };
      this.connectionDraft = {
        sourceNodeId: nodeId,
        sourcePortId: portId,
        x: point.x,
        y: point.y,
      };
      this.hotInputPort = null;
    },

    dragConnection(event) {
      if (!this.connectionDraft) return;

      const point = this.eventPoint(event);
      this.connectionDraft.x = point.x;
      this.connectionDraft.y = point.y;
      this.hotInputPort = this.inputPortFromEvent(event);
    },

    stopConnectionDrag(event) {
      if (!this.connectionDraft) return;

      const target = this.inputPortFromEvent(event);

      if (target) {
        this.connectPorts(
          this.connectionDraft.sourceNodeId,
          this.connectionDraft.sourcePortId,
          target.nodeId,
          target.portId,
        );
      }

      this.cancelConnection();
    },

    cancelConnection() {
      this.connectionDraft = null;
      this.hotInputPort = null;
      this.sourcePort = null;
    },

    connectPorts(sourceNodeId, sourcePortId, targetNodeId, targetPortId) {
      if (sourceNodeId === targetNodeId || this.hasEdge(sourceNodeId, sourcePortId, targetNodeId, targetPortId)) {
        return false;
      }

      const source = this.findNode(sourceNodeId);
      const target = this.findNode(targetNodeId);
      const sourcePort = this.findPort(source, sourcePortId);
      const targetPort = this.findPort(target, targetPortId);

      if (sourcePort?.direction !== "output" || targetPort?.direction !== "input") {
        return false;
      }

      this.commitFlowState();
      this.edges = this.edges.filter((edge) => !(edge.targetNodeId === targetNodeId && edge.targetPortId === targetPortId));
      this.edges.push({
        id: this.uniqueId("e"),
        sourceNodeId,
        sourcePortId,
        targetNodeId,
        targetPortId,
      });

      return true;
    },

    inputPortFromEvent(event) {
      const element = document.elementFromPoint(event.clientX, event.clientY)?.closest("[data-flow-port='input']");

      if (!element) {
        return null;
      }

      return {
        nodeId: element.dataset.nodeId,
        portId: element.dataset.portId,
      };
    },

    inputPortIsHot(nodeId, portId) {
      if (!this.connectionDraft) return false;

      return this.connectionDraft.sourceNodeId !== nodeId && this.findPort(this.findNode(nodeId), portId)?.direction === "input";
    },

    inputPortIsHovered(nodeId, portId) {
      return this.hotInputPort?.nodeId === nodeId && this.hotInputPort?.portId === portId;
    },

    outputPortIsActive(nodeId, portId) {
      return (
        this.sourcePort?.nodeId === nodeId &&
        this.sourcePort?.portId === portId
      );
    },

    canvasPointerDown(event) {
      if (event.target === this.$refs.surface || event.target.classList.contains("flow-builder-zoom-layer")) {
        this.sourcePort = null;
        this.cancelConnection();
      }
    },

    deleteNode(id = this.selectedNodeId) {
      if (!id) return;

      this.commitFlowState();
      this.nodes = this.nodes.filter((node) => node.id !== id);
      this.edges = this.edges.filter((edge) => edge.sourceNodeId !== id && edge.targetNodeId !== id);

      if (this.sourcePort?.nodeId === id) {
        this.sourcePort = null;
      }

      if (this.connectionDraft?.sourceNodeId === id) {
        this.cancelConnection();
      }

      if (this.selectedNodeId === id) {
        this.selectedNodeId = null;
        this.inspectorOpen = false;
        this.inspectorMinimized = false;
      }

    },

    clearFlow() {
      if (!this.hasNodes && this.edges.length === 0) return;

      this.commitFlowState();
      this.nodes = [];
      this.edges = [];
      this.selectedNodeId = null;
      this.inspectorOpen = false;
      this.inspectorMinimized = false;
      this.cancelConnection();
    },

    startNodeDrag(id, event) {
      const node = this.findNode(id);
      if (!node) return;

      const point = this.eventPoint(event);
      this.draggingNodeId = id;
      this.selectNode(id);
      this.dragStartSnapshot = this.flowSnapshot();
      this.dragOffset = {
        x: point.x - Number(node.x || 0),
        y: point.y - Number(node.y || 0),
      };
    },

    dragNode(event) {
      if (!this.draggingNodeId) return;

      const node = this.findNode(this.draggingNodeId);
      if (!node) return;

      const point = this.eventPoint(event);
      node.x = Math.max(12, Math.round(point.x - this.dragOffset.x));
      node.y = Math.max(12, Math.round(point.y - this.dragOffset.y));
      this.redrawCanvas();
    },

    stopNodeDrag() {
      if (this.draggingNodeId && this.dragStartSnapshot && this.dragStartSnapshot !== this.flowSnapshot()) {
        this.undoStack.push(this.dragStartSnapshot);

        if (this.undoStack.length > 50) {
          this.undoStack.shift();
        }

        this.redoStack = [];
      }

      this.draggingNodeId = null;
      this.dragStartSnapshot = null;
    },

    addQuickReplyOption(node) {
      if (!node || node.data.options.length >= 3) return;

      const next = node.data.options.length + 1;
      node.data.options.push(`Option ${String.fromCharCode(64 + next)}`);
      node.ports = this.defaultPortsFor(node);
      this.removeInvalidEdges();
    },

    removeQuickReplyOption(node, index) {
      if (!node || node.data.options.length <= 1) return;

      node.data.options.splice(index, 1);
      node.ports = this.defaultPortsFor(node);
      this.removeInvalidEdges();
    },

    edgePath(edge) {
      const source = this.portPoint(edge.sourceNodeId, edge.sourcePortId, "output");
      const target = this.portPoint(edge.targetNodeId, edge.targetPortId, "input");

      if (!source || !target) {
        return "";
      }

      return this.curvePath(source, target);
    },

    draftEdgePath() {
      if (!this.connectionDraft) return "";

      const source = this.portPoint(this.connectionDraft.sourceNodeId, this.connectionDraft.sourcePortId, "output");

      if (!source) return "";

      return this.curvePath(source, { x: this.connectionDraft.x, y: this.connectionDraft.y });
    },

    curvePath(source, target) {
      const horizontalDistance = Math.abs(target.x - source.x);
      const verticalDistance = Math.abs(target.y - source.y);
      const handle = Math.min(160, Math.max(72, horizontalDistance * 0.45, verticalDistance * 0.3));

      return `M ${source.x} ${source.y} C ${source.x + handle} ${source.y}, ${target.x - handle} ${target.y}, ${target.x} ${target.y}`;
    },

    edgeToneClass(edge) {
      const source = this.findNode(edge.sourceNodeId);
      const port = this.findPort(source, edge.sourcePortId);

      if (port?.tone === "success") return "flow-edge--success";
      if (port?.tone === "error") return "flow-edge--error";

      return "";
    },

    edgesMarkup() {
      this.canvasRevision;

      return this.edges
        .map((edge) => {
          const d = this.edgePath(edge);
          const toneClass = this.edgeToneClass(edge);

          if (!d) {
            return "";
          }

          return `<g class="flow-edge-group ${toneClass}"><path d="${d}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="flow-edge" marker-end="url(#automationArrow)"></path><path d="${d}" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" class="flow-edge__signal"></path></g>`;
        })
        .join("");
    },

    draftEdgeMarkup() {
      this.canvasRevision;

      const d = this.draftEdgePath();

      if (!d) {
        return "";
      }

      return `<path d="${d}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="7 7" stroke-linecap="round" class="flow-edge flow-edge--draft"></path>`;
    },

    surfaceStyle() {
      const width = Math.max(this.canvasWidth * this.zoomScale, this.viewportWidth);
      const height = Math.max(this.canvasHeight * this.zoomScale, this.viewportHeight);

      return `width: ${width}px; height: ${height}px`;
    },

    zoomLayerStyle() {
      return `width: ${this.canvasWidth}px; height: ${this.canvasHeight}px; transform: scale(${this.zoomScale})`;
    },

    portPoint(nodeId, portId, direction) {
      const node = this.findNode(nodeId);
      const port = this.findPort(node, portId);

      if (!node || !port) return null;

      return {
        x: Number(node.x) + (direction === "output" ? this.nodeWidth : 0),
        y: Number(node.y) + port.y,
      };
    },

    nodeStyle(node) {
      return `left: ${Number(node.x || 0)}px; top: ${Number(node.y || 0)}px`;
    },

    toolbarStyle(node) {
      if (!node) return "";

      return `left: ${Number(node.x || 0) + 82}px; top: ${Math.max(8, Number(node.y || 0) - 54)}px`;
    },

    portStyle(port, side) {
      return `top: ${port.y}px; ${side === "right" ? "right" : "left"}: -10px`;
    },

    minimapNodeStyle(node) {
      this.canvasRevision;

      const scaleX = 168 / this.canvasWidth;
      const scaleY = 88 / this.canvasHeight;
      const width = Math.max(14, this.nodeWidth * scaleX);
      const height = Math.max(8, this.nodeHeight(node) * scaleY);

      return [
        `left: ${Number(node.x || 0) * scaleX}px`,
        `top: ${Number(node.y || 0) * scaleY}px`,
        `width: ${width}px`,
        `height: ${height}px`,
      ].join("; ");
    },

    minimapViewportStyle() {
      this.canvasRevision;

      const canvas = this.$refs.canvas;

      if (!canvas) return "";

      const scaleX = 168 / this.canvasWidth;
      const scaleY = 88 / this.canvasHeight;

      return [
        `left: ${(canvas.scrollLeft / this.zoomScale) * scaleX}px`,
        `top: ${(canvas.scrollTop / this.zoomScale) * scaleY}px`,
        `width: ${(canvas.clientWidth / this.zoomScale) * scaleX}px`,
        `height: ${(canvas.clientHeight / this.zoomScale) * scaleY}px`,
      ].join("; ");
    },

    jumpToMinimap(event) {
      const canvas = this.$refs.canvas;

      if (!canvas) return;

      const rect = event.currentTarget.getBoundingClientRect();
      const x = ((event.clientX - rect.left) / rect.width) * this.canvasWidth;
      const y = ((event.clientY - rect.top) / rect.height) * this.canvasHeight;

      canvas.scrollLeft = Math.max(0, (x - canvas.clientWidth / this.zoomScale / 2) * this.zoomScale);
      canvas.scrollTop = Math.max(0, (y - canvas.clientHeight / this.zoomScale / 2) * this.zoomScale);
    },

    nodeClass(node) {
      return `flow-node-card--${node.kind || node.type || "action"}`;
    },

    nodeIcon(type, kind = null) {
      const step = this.stepByKind(kind) || this.stepByType(type);

      return step?.icon || "ph ph-paper-plane-tilt";
    },

    nodeTypeLabel(type, kind = null) {
      return this.stepByKind(kind)?.label || this.stepByType(type)?.label || "Action";
    },

    nodeBadge(node) {
      return this.stepByKind(node?.kind)?.badge || node?.type || "NODE";
    },

    nodeGroupLabel(node) {
      const match = this.nodeGroups.find((group) => group.nodes.some((step) => step.kind === node?.kind));

      return match?.label || "Node";
    },

    nodeTone(node) {
      return node?.data?.tone || this.stepByKind(node?.kind)?.tone || "success";
    },

    nodeHeight(node) {
      if (!node) return 122;
      if (node.kind === "quick_replies") return 128 + (node.data.options?.length || 0) * 36;
      if (node.type === "condition") return 122;

      return 122;
    },

    defaultDataFor(step) {
      if (step.kind === "quick_replies") {
        return {
          prompt: "Pick one",
          options: ["Option A", "Option B"],
          detail: "",
          favorite: false,
          tone: step.tone,
        };
      }

      if (step.type === "condition") {
        const conditionDefaults = {
          message_contains: { field: "message_body", operator: "contains", value: "price", expression: "{{last_reply}} contains price" },
          contact_has_tag: { field: "tag", operator: "has_tag", value: "Hot Lead", expression: "contact has tag Hot Lead" },
          contact_city: { field: "city", operator: "equals", value: "Dhaka", expression: "contact.city = Dhaka" },
          inside_business_hours: { field: "business_hours", operator: "inside_business_hours", value: "", expression: "inside business hours" },
          reply_matches: { field: "last_reply", operator: "equals", value: "YES", expression: "{{last_reply}} = YES" },
          no_reply_elapsed: { field: "last_reply", operator: "not_replied_for", value: "120", expression: "no reply for 120 minutes" },
          conversation_assignment: { field: "assigned_to", operator: "is_empty", value: "", expression: "conversation is unassigned" },
        };

        return {
          ...(conditionDefaults[step.kind] || conditionDefaults.message_contains),
          trueLabel: "Matched",
          falseLabel: "Not matched",
          detail: "",
          favorite: false,
          tone: step.tone,
        };
      }

      const defaults = {
        detail: step.hint || "",
        favorite: false,
        tone: step.tone,
      };

      if (step.type === "trigger") {
        return {
          ...defaults,
          keyword: step.kind === "keyword_matched" ? "price" : "",
          event: step.kind,
        };
      }

      if (step.type === "delay") {
        return {
          ...defaults,
          value: 5,
          unit: "minutes",
        };
      }

      if (step.kind === "send_whatsapp_message") {
        return {
          ...defaults,
          body: "Hi {{first_name}}, thanks for your message.",
        };
      }

      if (step.kind === "send_approved_template") {
        return {
          ...defaults,
          template_id: "",
          template_name: "",
        };
      }

      if (["add_tag", "add_contact_tag", "remove_tag"].includes(step.kind)) {
        return {
          ...defaults,
          tag_id: "",
          tag_name: "Hot Lead",
        };
      }

      if (["assign_agent", "assign_conversation"].includes(step.kind)) {
        return {
          ...defaults,
          agent_id: "",
        };
      }

      if (["create_lead", "update_lead_stage"].includes(step.kind)) {
        return { ...defaults, pipeline_id: "", stage_id: "", value: "" };
      }

      if (step.kind === "create_task") {
        return { ...defaults, title: "Follow up", due_in_minutes: 60, priority: "normal", assigned_to: "" };
      }

      if (step.kind === "mark_lead_lost") {
        return { ...defaults, lost_reason: "" };
      }

      if (step.kind === "call_webhook") {
        return {
          ...defaults,
          url: "https://example.com/webhook",
        };
      }

      if (step.kind === "notify_admin") {
        return {
          ...defaults,
          message: "Automation needs attention for {{name}}.",
        };
      }

      if (["generate_chatbot_reply", "generate_ai_reply", "ai_assistant"].includes(step.kind)) {
        return {
          ...defaults,
          chatbot_id: this.chatbots[0]?.id ? String(this.chatbots[0].id) : "",
          prompt: "",
        };
      }

      if (step.type === "end") {
        return {
          ...defaults,
          reason: step.label,
        };
      }

      return defaults;
    },

    defaultPortsFor(node) {
      if (!node) return [];

      const ports = [];

      if (node.type !== "trigger") {
        ports.push({ id: "input", label: "Input", direction: "input", y: 61 });
      }

      if (node.kind === "quick_replies") {
        (node.data.options || []).forEach((option, index) => {
          ports.push({ id: `option_${index + 1}`, label: option, direction: "output", y: 120 + index * 36 });
        });

        return ports;
      }

      if (node.type === "condition") {
        ports.push({ id: "true", label: node.data.trueLabel || "Matched", direction: "output", y: 61, tone: "success" });
        ports.push({ id: "false", label: node.data.falseLabel || "Not matched", direction: "output", y: 97, tone: "error" });

        return ports;
      }

      if (node.type !== "end") {
        ports.push({ id: "default", label: "Next", direction: "output", y: 61 });
      }

      return ports;
    },

    eventPoint(event) {
      const canvas = this.$refs.canvas;
      const rect = canvas.getBoundingClientRect();

      return {
        x: Math.round((event.clientX - rect.left + canvas.scrollLeft) / this.zoomScale),
        y: Math.round((event.clientY - rect.top + canvas.scrollTop) / this.zoomScale),
      };
    },

    findNode(id) {
      return this.nodes.find((node) => node.id === id);
    },

    findPort(node, portId) {
      return (node?.ports || []).find((port) => port.id === portId);
    },

    hasEdge(sourceNodeId, sourcePortId, targetNodeId, targetPortId) {
      return this.edges.some((edge) => (
        edge.sourceNodeId === sourceNodeId &&
        edge.sourcePortId === sourcePortId &&
        edge.targetNodeId === targetNodeId &&
        edge.targetPortId === targetPortId
      ));
    },

    edgeIsValid(edge) {
      const source = this.findNode(edge.sourceNodeId);
      const target = this.findNode(edge.targetNodeId);
      const sourcePort = this.findPort(source, edge.sourcePortId);
      const targetPort = this.findPort(target, edge.targetPortId);

      return Boolean(source && target && sourcePort?.direction === "output" && targetPort?.direction === "input");
    },

    removeInvalidEdges() {
      this.edges = this.edges.filter((edge) => this.edgeIsValid(edge));
      this.enforceSingleInboundEdges();
    },

    enforceSingleInboundEdges() {
      const edgesByTargetPort = new Map();

      this.edges.forEach((edge) => {
        edgesByTargetPort.set(`${edge.targetNodeId}:${edge.targetPortId}`, edge);
      });

      this.edges = Array.from(edgesByTargetPort.values());
    },

    normalizeNode(node) {
      const step = this.stepByKind(node.kind) || this.stepByType(node.type);
      const data = {
        ...this.defaultDataFor(step || { kind: node.kind, tone: "success", hint: "" }),
        ...(node.data || node.config || {}),
      };
      const normalized = {
        id: String(node.id || this.uniqueId("n")),
        type: node.type || step?.type || "action",
        kind: node.kind || step?.kind || node.type || "action",
        label: node.label || step?.label || this.defaultLabel(node.type || "action"),
        x: Number(node.x ?? 220),
        y: Number(node.y ?? 120),
        data,
        ports: Array.isArray(node.ports) ? node.ports : [],
      };

      normalized.ports = this.defaultPortsFor(normalized);

      return normalized;
    },

    normalizeEdge(edge) {
      const sourceNodeId = edge.sourceNodeId || edge.source;
      const targetNodeId = edge.targetNodeId || edge.target;
      const source = this.findNode(sourceNodeId);
      const target = this.findNode(targetNodeId);
      const requestedSourcePortId = edge.sourcePortId || edge.sourcePort;
      const requestedTargetPortId = edge.targetPortId || edge.targetPort;
      const sourcePortId = this.findPort(source, requestedSourcePortId)?.id || this.firstOutputPort(source)?.id;
      const targetPortId = this.findPort(target, requestedTargetPortId)?.id || this.firstInputPort(target)?.id;

      if (!sourceNodeId || !targetNodeId || sourceNodeId === targetNodeId || !sourcePortId || !targetPortId) {
        return null;
      }

      return {
        id: String(edge.id || this.uniqueId("e")),
        sourceNodeId: String(sourceNodeId),
        sourcePortId: String(sourcePortId),
        targetNodeId: String(targetNodeId),
        targetPortId: String(targetPortId),
      };
    },

    firstInputPort(node) {
      return (node?.ports || []).find((port) => port.direction === "input");
    },

    firstOutputPort(node) {
      return (node?.ports || []).find((port) => port.direction === "output");
    },

    defaultLabel(type) {
      const labels = {
        trigger: "Trigger",
        end: "End",
        condition: "Condition",
        action: "Send message",
        delay: "Wait",
      };

      return labels[type] || "New step";
    },

    uniqueId(prefix) {
      return `${prefix}_${Math.random().toString(36).slice(2, 8)}`;
    },

    stepByKind(kind) {
      return this.stepTypes.find((step) => step.kind === kind);
    },

    stepByType(type) {
      return this.stepTypes.find((step) => step.type === type);
    },
  }));
});
