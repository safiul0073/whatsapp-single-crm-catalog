<x-layouts.user :title="$automation ? __('Edit Automation') : __('Create Automation')">
  <form
    method="POST"
    action="{{ $automation ? route('user.automations.update', $automation) : route('user.automations.store') }}"
    x-data="automationBuilder(@js($flow), @js([
      'generateUrl' => route('user.automations.generate'),
      'testUrl' => route('user.automations.test-flow'),
      'aiDraft' => $aiDraft ?? null,
      'chatbots' => $chatbots ?? [],
      'crm' => $crmBuilder ?? ['pipelines' => [], 'stages' => [], 'tags' => [], 'agents' => []],
    ]))"
    x-init="init()"
    @submit="prepareSubmit()"
  >
    @csrf
    @if($automation)
      @method('PUT')
    @endif

    <input type="hidden" name="nodes" :value="nodesPayload">
    <input type="hidden" name="edges" :value="edgesPayload">

    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-neutral-100 pb-4">
      <div class="flex min-w-0 items-center gap-3">
        <a href="{{ route('user.automations.index') }}" class="row-action" aria-label="Back to automations">
          <i class="ph ph-arrow-left text-lg"></i>
        </a>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-neutral-0">
          <i class="ph ph-share-network text-lg"></i>
        </span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold tracking-[0.28em] text-neutral-400 uppercase">Flow Builder</p>
          <input
            type="text"
            name="name"
            value="{{ old('name', $automation->name ?? ($aiDraft['name'] ?? 'Untitled automation')) }}"
            x-ref="nameInput"
            required
            aria-label="Automation name"
            class="w-full max-w-xs truncate rounded-lg border border-transparent bg-transparent px-1 font-title text-xl font-bold text-title hover:border-neutral-200 focus:border-primary focus:bg-neutral-0 focus:ring-2 focus:ring-primary/20 focus:outline-none"
          />
          <textarea
            name="description"
            x-ref="descriptionInput"
            rows="1"
            class="mt-1 w-full max-w-xl resize-none rounded-lg border border-transparent bg-transparent px-1 text-sm text-neutral-500 hover:border-neutral-200 focus:border-primary focus:bg-neutral-0 focus:ring-2 focus:ring-primary/20 focus:outline-none"
            placeholder="Short description"
          >{{ old('description', $automation->description ?? ($aiDraft['description'] ?? '')) }}</textarea>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="row-action" data-drawer-trigger="automationFlowHelpDrawer" aria-label="Flow builder help" title="Flow builder help">
          <i class="ph ph-question text-lg"></i>
        </button>
        @if($canUseAutomationAi)
          <button type="button" class="btn-sm btn-outline" data-modal-open="aiAutomationModal">
            <i class="ph ph-sparkle text-base"></i>
            Generate with AI
          </button>
        @else
          <a href="{{ route('user.subscription.show') }}" class="btn-sm btn-outline">
            <i class="ph ph-crown text-base"></i>
            AI builder is premium
          </a>
        @endif
        <button type="button" class="btn-sm btn-outline" @click="testFlow()" :disabled="testRunning || !hasNodes">
          <i class="ph ph-play text-base"></i>
          <span x-text="testRunning ? 'Testing...' : 'Test'"></span>
        </button>
        <button type="button" class="btn-sm btn-outline" x-show="hasNodes" x-cloak @click="clearFlow()">
          <i class="ph ph-eraser text-base"></i>
          Clear
        </button>
        <span class="badge {{ $automation?->is_active ? 'badge-success' : 'badge-warning' }}">
          {{ $automation?->is_active ? 'Active' : 'Draft' }}
        </span>
        <button type="submit" name="activate" value="0" class="btn-sm btn-outline">Save</button>
        <button type="submit" name="activate" value="1" class="btn-sm btn-primary">
          <i class="ph ph-check text-base"></i>
          Publish
        </button>
      </div>
    </div>

    @if ($errors->any())
      <div class="app-card mt-5 border-error/30 bg-error/5 p-4 text-sm text-error">
        <p class="font-semibold">Please fix the automation setup.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="app-card mt-5 border-primary/20 bg-primary/5 p-5" x-show="testResult || testError" x-cloak>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-[10px] font-bold tracking-[0.2em] text-primary uppercase">Test run</p>
          <h3 class="mt-1 font-title text-lg font-bold text-title" x-text="testResult?.message || 'Test could not run'"></h3>
          <p class="m-text mt-1" x-show="testResult">Sample message: <span class="font-semibold text-title" x-text="testMessage"></span></p>
          <p class="mt-1 text-sm text-error" x-show="testError" x-text="testError"></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <input type="text" class="form-input h-10 w-72 max-w-full text-sm" x-model="testMessage" placeholder="Sample inbound message">
          <button type="button" class="btn-sm btn-primary" @click="testFlow()" :disabled="testRunning">
            <i class="ph ph-play text-base"></i>
            Run again
          </button>
          <button type="button" class="row-action" @click="clearTestResult()" aria-label="Close test result">
            <i class="ph ph-x text-base"></i>
          </button>
        </div>
      </div>

      <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3" x-show="testResult?.steps?.length">
        <template x-for="(step, index) in (testResult?.steps || [])" :key="`${step.node_id}-${index}`">
          <div class="rounded-lg border border-neutral-100 bg-neutral-0 p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-bold text-neutral-400" x-text="`Step ${index + 1}`"></p>
                <h4 class="mt-1 truncate font-semibold text-title" x-text="step.label"></h4>
              </div>
              <span class="badge" :class="step.status === 'failed' ? 'badge-error' : (step.status === 'skipped' ? 'badge-warning' : 'badge-success')" x-text="step.status"></span>
            </div>
            <p class="m-text mt-3 text-sm" x-text="step.summary"></p>
            <p class="mt-2 text-xs font-semibold text-neutral-500">Port: <span class="text-title" x-text="step.port"></span></p>
            <pre class="mt-3 max-h-32 overflow-auto rounded-lg bg-section p-3 text-xs text-neutral-600" x-text="JSON.stringify(step.output || {}, null, 2)"></pre>
          </div>
        </template>
      </div>
    </div>

    @if($canUseAutomationAi)
      <div class="modal" id="aiAutomationModal" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="aiAutomationModalTitle">
          <div class="flex items-start justify-between gap-4 border-b border-neutral-100 p-5">
            <div class="min-w-0">
              <p class="text-[10px] font-bold tracking-[0.2em] text-primary uppercase">AI flow builder</p>
              <h3 id="aiAutomationModalTitle" class="mt-1 font-title text-xl font-bold text-title">Generate an automation flow</h3>
              <p class="mt-1 text-sm text-neutral-500">Describe the journey and AI will place a draft directly on this canvas.</p>
            </div>
            <button type="button" class="row-action" data-modal-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>

          <div class="space-y-4 p-5">
            <div class="rounded-lg border border-neutral-200 bg-section p-4">
              <p class="text-sm font-semibold text-title">A strong prompt includes:</p>
              <div class="mt-3 grid gap-2 text-sm text-neutral-600 sm:grid-cols-2">
                <span><i class="ph ph-check text-primary"></i> Start trigger or customer event</span>
                <span><i class="ph ph-check text-primary"></i> Messages, templates, or media</span>
                <span><i class="ph ph-check text-primary"></i> Questions, buttons, or branches</span>
                <span><i class="ph ph-check text-primary"></i> Delays, tags, handoff, or logging</span>
              </div>
            </div>

            <div>
              <label for="aiAutomationPrompt" class="form-label">Automation prompt</label>
              <textarea
                id="aiAutomationPrompt"
                x-ref="aiPrompt"
                x-model="aiPrompt"
                rows="5"
                class="form-input"
                placeholder="Example: When a new lead messages pricing, welcome them, ask their budget, show quick reply options, wait 1 day, then assign hot leads to sales."
              ></textarea>
            </div>

            <div class="rounded-lg border border-dashed border-neutral-200 bg-neutral-0 p-4">
              <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Example prompt</p>
              <p class="mt-2 text-sm text-neutral-600">When someone asks about pricing, send a welcome message, ask their budget, show quick replies for Starter, Growth, and Enterprise, wait 1 day, tag interested leads, and assign them to sales.</p>
            </div>

            <p class="text-sm text-error" x-show="aiError" x-text="aiError"></p>
            <p class="text-sm text-primary" x-show="aiNotice" x-text="aiNotice"></p>
            <div class="flex flex-wrap gap-2" x-show="aiSummary.length">
              <template x-for="item in aiSummary" :key="item">
                <span class="badge badge-soft"><i class="ph ph-check-circle text-xs"></i><span x-text="item"></span></span>
              </template>
            </div>
          </div>

          <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-100 p-5">
            <button type="button" class="btn-sm btn-outline" data-modal-close>Close</button>
            <button type="button" class="btn-sm btn-primary" @click="generateWithAi()" :disabled="aiGenerating">
              <i class="ph ph-sparkle text-base" :class="{ 'animate-pulse': aiGenerating }"></i>
              <span x-text="aiGenerating ? 'Generating...' : 'Generate flow'"></span>
            </button>
          </div>
        </div>
      </div>
    @endif

    <div
      class="flow-builder-shell mt-4 grid gap-0 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-0"
      :class="paletteCollapsed
        ? 'is-palette-collapsed lg:grid-cols-[1fr]'
        : 'is-palette-open lg:grid-cols-[16rem_1fr]'"
    >
      <aside class="flow-builder-palette max-h-[42rem] overflow-y-auto border-r border-neutral-200 bg-section/50 p-3" x-show="!paletteCollapsed" x-cloak>
        <div class="mb-3 flex items-center justify-between gap-3 px-1">
          <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">Add a node</p>
          <span class="text-xs text-neutral-400" x-text="nodeCount"></span>
        </div>
        <div class="relative mb-4">
          <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-neutral-400"></i>
          <input
            type="search"
            class="form-input input-search h-10 rounded-full pl-9 text-sm"
            placeholder="Search nodes..."
            x-model.debounce.150ms="nodeSearch"
          >
        </div>

        <template x-for="group in filteredGroups" :key="group.label">
          <div class="mb-5">
            <p
              class="mb-2 px-1 text-[10px] font-bold tracking-[0.18em] text-neutral-400 uppercase"
              :class="{ 'text-primary': group.label === 'Start' }"
              x-text="group.label"
            ></p>
            <div class="space-y-2">
              <template x-for="step in group.nodes" :key="step.kind">
                <button
                  type="button"
                  class="palette-item w-full rounded-lg bg-neutral-0"
                  :class="{ 'palette-item--start': step.kind === 'trigger' }"
                  draggable="true"
                  @dragstart="startPaletteDrag(step, $event)"
                  @click="addNode(step, canvasCenterPoint())"
                >
                  <span class="palette-item__icon"><i :class="step.icon"></i></span>
                  <span class="min-w-0 text-left">
                    <span class="block truncate text-sm font-semibold text-title" x-text="step.label"></span>
                    <span class="block truncate text-xs text-neutral-400" x-text="step.hint"></span>
                  </span>
                </button>
              </template>
            </div>
          </div>
        </template>

        <p class="form-hint rounded-lg border border-dashed border-neutral-200 bg-neutral-0 p-3">Tip: drag onto canvas, or click a node to add it near the center.</p>
      </aside>

      <button type="button" class="flow-palette-toggle" @click="togglePalette()" :aria-label="paletteCollapsed ? 'Show node palette' : 'Hide node palette'">
        <i class="ph" :class="paletteCollapsed ? 'ph-caret-right' : 'ph-caret-left'"></i>
      </button>

      <div class="flow-canvas-frame">
        <div
          x-ref="canvas"
          class="builder-canvas flow-builder-canvas rounded-none border-0"
          :class="{ 'is-dropping': isDropping, 'is-connecting': connectionDraft }"
          aria-label="Automation canvas"
          aria-describedby="automationCanvasZoomHelp"
          @pointerdown="canvasPointerDown($event)"
          @wheel="handleCanvasWheel($event)"
          @scroll="redrawCanvas()"
          @dragover.prevent="isDropping = true"
          @dragleave="isDropping = false"
          @drop.prevent="dropNode($event)"
        >
          <div x-ref="surface" class="builder-surface flow-builder-surface" :style="surfaceStyle()">
          <div class="flow-builder-zoom-layer" :style="zoomLayerStyle()">
            <template x-if="!hasNodes">
              <div class="flow-builder-empty">
                <span class="flow-builder-empty__icon">
                  <i class="ph ph-flow-arrow"></i>
                </span>
                <p class="flow-builder-empty__title">Start a new WhatsApp flow</p>
                <p class="flow-builder-empty__text">Add a trigger, message, condition, or any step from the left panel.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                  <button type="button" class="btn-sm btn-primary" @click="addStarterNode('trigger')">
                    <i class="ph ph-play text-base"></i>
                    Trigger
                  </button>
                  <button type="button" class="btn-sm btn-outline" @click="addStarterNode('send_whatsapp_message')">
                    <i class="ph ph-chat-circle text-base"></i>
                    Message
                  </button>
                  @if($canUseAutomationAi)
                    <button type="button" class="btn-sm btn-outline" data-modal-open="aiAutomationModal">
                      <i class="ph ph-sparkle text-base"></i>
                      AI
                    </button>
                  @else
                    <a href="{{ route('user.subscription.show') }}" class="btn-sm btn-outline">
                      <i class="ph ph-crown text-base"></i>
                      AI premium
                    </a>
                  @endif
                  <button type="button" class="btn-sm btn-outline" @click="addStarterNode('condition')">
                    <i class="ph ph-git-branch text-base"></i>
                    Condition
                  </button>
                </div>
              </div>
            </template>

            <svg class="pointer-events-none absolute inset-0 h-full w-full overflow-visible">
              <g x-html="edgesMarkup()"></g>
              <g x-html="draftEdgeMarkup()"></g>
              <defs>
                <marker id="automationArrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto" markerUnits="strokeWidth">
                  <path d="M0,0 L8,4 L0,8 Z" fill="currentColor" class="text-primary/55"></path>
                </marker>
              </defs>
            </svg>

            <template x-if="selectedNode">
              <div class="flow-node-toolbar" :style="toolbarStyle(selectedNode)">
                <button type="button" class="flow-node-toolbar__btn" @click="focusInspector(selectedNode.id)" aria-label="Edit node">
                  <i class="ph ph-pencil-simple"></i>
                </button>
                <button type="button" class="flow-node-toolbar__btn text-error" @click="deleteNode(selectedNode.id)" aria-label="Delete node">
                  <i class="ph ph-trash"></i>
                </button>
              </div>
            </template>

            <template x-for="node in nodes" :key="node.id">
              <div
                class="flow-node-card"
                :class="[nodeClass(node), selectedNodeId === node.id ? 'is-selected' : '', draggingNodeId === node.id ? 'is-dragging' : '']"
                :style="nodeStyle(node)"
                @mousedown.self.prevent="startNodeDrag(node.id, $event)"
                @click="selectNode(node.id)"
              >
                <template x-if="node.type === 'trigger'">
                  <span
                    class="flow-port flow-port--input flow-port--start"
                    title="Flow starts here"
                    aria-hidden="true"
                  ></span>
                </template>

                <template x-for="port in node.ports.filter((item) => item.direction === 'input')" :key="`${node.id}-${port.id}`">
                  <button
                    type="button"
                    class="flow-port flow-port--input"
                    data-flow-port="input"
                    :data-node-id="node.id"
                    :data-port-id="port.id"
                    :class="{ 'is-hot': inputPortIsHot(node.id, port.id), 'is-hovered': inputPortIsHovered(node.id, port.id) }"
                    :style="portStyle(port, 'left')"
                    @click.stop="clickPort(node.id, port.id, 'input')"
                    :title="port.label"
                  ></button>
                </template>

              <template x-for="port in node.ports.filter((item) => item.direction === 'output')" :key="`${node.id}-${port.id}`">
                <button
                  type="button"
                  class="flow-port flow-port--output"
                  :class="[
                    port.tone === 'success' ? 'flow-port--success' : '',
                    port.tone === 'error' ? 'flow-port--error' : '',
                    port.branch ? 'flow-port--branch' : '',
                    outputPortIsActive(node.id, port.id) ? 'is-active' : ''
                  ]"
                  :style="portStyle(port, 'right')"
                  @click.stop="selectNode(node.id)"
                  @pointerdown.stop="startConnectionDrag(node.id, port.id, $event)"
                  :title="port.label"
                ></button>
              </template>

              <div class="flow-node-card__head" @mousedown.prevent="startNodeDrag(node.id, $event)">
                <span class="flow-node-card__icon" :class="`tone-${nodeTone(node)}`">
                  <i :class="nodeIcon(node.type, node.kind)"></i>
                </span>
                <span class="min-w-0">
                  <span class="flow-node-card__title" x-text="node.label"></span>
                  <span class="flow-node-card__id" x-text="node.id"></span>
                </span>
                <span class="flow-node-card__badge" x-text="nodeBadge(node)"></span>
              </div>

              <template x-if="node.kind === 'quick_replies'">
                <div class="flow-node-card__body">
                  <div class="flow-node-card__prompt" x-text="node.data.prompt"></div>
                  <template x-for="(option, index) in node.data.options" :key="`${node.id}-option-${index}`">
                    <div class="flow-node-card__row">
                      <span class="flow-row-icon"><i class="ph ph-arrow-bend-up-right"></i></span>
                      <span x-text="option"></span>
                    </div>
                  </template>
                </div>
              </template>

              <template x-if="node.type === 'condition'">
                <div class="flow-node-card__body">
                  <div class="flow-node-card__prompt" x-text="node.data.expression"></div>
                </div>
              </template>

              <template x-if="node.kind !== 'quick_replies' && node.type !== 'condition'">
                <div class="flow-node-card__body">
                  <div class="flow-node-card__prompt" x-text="node.data.detail || nodeTypeLabel(node.type, node.kind)"></div>
                </div>
              </template>
              </div>
            </template>
          </div>

          <div class="absolute top-5 left-5 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-0 px-3 py-2 text-xs font-semibold text-neutral-500 shadow-sm">
            <span><span class="text-title" x-text="placedNodeCount"></span> steps</span>
            <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
            <span><span class="text-title" x-text="connectionCount"></span> connections</span>
          </div>
        </div>

        </div>

        <div class="flow-viewport-hud">
          <button
            type="button"
            class="flow-canvas-help"
            aria-label="Canvas zoom help"
            aria-describedby="automationCanvasZoomHelp"
          >
            <i class="ph ph-question text-sm"></i>
            <span class="flow-canvas-help__tip" aria-hidden="true">
              Zoom with Ctrl/Cmd + wheel, Ctrl/Cmd + +, Ctrl/Cmd + -, or reset with Ctrl/Cmd + 0.
            </span>
          </button>
          <p id="automationCanvasZoomHelp" class="sr-only">
            Zoom with Ctrl/Cmd plus mouse wheel, Ctrl/Cmd plus plus, Ctrl/Cmd plus minus, or reset with Ctrl/Cmd plus zero.
          </p>

          <div class="flow-minimap" x-show="hasNodes" x-cloak>
            <button type="button" class="flow-minimap__map" @click="jumpToMinimap($event)" aria-label="Jump around canvas">
              <template x-for="node in nodes" :key="`mini-${node.id}`">
                <span class="flow-minimap__node" :class="`tone-${nodeTone(node)}`" :style="minimapNodeStyle(node)"></span>
              </template>
              <span class="flow-minimap__viewport" :style="minimapViewportStyle()"></span>
            </button>
          </div>

          <div class="flow-canvas-controls">
            <div class="flow-canvas-controls__row">
              <div class="flow-control-group">
                <button type="button" class="flow-control-btn" @click="undoFlow()" :disabled="!canUndo" aria-label="Undo">
                  <i class="ph ph-arrow-u-up-left text-sm"></i>
                </button>
                <button type="button" class="flow-control-btn" @click="redoFlow()" :disabled="!canRedo" aria-label="Redo">
                  <i class="ph ph-arrow-u-up-right text-sm"></i>
                </button>
              </div>
              <div class="flow-control-group">
                <button type="button" class="flow-control-btn" @click="zoomIn()" aria-label="Zoom in">
                  <i class="ph ph-plus text-sm"></i>
                </button>
                <span class="flow-control-value" x-text="`${zoom}%`"></span>
                <button type="button" class="flow-control-btn" @click="zoomOut()" aria-label="Zoom out">
                  <i class="ph ph-minus text-sm"></i>
                </button>
                <button type="button" class="flow-control-btn border-l border-neutral-200" @click="fitToNodes()" aria-label="Fit flow">
                  <i class="ph ph-arrows-out-simple text-sm"></i>
                </button>
                <button type="button" class="flow-control-btn" @click="resetViewport()" aria-label="Reset view">
                  <i class="ph ph-arrow-clockwise text-sm"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <aside
        class="flow-inspector"
        :class="{ 'is-minimized': inspectorMinimized }"
        x-show="selectedNode && inspectorOpen"
        x-transition.opacity.duration.150ms
        x-cloak
      >
        <template x-if="selectedNode">
          <div>
            <button
              type="button"
              class="flow-inspector__minimized"
              x-show="inspectorMinimized"
              @click="expandInspector()"
              aria-label="Expand node settings"
            >
              <i :class="nodeIcon(selectedNode.type, selectedNode.kind)"></i>
              <span x-text="selectedNode.label"></span>
            </button>

            <div class="flow-inspector__content" x-show="!inspectorMinimized">
              <div class="flow-inspector__header">
              <span class="flow-node-card__icon" :class="`tone-${nodeTone(selectedNode)}`">
                <i :class="nodeIcon(selectedNode.type, selectedNode.kind)"></i>
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold tracking-[0.18em] text-neutral-400 uppercase" x-text="nodeGroupLabel(selectedNode)"></p>
                <h3 class="truncate font-title text-lg font-bold text-title" x-text="selectedNode.label"></h3>
              </div>
              <button type="button" class="row-action" @click="minimizeInspector()" aria-label="Minimize settings">
                <i class="ph ph-sidebar-simple text-base"></i>
              </button>
              <button type="button" class="row-action text-error" @click="deleteNode(selectedNode.id)" aria-label="Delete node">
                <i class="ph ph-trash text-base"></i>
              </button>
              <button type="button" class="row-action" @click="closeInspector()" aria-label="Close settings">
                <i class="ph ph-x text-base"></i>
              </button>
              </div>

            <div class="space-y-4 p-4">
              <p class="font-mono text-xs text-neutral-500">id <span x-text="selectedNode.id"></span></p>

              <template x-if="selectedNode.kind === 'quick_replies'">
                <div class="space-y-4">
                  <div class="flow-whatsapp-preview">
                    <p class="flow-whatsapp-preview__label">Preview · WhatsApp · <span x-text="selectedNode.data.options.length"></span> button(s)</p>
                    <div class="flow-whatsapp-preview__bubble">
                      <span x-text="selectedNode.data.prompt"></span>
                      <small>12:12 PM ✓✓</small>
                    </div>
                    <template x-for="option in selectedNode.data.options" :key="`preview-${option}`">
                      <div class="flow-whatsapp-preview__button">
                        <i class="ph ph-arrow-bend-up-right"></i>
                        <span x-text="option"></span>
                      </div>
                    </template>
                  </div>

                  <div>
                    <label class="form-label" for="cfgPrompt">Prompt</label>
                    <textarea id="cfgPrompt" rows="5" class="form-input" x-model="selectedNode.data.prompt"></textarea>
                  </div>

                  <div>
                    <div class="mb-2 flex items-center justify-between">
                      <label class="form-label mb-0">Options</label>
                      <button type="button" class="text-xs font-semibold text-primary" x-show="selectedNode.data.options.length < 3" @click="addQuickReplyOption(selectedNode)">+ Add button</button>
                    </div>
                    <div class="space-y-2">
                      <template x-for="(option, index) in selectedNode.data.options" :key="`edit-option-${index}`">
                        <div class="flex items-center gap-2">
                          <span class="w-4 text-xs text-neutral-500" x-text="`${index + 1}.`"></span>
                          <input type="text" class="form-input h-10" x-model="selectedNode.data.options[index]">
                          <button type="button" class="row-action text-error" @click="removeQuickReplyOption(selectedNode, index)" aria-label="Remove option">
                            <i class="ph ph-x text-base"></i>
                          </button>
                        </div>
                      </template>
                    </div>
                    <p class="form-hint mt-2">Max 3 buttons. Each gets its own output port.</p>
                  </div>
                </div>
              </template>

              <template x-if="selectedNode.type === 'condition'">
                <div class="space-y-4">
                  <div>
                    <label class="form-label" for="cfgExpression">Expression</label>
                    <input id="cfgExpression" type="text" class="form-input" x-model="selectedNode.data.expression" placeholder="@{{contact.tag}} = VIP">
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <label class="form-label" for="cfgField">Field</label>
                      <input id="cfgField" type="text" class="form-input" x-model="selectedNode.data.field" placeholder="message_body">
                    </div>
                    <div>
                      <label class="form-label" for="cfgOperator">Operator</label>
                      <select id="cfgOperator" class="form-input" x-model="selectedNode.data.operator">
                        <option value="contains">Contains</option>
                        <option value="equals">Equals</option>
                        <option value="not_equals">Not equals</option>
                        <option value="has_tag">Has tag</option>
                        <option value="inside_business_hours">Inside business hours</option>
                        <option value="not_replied_for">Not replied for minutes</option>
                        <option value="is_empty">Is empty</option>
                        <option value="is_not_empty">Is not empty</option>
                      </select>
                    </div>
                  </div>
                  <div>
                    <label class="form-label" for="cfgValue">Value</label>
                    <input id="cfgValue" type="text" class="form-input" x-model="selectedNode.data.value" placeholder="price">
                  </div>
                  <div>
                    <label class="form-label" for="cfgTrue">True label</label>
                    <input id="cfgTrue" type="text" class="form-input" x-model="selectedNode.data.trueLabel">
                  </div>
                  <div>
                    <label class="form-label" for="cfgFalse">False label</label>
                    <input id="cfgFalse" type="text" class="form-input" x-model="selectedNode.data.falseLabel">
                  </div>
                </div>
              </template>

              <template x-if="selectedNode.kind !== 'quick_replies' && selectedNode.type !== 'condition'">
                <div class="space-y-4">
                  <div>
                    <label class="form-label" for="cfgLabel">Label</label>
                    <input id="cfgLabel" type="text" class="form-input" x-model="selectedNode.label" placeholder="Describe this step">
                  </div>
                  <template x-if="selectedNode.type === 'trigger'">
                    <div class="space-y-4">
                      <div>
                        <label class="form-label" for="cfgTriggerKeyword">Keyword / match value</label>
                        <input id="cfgTriggerKeyword" type="text" class="form-input" x-model="selectedNode.data.keyword" placeholder="price">
                      </div>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'send_whatsapp_message'">
                    <div>
                      <label class="form-label" for="cfgBody">Message body</label>
                      <textarea id="cfgBody" rows="5" class="form-input" x-model="selectedNode.data.body" placeholder="Hi @{{first_name}}, thanks for your message."></textarea>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'send_approved_template'">
                    <div class="grid gap-3 sm:grid-cols-2">
                      <div>
                        <label class="form-label" for="cfgTemplateId">Template ID</label>
                        <input id="cfgTemplateId" type="number" class="form-input" x-model="selectedNode.data.template_id" placeholder="1">
                      </div>
                      <div>
                        <label class="form-label" for="cfgTemplateName">Template name</label>
                        <input id="cfgTemplateName" type="text" class="form-input" x-model="selectedNode.data.template_name" placeholder="hello_world">
                      </div>
                    </div>
                  </template>
                  <template x-if="['add_tag', 'add_contact_tag', 'remove_tag'].includes(selectedNode.kind)">
                    <div>
                      <label class="form-label" for="cfgTagId">Contact tag</label>
                      <select id="cfgTagId" class="form-input" x-model="selectedNode.data.tag_id">
                        <option value="">Select tag</option>
                        <template x-for="tag in crmTags" :key="tag.id"><option :value="tag.id" x-text="tag.name"></option></template>
                      </select>
                    </div>
                  </template>
                  <template x-if="['assign_agent', 'assign_conversation'].includes(selectedNode.kind)">
                    <div>
                      <label class="form-label" for="cfgAgentId">Workspace agent</label>
                      <select id="cfgAgentId" class="form-input" x-model="selectedNode.data.agent_id">
                        <option value="">Select agent</option>
                        <template x-for="agent in crmAgents" :key="agent.id"><option :value="agent.id" x-text="agent.name"></option></template>
                      </select>
                    </div>
                  </template>
                  <template x-if="['create_lead', 'update_lead_stage'].includes(selectedNode.kind)">
                    <div class="grid gap-3 sm:grid-cols-2">
                      <div><label class="form-label" for="cfgPipelineId">Pipeline</label><select id="cfgPipelineId" class="form-input" x-model="selectedNode.data.pipeline_id"><option value="">Default pipeline</option><template x-for="pipeline in crmPipelines" :key="pipeline.id"><option :value="pipeline.id" x-text="pipeline.name"></option></template></select></div>
                      <div><label class="form-label" for="cfgStageId">Stage</label><select id="cfgStageId" class="form-input" x-model="selectedNode.data.stage_id"><option value="">First stage</option><template x-for="stage in crmStagesFor(selectedNode.data.pipeline_id)" :key="stage.id"><option :value="stage.id" x-text="stage.pipeline + ' · ' + stage.name"></option></template></select></div>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'create_lead'">
                    <div class="grid gap-3 sm:grid-cols-3">
                      <div><label class="form-label" for="cfgLeadTitle">Lead title</label><input id="cfgLeadTitle" type="text" maxlength="255" class="form-input" x-model="selectedNode.data.title" placeholder="WhatsApp opportunity"></div>
                      <div><label class="form-label" for="cfgLeadValue">Value</label><input id="cfgLeadValue" type="number" min="0" step="0.01" class="form-input" x-model="selectedNode.data.value"></div>
                      <div><label class="form-label" for="cfgLeadAgent">Assignee</label><select id="cfgLeadAgent" class="form-input" x-model="selectedNode.data.assigned_to"><option value="">Keep current owner</option><template x-for="agent in crmAgents" :key="agent.id"><option :value="agent.id" x-text="agent.name"></option></template></select></div>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'create_task'">
                    <div class="space-y-3">
                      <div><label class="form-label" for="cfgTaskTitle">Task title</label><input id="cfgTaskTitle" type="text" class="form-input" x-model="selectedNode.data.title" placeholder="Follow up"></div>
                      <div class="grid gap-3 sm:grid-cols-3">
                        <div><label class="form-label" for="cfgTaskDue">Due in minutes</label><input id="cfgTaskDue" type="number" min="1" class="form-input" x-model="selectedNode.data.due_in_minutes"></div>
                        <div><label class="form-label" for="cfgTaskPriority">Priority</label><select id="cfgTaskPriority" class="form-input" x-model="selectedNode.data.priority"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option></select></div>
                        <div><label class="form-label" for="cfgTaskAgent">Assignee</label><select id="cfgTaskAgent" class="form-input" x-model="selectedNode.data.assigned_to"><option value="">Current owner</option><template x-for="agent in crmAgents" :key="agent.id"><option :value="agent.id" x-text="agent.name"></option></template></select></div>
                      </div>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'mark_lead_lost'">
                    <div><label class="form-label" for="cfgLostReason">Lost reason</label><textarea id="cfgLostReason" rows="3" class="form-input" x-model="selectedNode.data.lost_reason"></textarea></div>
                  </template>
                  <template x-if="selectedNode.kind === 'call_webhook'">
                    <div>
                      <label class="form-label" for="cfgWebhookUrl">Webhook URL</label>
                      <input id="cfgWebhookUrl" type="url" class="form-input" x-model="selectedNode.data.url" placeholder="https://example.com/webhook">
                    </div>
                  </template>
                  <template x-if="['generate_chatbot_reply', 'generate_ai_reply', 'ai_assistant'].includes(selectedNode.kind)">
                    <div class="space-y-4">
                      <div>
                        <label class="form-label" for="cfgChatbotId">Chatbot</label>
                        <select id="cfgChatbotId" class="form-input" x-model="selectedNode.data.chatbot_id">
                          <option value="">Use latest active chatbot</option>
                          <template x-for="chatbot in chatbots" :key="chatbot.id">
                            <option :value="chatbot.id" x-text="chatbot.name"></option>
                          </template>
                        </select>
                        <p class="form-hint mt-1">The selected chatbot answers with platform AI and its attached knowledge bases.</p>
                      </div>
                      <div>
                        <label class="form-label" for="cfgChatbotPrompt">Fallback prompt</label>
                        <textarea id="cfgChatbotPrompt" rows="3" class="form-input" x-model="selectedNode.data.prompt" placeholder="Used when the trigger does not include message text."></textarea>
                      </div>
                    </div>
                  </template>
                  <template x-if="selectedNode.kind === 'notify_admin'">
                    <div>
                      <label class="form-label" for="cfgNotification">Notification text</label>
                      <textarea id="cfgNotification" rows="4" class="form-input" x-model="selectedNode.data.message"></textarea>
                    </div>
                  </template>
                  <template x-if="selectedNode.type === 'delay'">
                    <div class="grid gap-3 sm:grid-cols-2">
                      <div>
                        <label class="form-label" for="cfgDelayValue">Wait value</label>
                        <input id="cfgDelayValue" type="number" min="1" class="form-input" x-model="selectedNode.data.value">
                      </div>
                      <div>
                        <label class="form-label" for="cfgDelayUnit">Unit</label>
                        <select id="cfgDelayUnit" class="form-input" x-model="selectedNode.data.unit">
                          <option value="minutes">Minutes</option>
                          <option value="hours">Hours</option>
                          <option value="days">Days</option>
                        </select>
                      </div>
                    </div>
                  </template>
                  <template x-if="selectedNode.type === 'end'">
                    <div>
                      <label class="form-label" for="cfgGoalReason">Goal reason</label>
                      <input id="cfgGoalReason" type="text" class="form-input" x-model="selectedNode.data.reason" placeholder="Customer became lead">
                    </div>
                  </template>
                  <div>
                    <label class="form-label" for="cfgDetail">Details</label>
                    <textarea id="cfgDetail" rows="5" class="form-input" x-model="selectedNode.data.detail"></textarea>
                  </div>
                </div>
              </template>

              <div class="rounded-lg border border-neutral-100 bg-section p-3 text-xs text-neutral-500" x-show="sourcePort">
                Drag from the selected output to another card input to connect it.
              </div>
            </div>
            </div>
          </div>
        </template>
      </aside>
    </div>
  </form>

  @push('drawers')
    @include('automations::user.partials.flow-help-drawer')
  @endpush
</x-layouts.user>
