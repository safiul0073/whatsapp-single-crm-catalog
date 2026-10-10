<x-layouts.user :title="__('CRM')">
    @php
        $pipelineLeadCount = $pipeline->leads()->count();
        $boardLeadCount = collect($leads)->sum(fn ($stageLeads) => $stageLeads->count());
        $canManageLeads = auth()->user()->can('crm.manage');
        $hasMovableLeads = $canManageLeads && collect($leads)->flatten()->contains(fn ($lead) => $lead->status->value === 'open');
    @endphp
    <div
        class="space-y-5"
        x-data="crmBoard(@js(['moveUrl' => route('user.crm.leads.stage', ['lead' => '__LEAD__']), 'emptyLabel' => $hasMovableLeads ? __('Drop leads here') : null, 'movedLabel' => __('Lead moved to :stage.'), 'savingLabel' => __('Saving stage change...'), 'hasLeads' => $hasMovableLeads, 'canManage' => $canManageLeads]))"
    >
        <header class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="heading-3">{{ __('CRM Pipeline') }}</h1>
                <p class="m-text mt-1">{{ __('Move WhatsApp opportunities forward and keep every follow-up visible.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('crm.manage')
                    <button type="button" class="btn-sm btn-outline" data-modal-open="managePipeline">
                        <i class="ph ph-sliders-horizontal"></i>{{ __('Manage pipeline') }}
                    </button>
                    <button type="button" class="btn-sm btn-primary" data-modal-open="createPipeline">
                        <i class="ph ph-plus-circle"></i>{{ __('New pipeline') }}
                    </button>
                @endcan
            </div>
        </header>

        <form method="GET" action="{{ route('user.crm.index') }}" class="section-card grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_10rem_12rem_12rem_auto]">
            <input type="search" name="q" class="form-input" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search leads or contacts') }}">
            <select name="pipeline" class="form-input">
                @foreach ($pipelines as $pipelineOption)
                    <option value="{{ $pipelineOption->id }}" @selected($pipelineOption->id === $pipeline->id)>{{ $pipelineOption->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-input">
                @foreach (['open' => __('Open'), 'won' => __('Won'), 'lost' => __('Lost'), 'all' => __('All')] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? 'open') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="source" class="form-input">
                <option value="">{{ __('All sources') }}</option>
                @foreach (['manual', 'whatsapp', 'campaign', 'automation'] as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ str($source)->headline() }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="form-input">
                <option value="">{{ __('All agents') }}</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $agent->id)>{{ $agent->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-sm btn-outline justify-center">{{ __('Filter') }}</button>
        </form>

        <p class="rounded-md border border-error/30 bg-error/10 px-4 py-3 text-sm text-error" role="alert" x-show="error" x-cloak x-text="error"></p>
        <p class="rounded-md border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-body" role="status" aria-live="polite" x-show="isSaving || message" x-cloak x-text="isSaving ? savingLabel : message"></p>

        @if ($pipelineLeadCount === 0)
            <section class="flex flex-col gap-3 border border-neutral-200 bg-neutral-0 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="emptyPipelineTitle">
                <div>
                    <h2 id="emptyPipelineTitle" class="text-sm font-semibold text-title">{{ __('This pipeline is ready for leads') }}</h2>
                    <p class="mt-1 text-sm text-body">{{ __('Create a lead from a conversation in Inbox, or configure an automation to add leads here.') }}</p>
                </div>
                @canany(['inbox.view', 'inbox.assigned_only'])
                    <a href="{{ route('user.inbox.index') }}" class="btn-sm btn-outline justify-center">
                        <i class="ph ph-chat-circle-text"></i>{{ __('Open Inbox') }}
                    </a>
                @endcanany
            </section>
        @elseif ($boardLeadCount === 0)
            <p class="border border-neutral-200 bg-neutral-0 px-4 py-3 text-sm text-body" role="status">{{ __('No leads match the current filters.') }}</p>
        @endif

        <div class="crm-board" aria-label="{{ __('CRM lead board') }}">
            @foreach ($pipeline->stages as $stage)
                <section
                    class="pipeline-col min-h-72"
                    data-stage-id="{{ $stage->id }}"
                    @dragover.prevent="dragOverStage($event.currentTarget)"
                    @dragleave="leaveStage($event.currentTarget, $event)"
                    @drop.prevent="dropLead({{ $stage->id }}, $event)"
                >
                    <header class="pipeline-col__head">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                            <h2 class="pipeline-col__title">{{ $stage->name }}</h2>
                        </div>
                        <span class="pipeline-col__count bg-primary/10 text-primary">{{ ($leads[$stage->id] ?? collect())->count() }}</span>
                    </header>
                    <div class="pipeline-col__body min-h-52" data-stage-cards>
                        @forelse ($leads[$stage->id] ?? [] as $lead)
                            <article
                                @class(['lead-card', 'cursor-grab active:cursor-grabbing' => $canManageLeads && $lead->status->value === 'open'])
                                draggable="{{ $canManageLeads && $lead->status->value === 'open' ? 'true' : 'false' }}"
                                data-lead-id="{{ $lead->id }}"
                                @if ($canManageLeads && $lead->status->value === 'open')
                                    @dragstart="startDrag({{ $lead->id }}, {{ $stage->id }}, $event)"
                                    @dragend="finishDrag()"
                                @endif
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate font-semibold text-title">{{ $lead->title }}</h3>
                                        <p class="mt-1 truncate text-sm text-body">{{ $lead->contact?->name ?: $lead->contact?->phone }}</p>
                                    </div>
                                    <span class="badge badge-soft shrink-0">{{ str($lead->source->value)->headline() }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($lead->contact?->tags ?? [] as $tag)
                                        <span class="badge badge-soft">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                                <div class="lead-card__foot">
                                    <span class="text-xs text-body">{{ $lead->assignee?->name ?? __('Unassigned') }}</span>
                                    @if ($lead->next_follow_up_at)
                                        <span class="text-xs {{ $lead->next_follow_up_at->isPast() ? 'text-error' : 'text-body' }}">{{ $lead->next_follow_up_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            @if ($hasMovableLeads)
                                <p class="rounded-md border border-dashed border-neutral-300 p-4 text-center text-xs text-body" data-empty-stage>{{ __('Drop leads here') }}</p>
                            @elseif ($boardLeadCount > 0)
                                <p class="px-2 py-3 text-center text-xs text-body">{{ __('No leads in this stage.') }}</p>
                            @endif
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        @can('crm.manage')
            <x-ui.modal id="createPipeline" :title="__('Create pipeline')">
                <form method="POST" action="{{ route('user.crm.pipelines.store') }}" class="space-y-4">
                    @csrf
                    <div><label for="pipelineName" class="form-label">{{ __('Name') }}</label><input id="pipelineName" name="name" class="form-input" required maxlength="255"></div>
                    <label class="flex items-center gap-2 text-sm text-title"><input type="checkbox" name="is_default" value="1" class="app-checkbox">{{ __('Make this the default pipeline') }}</label>
                    <button type="submit" class="btn btn-primary w-full">{{ __('Create pipeline') }}</button>
                </form>
            </x-ui.modal>

            <x-ui.modal id="managePipeline" :title="__('Manage pipeline')" size="lg">
                <div class="space-y-5">
                    <form method="POST" action="{{ route('user.crm.pipelines.update', $pipeline) }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                        @csrf
                        @method('PUT')
                        <div><label for="editPipelineName" class="form-label">{{ __('Pipeline name') }}</label><input id="editPipelineName" name="name" value="{{ $pipeline->name }}" class="form-input" required maxlength="255"></div>
                        <label class="flex h-11 items-center gap-2 text-sm text-title"><input type="checkbox" name="is_default" value="1" class="app-checkbox" @checked($pipeline->is_default)>{{ __('Default') }}</label>
                        <button type="submit" class="btn-sm btn-outline h-11 justify-center">{{ __('Save') }}</button>
                    </form>

                    @unless ($pipeline->is_default)
                        <form method="POST" action="{{ route('user.crm.pipelines.destroy', $pipeline) }}" class="flex justify-end">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm btn-error">{{ __('Delete pipeline') }}</button>
                        </form>
                    @endunless

                    <div class="space-y-3">
                        @foreach ($pipeline->stages as $stage)
                            <div class="rounded-md border border-neutral-200 p-3">
                                <form method="POST" action="{{ route('user.crm.stages.update', $stage) }}" class="grid gap-3 sm:grid-cols-[1fr_7rem_5rem_auto] sm:items-end">
                                    @csrf
                                    @method('PUT')
                                    <div><label class="form-label" for="stageName{{ $stage->id }}">{{ __('Stage') }}</label><input id="stageName{{ $stage->id }}" name="name" value="{{ $stage->name }}" class="form-input" required></div>
                                    <div><label class="form-label" for="stageColor{{ $stage->id }}">{{ __('Color') }}</label><input id="stageColor{{ $stage->id }}" name="color" type="color" value="{{ $stage->color ?? '#1FAA53' }}" class="form-input h-11 p-1"></div>
                                    <div><label class="form-label" for="stagePosition{{ $stage->id }}">{{ __('Order') }}</label><input id="stagePosition{{ $stage->id }}" name="position" type="number" min="0" value="{{ $stage->position }}" class="form-input"></div>
                                    <button type="submit" class="btn-sm btn-outline h-11 justify-center">{{ __('Save') }}</button>
                                </form>
                                @if ($pipeline->stages->count() > 1)
                                    <form method="POST" action="{{ route('user.crm.stages.destroy', $stage) }}" class="mt-3 grid gap-3 border-t border-neutral-200 pt-3 sm:grid-cols-[1fr_auto] sm:items-end">
                                        @csrf
                                        @method('DELETE')
                                        <div>
                                            <label for="replacementStage{{ $stage->id }}" class="form-label">{{ __('Move existing leads to') }}</label>
                                            <select id="replacementStage{{ $stage->id }}" name="replacement_stage_id" class="form-input">
                                                <option value="">{{ __('No replacement needed') }}</option>
                                                @foreach ($pipeline->stages->where('id', '!=', $stage->id) as $replacementStage)
                                                    <option value="{{ $replacementStage->id }}">{{ $replacementStage->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn-sm btn-error h-11 justify-center">{{ __('Delete stage') }}</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('user.crm.stages.store', $pipeline) }}" class="grid gap-3 border-t border-neutral-200 pt-4 sm:grid-cols-[1fr_7rem_auto] sm:items-end">
                        @csrf
                        <div><label for="newStageName" class="form-label">{{ __('New stage') }}</label><input id="newStageName" name="name" class="form-input" required maxlength="255"></div>
                        <div><label for="newStageColor" class="form-label">{{ __('Color') }}</label><input id="newStageColor" name="color" type="color" value="#1FAA53" class="form-input h-11 p-1"></div>
                        <button type="submit" class="btn-sm btn-primary h-11 justify-center">{{ __('Add stage') }}</button>
                    </form>
                </div>
            </x-ui.modal>
        @endcan
    </div>
</x-layouts.user>
