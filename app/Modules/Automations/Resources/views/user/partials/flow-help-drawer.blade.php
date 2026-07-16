<div class="drawer" id="automationFlowHelpDrawer">
  <div class="flex h-full flex-col bg-neutral-0" role="dialog" aria-modal="true" aria-labelledby="automationFlowHelpTitle">
    <div class="border-b border-neutral-100 p-5 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-[10px] font-bold tracking-[0.2em] text-primary uppercase">Automation guide</p>
          <h3 id="automationFlowHelpTitle" class="mt-1 font-title text-xl font-bold text-title">Build a flow in minutes</h3>
          <p class="mt-1 text-sm text-neutral-500">Connect a starting event to the steps WaPro should run automatically.</p>
        </div>
        <button type="button" class="row-action" data-drawer-close aria-label="Close flow help">
          <i class="ph ph-x text-base"></i>
        </button>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-5 sm:p-6">
      <div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-100 bg-section p-4">
        <div class="min-w-0 text-center">
          <span class="mx-auto grid h-9 w-9 place-items-center rounded-lg bg-success/10 text-success"><i class="ph ph-play"></i></span>
          <p class="mt-2 text-xs font-bold text-title">Start</p>
        </div>
        <i class="ph ph-arrow-right text-neutral-300"></i>
        <div class="min-w-0 text-center">
          <span class="mx-auto grid h-9 w-9 place-items-center rounded-lg bg-purple/10 text-purple"><i class="ph ph-git-branch"></i></span>
          <p class="mt-2 text-xs font-bold text-title">Decide</p>
        </div>
        <i class="ph ph-arrow-right text-neutral-300"></i>
        <div class="min-w-0 text-center">
          <span class="mx-auto grid h-9 w-9 place-items-center rounded-lg bg-info/10 text-info"><i class="ph ph-paper-plane-tilt"></i></span>
          <p class="mt-2 text-xs font-bold text-title">Act</p>
        </div>
      </div>

      <div class="mt-6">
        <h4 class="text-sm font-bold text-title">Create your first flow</h4>
        <ol class="mt-3 divide-y divide-neutral-100 border-y border-neutral-100">
          <li class="flex gap-3 py-4">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">1</span>
            <div><p class="text-sm font-semibold text-title">Choose a trigger</p><p class="mt-0.5 text-xs leading-5 text-neutral-500">Pick what starts the flow, such as a new message, contact, or tag.</p></div>
          </li>
          <li class="flex gap-3 py-4">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">2</span>
            <div><p class="text-sm font-semibold text-title">Add the next step</p><p class="mt-0.5 text-xs leading-5 text-neutral-500">Drag or click a message, condition, delay, action, or goal from the left panel.</p></div>
          </li>
          <li class="flex gap-3 py-4">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">3</span>
            <div><p class="text-sm font-semibold text-title">Connect the nodes</p><p class="mt-0.5 text-xs leading-5 text-neutral-500">Drag from the right dot of one node to the left dot of the next node.</p></div>
          </li>
          <li class="flex gap-3 py-4">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">4</span>
            <div><p class="text-sm font-semibold text-title">Edit the details</p><p class="mt-0.5 text-xs leading-5 text-neutral-500">Select a node and use Edit to configure its message, rule, delay, or action.</p></div>
          </li>
          <li class="flex gap-3 py-4">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">5</span>
            <div><p class="text-sm font-semibold text-title">Test and publish</p><p class="mt-0.5 text-xs leading-5 text-neutral-500">Run a test, review each step, save the draft, then publish when it is ready.</p></div>
          </li>
        </ol>
      </div>

      <div class="mt-6 rounded-lg border border-primary/20 bg-primary/5 p-4">
        <div class="flex gap-3">
          <i class="ph ph-lightbulb text-lg text-primary"></i>
          <div>
            <p class="text-sm font-semibold text-title">Keep the path easy to follow</p>
            <p class="mt-1 text-xs leading-5 text-neutral-500">Place nodes in execution order, connect every non-goal step, and use conditions only when the flow needs a decision.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="border-t border-neutral-100 p-5 sm:p-6">
      <a href="{{ route('user.automations.create') }}" class="btn-sm btn-primary w-full justify-center" data-drawer-close>
        <i class="ph ph-plus text-base"></i>
        Open flow builder
      </a>
    </div>
  </div>
</div>
