@php
    $stageOptions = [
        'new' => __('New'),
        'contacted' => __('Contacted'),
        'qualified' => __('Qualified'),
        'converted' => __('Converted'),
        'won' => __('Won'),
        'lost' => __('Lost'),
    ];
    $sourceOptions = [
        'manual' => __('Manual'),
        'ai' => __('AI'),
        'google_places' => __('Google Places'),
        'website' => __('Website'),
        'campaign' => __('Campaign'),
        'referral' => __('Referral'),
    ];
    $verificationOptions = [
        'unverified' => __('Unverified'),
        'manual' => __('Manual'),
        'verified' => __('Verified'),
    ];
    $emptyStateColspan = auth()->user()?->can('leads.manage') ? 8 : 7;
    $selectedStages = collect($filters['stage'] ?? [])->filter()->values()->all();
    $selectedSources = collect($filters['source'] ?? [])->filter()->values()->all();
    $selectedVerificationStatuses = collect($filters['verification_status'] ?? [])->filter()->values()->all();
    $generationProviderLabel = match ($generationProvider['generated_by'] ?? null) {
        'platform_ai' => __('Using platform AI provider: :provider', ['provider' => $generationProvider['provider'] ?? __('Platform AI')]),
        default => __('AI query helper is not configured. Google Places search will still run when Place API is configured.'),
    };
    $placeApiConfigured = (bool) ($placeApiStatus['configured'] ?? false);
    $placeApiLabel = $placeApiConfigured
        ? __('Google Places API is configured.')
        : __('Admin has not configured Google Places API settings.');
    $hasAdvancedFilters = $selectedStages !== []
        || $selectedSources !== []
        || $selectedVerificationStatuses !== []
        || filled($filters['country'] ?? null)
        || filled($filters['category'] ?? null);
    $activeFilterCount = collect([
        filled($filters['search'] ?? null),
        $selectedStages !== [],
        $selectedSources !== [],
        $selectedVerificationStatuses !== [],
        filled($filters['country'] ?? null),
        filled($filters['category'] ?? null),
    ])->filter()->count();
@endphp

<x-layouts.user :title="__('Leads')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Leads') }}</h2>
            <p class="m-text mt-1">{{ __('Find real business leads from Google Places, review them, and convert them into campaign-ready contacts.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('leads.manage')
                <button type="button" class="btn-sm btn-primary" data-modal-open="generateLeads">
                    <i class="ph ph-sparkle text-base"></i>
                    {{ __('Find leads') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Draft leads') }}</p>
                <span class="stat-card__icon"><i class="ph ph-user-focus text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-2xl font-extrabold text-title">{{ number_format($stats['total'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Total in this workspace') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('New') }}</p>
                <span class="stat-card__icon"><i class="ph ph-sparkle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-2xl font-extrabold text-title">{{ number_format($stats['new'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Awaiting review') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Converted') }}</p>
                <span class="stat-card__icon"><i class="ph ph-address-book text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-2xl font-extrabold text-title">{{ number_format($stats['converted'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Linked to contacts') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Average score') }}</p>
                <span class="stat-card__icon"><i class="ph ph-chart-line-up text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-2xl font-extrabold text-title">{{ number_format($stats['average_score'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Fit estimate') }}</p>
        </div>
    </div>

    <div class="app-card mt-8 p-3 sm:p-4">
        <form method="GET" action="{{ route('user.leads.index') }}" x-data="{ advanced: @js($hasAdvancedFilters) }">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="min-w-0 flex-1">
                    <label for="leadSearch" class="sr-only">{{ __('Search leads') }}</label>
                    <div class="relative">
                        <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
                        <input id="leadSearch" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search leads') }}" class="form-input input-search">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn-sm btn-primary h-11 justify-center px-5">
                        <i class="ph ph-magnifying-glass text-base"></i>
                        {{ __('Search') }}
                    </button>
                    <button type="button" class="btn-sm btn-outline h-11 justify-center px-4" @click="advanced = !advanced" :aria-expanded="advanced.toString()" aria-controls="leadAdvancedFilters">
                        <i class="ph ph-sliders-horizontal text-base"></i>
                        {{ __('Advanced') }}
                        @if ($activeFilterCount > 0)
                            <span class="badge badge-primary">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    @if ($activeFilterCount > 0)
                        <a href="{{ route('user.leads.index') }}" class="btn-sm btn-outline h-11 justify-center px-4">
                            <i class="ph ph-x text-base"></i>
                            {{ __('Clear') }}
                        </a>
                    @endif
                </div>
            </div>

            <div id="leadAdvancedFilters" x-show="advanced" x-cloak class="mt-4 border-t border-neutral-100 pt-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[repeat(3,minmax(12rem,1fr))_minmax(8rem,0.7fr)_minmax(10rem,0.85fr)_auto] xl:items-end">
                    <div>
                        <label for="leadStageFilter" class="form-label">{{ __('Stage') }}</label>
                        <select id="leadStageFilter" name="stage[]" class="form-select ts-multi" multiple data-placeholder="{{ __('Stage') }}">
                            @foreach ($stageOptions as $value => $label)
                                <option value="{{ $value }}" @selected(in_array($value, $selectedStages, true))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="leadSourceFilter" class="form-label">{{ __('Source') }}</label>
                        <select id="leadSourceFilter" name="source[]" class="form-select ts-multi" multiple data-placeholder="{{ __('Source') }}">
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(in_array($value, $selectedSources, true))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="leadVerificationFilter" class="form-label">{{ __('Verification status') }}</label>
                        <select id="leadVerificationFilter" name="verification_status[]" class="form-select ts-multi" multiple data-placeholder="{{ __('Verification status') }}">
                            @foreach ($verificationOptions as $value => $label)
                                <option value="{{ $value }}" @selected(in_array($value, $selectedVerificationStatuses, true))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="leadCountryFilter" class="form-label">{{ __('Country') }}</label>
                        <input id="leadCountryFilter" type="text" name="country" value="{{ $filters['country'] ?? '' }}" maxlength="2" placeholder="{{ __('Country') }}" class="form-input uppercase">
                    </div>
                    <div>
                        <label for="leadCategoryFilter" class="form-label">{{ __('Category') }}</label>
                        <input id="leadCategoryFilter" type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="{{ __('Category') }}" class="form-input">
                    </div>
                    <button type="submit" class="btn-sm btn-outline h-12 justify-center px-5">
                        <i class="ph ph-funnel text-base"></i>
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    @can('leads.manage')
        <form id="bulkConvertForm" method="POST" action="{{ route('user.leads.bulk-convert') }}" class="mt-4">
            @csrf
            <div data-bulk-bar="leads" class="bulk-bar sticky top-4 z-20 shadow-sm">
                <div class="flex w-full flex-col gap-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-title">
                                <span data-selected-count="leads">0</span> {{ __('selected') }}
                            </p>
                            <p class="mt-0.5 text-xs text-body">{{ __('Convert selected leads into contacts, or remove them from the lead list.') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" id="bulkConvertSubmit" data-lead-bulk-action class="btn-sm btn-primary h-11 justify-center px-5 disabled:pointer-events-none disabled:opacity-50" disabled>
                                <i class="ph ph-address-book text-base"></i>
                                {{ __('Convert') }}
                            </button>
                            <button type="button" id="bulkDeleteSubmit" data-lead-bulk-action data-lead-bulk-delete-trigger data-modal-open="bulkDeleteLeadsConfirm" class="btn-sm btn-outline h-11 justify-center px-5 text-error hover:border-error hover:text-error disabled:pointer-events-none disabled:opacity-50" disabled>
                                <i class="ph ph-trash text-base"></i>
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <div>
                        <label for="bulkConvertGroups" class="form-label">{{ __('Add converted contacts to groups') }}</label>
                        <select id="bulkConvertGroups" name="group_ids[]" class="form-select ts-multi" multiple data-placeholder="{{ __('Select groups') }}">
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                        <div>
                        <label for="bulkConvertTags" class="form-label">{{ __('Apply tags') }}</label>
                        <select id="bulkConvertTags" name="tag_ids[]" class="form-select ts-multi" multiple data-placeholder="{{ __('Select tags') }}">
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    </div>
                </div>
            </div>
        </form>
    @endcan

    <form id="bulkDeleteLeadsForm" method="POST" action="{{ route('user.leads.bulk-delete') }}" class="hidden">
        @csrf
        <div data-bulk-delete-inputs="leads"></div>
    </form>

    <div class="app-card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            @if ($leads->count() > 0)
                <div class="list-table" style="--list-cols: 2.5rem minmax(13rem,1.6fr) minmax(9rem,0.95fr) minmax(12rem,1.15fr) minmax(8rem,0.85fr) minmax(8rem,0.85fr) minmax(8rem,0.9fr) minmax(7rem,0.8fr) 8.5rem;">
                    <div class="list-table__head">
                        @can('leads.manage')
                            <span>
                                <input type="checkbox" data-select-all="leads" class="app-checkbox" aria-label="{{ __('Select all leads') }}" form="bulkConvertForm">
                            </span>
                        @else
                            <span></span>
                        @endcan
                        <span>{{ __('Lead') }}</span>
                        <span>{{ __('Phone') }}</span>
                        <span>{{ __('Email') }}</span>
                        <span>{{ __('Location') }}</span>
                        <span>{{ __('Category') }}</span>
                        <span>{{ __('Stage') }}</span>
                        <span>{{ __('Status') }}</span>
                        <span class="text-right">{{ __('Actions') }}</span>
                    </div>

                    @foreach ($leads as $lead)
                        @php
                            $leadCanSend = ($lead->phone && (($connectedChannels['whatsapp'] ?? false) || ($connectedChannels['sms'] ?? false)))
                                || ($lead->email && ($connectedChannels['email'] ?? false))
                                || ($connectedChannels['telegram'] ?? false);
                            $leadSelectable = ! $lead->isConverted() && ($lead->phone || $lead->email);
                        @endphp
                        <div class="list-table__row">
                            @can('leads.manage')
                                <span>
                                    @if ($leadSelectable)
                                        <input form="bulkConvertForm" type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" data-select-item="leads" class="app-checkbox" aria-label="{{ __('Select :name', ['name' => $lead->name ?: $lead->company ?: __('lead')]) }}">
                                    @else
                                        <span class="text-neutral-300">-</span>
                                    @endif
                                </span>
                            @else
                                <span></span>
                            @endcan
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">
                                    {{ strtoupper(substr($lead->name ?: $lead->company ?: 'L', 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-title">{{ $lead->name ?: $lead->company ?: __('Unnamed lead') }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ $lead->company ?: __('No company') }}</p>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-body">
                                        @if ($lead->phone)
                                            <span class="badge badge-soft"><i class="ph ph-whatsapp-logo"></i> {{ __('Phone') }}</span>
                                        @endif
                                        @if ($lead->email)
                                            <span class="badge badge-soft"><i class="ph ph-envelope-simple"></i> {{ __('Email') }}</span>
                                        @endif
                                    </div>
                                    @if ($lead->notes)
                                        <p class="mt-2 line-clamp-2 text-xs text-body">{{ $lead->notes }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="truncate text-body">{{ $lead->phone ?: '-' }}</span>
                            <span class="truncate text-body">{{ $lead->email ?: '-' }}</span>
                            <div class="text-body">
                                <p class="font-medium text-title">{{ $lead->city ?: $lead->place ?: '-' }}</p>
                                <p class="text-xs text-neutral-400">{{ $lead->country ?: '-' }}</p>
                            </div>
                            <span>{{ $lead->category ?: '-' }}</span>
                            <span>
                                <span class="badge badge-neutral">{{ $stageOptions[$lead->stage] ?? Str::headline($lead->stage) }}</span>
                            </span>
                            <span>
                                @if ($lead->isConverted())
                                    <span class="badge badge-success">{{ __('Converted') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ Str::headline($lead->verification_status) }}</span>
                                @endif
                            </span>
                            <span class="flex justify-end gap-2">
                                @if ($lead->contact_id)
                                    <a href="{{ route('user.contacts.index') }}" class="row-action" aria-label="{{ __('View contact') }}" title="{{ __('View contact') }}">
                                        <i class="ph ph-address-book text-base"></i>
                                    </a>
                                @endif
                                @can('leads.manage')
                                    @if ($leadCanSend)
                                        <button type="button" class="row-action" data-modal-open="sendLead{{ $lead->id }}" aria-label="{{ __('Send message') }}" title="{{ __('Send message') }}">
                                            <i class="ph ph-paper-plane-tilt text-base"></i>
                                        </button>
                                    @endif
                                    <button type="button" class="row-action" data-modal-open="editLead{{ $lead->id }}" aria-label="{{ __('Edit lead') }}" title="{{ __('Edit') }}">
                                        <i class="ph ph-pencil-simple text-base"></i>
                                    </button>
                                    @if (! $lead->isConverted())
                                        <form method="POST" action="{{ route('user.leads.convert', $lead) }}">
                                            @csrf
                                            <button type="submit" class="row-action" aria-label="{{ __('Convert lead') }}" title="{{ __('Convert') }}">
                                                <i class="ph ph-user-plus text-base"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" class="row-action text-error hover:bg-error/10 hover:text-error" data-modal-open="deleteLeadConfirm{{ $lead->id }}" aria-label="{{ __('Delete lead') }}" title="{{ __('Delete') }}">
                                            <i class="ph ph-trash text-base"></i>
                                    </button>
                                @endcan
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-4 py-10 text-center text-sm text-neutral-400">
                    {{ __('No leads found.') }}
                </div>
            @endif
        </div>
        <x-tables.pagination :paginator="$leads" />
    </div>

    @push('modals')
        @can('leads.manage')
            <x-ui.confirm
                id="bulkDeleteLeadsConfirm"
                :title="__('Delete selected leads?')"
                :message="__('The selected leads will be permanently removed.')"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="bulkDeleteLeadsForm"
            />

            <div class="modal" id="generateLeads" data-modal>
                <div class="modal__backdrop" data-modal-close></div>
                <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="generateLeadsTitle">
                    <div class="flex items-center justify-between gap-3">
                        <h3 id="generateLeadsTitle" class="heading-4">{{ __('Find leads from Google Places') }}</h3>
                        <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                            <i class="ph ph-x text-base"></i>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('user.leads.generate') }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="space-y-2 rounded-lg border border-neutral-100 bg-section p-3 text-sm text-body">
                            <p>
                                <span class="font-semibold text-title">{{ __('Place source') }}:</span>
                                {{ $placeApiLabel }}
                            </p>
                            <p>
                                <span class="font-semibold text-title">{{ __('AI helper') }}:</span>
                                {{ $generationProviderLabel }}
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="generateCountry" class="form-label">{{ __('Country') }} <span class="text-error">*</span></label>
                                <input id="generateCountry" name="country" type="text" maxlength="2" required value="{{ old('country', 'US') }}" class="form-input uppercase">
                            </div>
                            <div>
                                <label for="generatePlace" class="form-label">{{ __('Place or city') }}</label>
                                <input id="generatePlace" name="place" type="text" value="{{ old('place') }}" placeholder="{{ __('New York') }}" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label for="generateCategory" class="form-label">{{ __('Category') }} <span class="text-error">*</span></label>
                            <input id="generateCategory" name="category" type="text" required value="{{ old('category') }}" placeholder="{{ __('Restaurants') }}" class="form-input">
                        </div>
                        <div>
                            <label for="generateAudience" class="form-label">{{ __('Audience') }}</label>
                            <textarea id="generateAudience" name="audience" rows="3" class="form-input" placeholder="{{ __('Owners, founders, or marketing managers') }}">{{ old('audience') }}</textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="generateChannel" class="form-label">{{ __('Preferred channel') }}</label>
                                <select id="generateChannel" name="channel" class="form-select ts-basic">
                                    @foreach (['any' => __('Any'), 'whatsapp' => __('WhatsApp'), 'email' => __('Email'), 'sms' => __('SMS'), 'telegram' => __('Telegram')] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('channel', 'any') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="generateCount" class="form-label">{{ __('Lead count') }}</label>
                                <input id="generateCount" name="count" type="number" min="1" max="25" value="{{ old('count', 10) }}" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label for="generateNotes" class="form-label">{{ __('Notes') }}</label>
                            <textarea id="generateNotes" name="notes" rows="2" class="form-input">{{ old('notes') }}</textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-sm btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                            <button type="submit" class="btn-sm btn-primary disabled:pointer-events-none disabled:opacity-50" @disabled(! $placeApiConfigured)>
                                <i class="ph ph-map-pin text-base"></i>
                                {{ __('Find leads') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>



            @foreach ($leads as $lead)
                @include('leads::user.partials.form-modal', [
                    'id' => 'editLead'.$lead->id,
                    'title' => __('Edit lead'),
                    'action' => route('user.leads.update', $lead),
                    'method' => 'PUT',
                            'lead' => $lead,
                            'stageOptions' => $stageOptions,
                            'sourceOptions' => $sourceOptions,
                        ])

                <x-ui.confirm
                    id="deleteLeadConfirm{{ $lead->id }}"
                    :title="__('Delete lead?')"
                    :message="__('This lead will be permanently removed.')"
                    :confirmText="__('Yes, Delete')"
                    :cancelText="__('Cancel')"
                    formId="deleteLeadForm{{ $lead->id }}"
                />
                <form id="deleteLeadForm{{ $lead->id }}" method="POST" action="{{ route('user.leads.destroy', $lead) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>

                @php
                    $leadCanSend = ($lead->phone && (($connectedChannels['whatsapp'] ?? false) || ($connectedChannels['sms'] ?? false)))
                        || ($lead->email && ($connectedChannels['email'] ?? false))
                        || ($connectedChannels['telegram'] ?? false);
                @endphp

                @if ($leadCanSend)
                    <div class="modal" id="sendLead{{ $lead->id }}" data-modal>
                        <div class="modal__backdrop" data-modal-close></div>
                        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="sendLead{{ $lead->id }}Title">
                            <div class="flex items-center justify-between gap-3">
                                <h3 id="sendLead{{ $lead->id }}Title" class="heading-4">{{ __('Send message') }}</h3>
                                <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                                    <i class="ph ph-x text-base"></i>
                                </button>
                            </div>
                            <form method="POST" action="{{ route('user.leads.send-message', $lead) }}" class="mt-4 space-y-4">
                                @csrf
                                <div class="rounded-lg border border-neutral-100 bg-section p-3">
                                    <p class="font-semibold text-title">{{ $lead->name ?: $lead->company ?: __('Unnamed lead') }}</p>
                                    <p class="mt-1 text-sm text-body">{{ collect([$lead->phone, $lead->email])->filter()->implode(' | ') }}</p>
                                </div>
                                <div>
                                    <label for="sendLead{{ $lead->id }}Channel" class="form-label">{{ __('Channel') }}</label>
                                    <select id="sendLead{{ $lead->id }}Channel" name="channel" class="form-select ts-basic">
                                        @if ($lead->phone && ($connectedChannels['whatsapp'] ?? false))
                                            <option value="whatsapp">{{ __('WhatsApp') }}</option>
                                        @endif
                                        @if ($lead->phone && ($connectedChannels['sms'] ?? false))
                                            <option value="sms">{{ __('SMS') }}</option>
                                        @endif
                                        @if ($lead->email && ($connectedChannels['email'] ?? false))
                                            <option value="email">{{ __('Email') }}</option>
                                        @endif
                                        @if ($connectedChannels['telegram'] ?? false)
                                            <option value="telegram">{{ __('Telegram invite') }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div>
                                    <label for="sendLead{{ $lead->id }}TelegramDelivery" class="form-label">{{ __('Telegram invite delivery') }}</label>
                                    <select id="sendLead{{ $lead->id }}TelegramDelivery" name="telegram_delivery_channel" class="form-select ts-basic">
                                        <option value="copy">{{ __('Create copyable invite') }}</option>
                                        @if ($lead->phone && ($connectedChannels['whatsapp'] ?? false))
                                            <option value="whatsapp">{{ __('Send invite by WhatsApp') }}</option>
                                        @endif
                                        @if ($lead->phone && ($connectedChannels['sms'] ?? false))
                                            <option value="sms">{{ __('Send invite by SMS') }}</option>
                                        @endif
                                        @if ($lead->email && ($connectedChannels['email'] ?? false))
                                            <option value="email">{{ __('Send invite by Email') }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div>
                                    <label for="sendLead{{ $lead->id }}Subject" class="form-label">{{ __('Email subject') }}</label>
                                    <input id="sendLead{{ $lead->id }}Subject" name="subject" type="text" class="form-input" value="{{ __('Quick follow up') }}">
                                </div>
                                <div>
                                    <label for="sendLead{{ $lead->id }}Body" class="form-label">{{ __('Message') }}</label>
                                    <textarea id="sendLead{{ $lead->id }}Body" name="body" rows="4" class="form-input" placeholder="{{ __('Write a short message...') }}"></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn-sm btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-sm btn-primary">
                                        <i class="ph ph-paper-plane-tilt text-base"></i>
                                        {{ __('Send') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        @endcan
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const bulkBar = document.querySelector('[data-bulk-bar="leads"]');
                const selectedCount = document.querySelector('[data-selected-count="leads"]');
                const bulkButtons = document.querySelectorAll('[data-lead-bulk-action]');

                const syncLeadBulkState = (count) => {
                    if (selectedCount) {
                        selectedCount.textContent = String(count);
                    }

                    if (bulkBar) {
                        bulkBar.classList.toggle('is-shown', count > 0);
                    }

                    bulkButtons.forEach((button) => {
                        button.disabled = count === 0;
                    });
                };

                syncLeadBulkState(0);

                document.addEventListener('bulk-selection:changed', (event) => {
                    if (event.detail.group !== 'leads') {
                        return;
                    }

                    syncLeadBulkState(event.detail.count ?? 0);
                });

                const bulkDeleteInputContainer = document.querySelector('[data-bulk-delete-inputs="leads"]');

                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('[data-lead-bulk-delete-trigger]');

                    if (!trigger || !bulkDeleteInputContainer) {
                        return;
                    }

                    const selectedIds = Array.from(document.querySelectorAll('input[data-select-item="leads"]:checked')).map((checkbox) => checkbox.value);

                    bulkDeleteInputContainer.replaceChildren(...selectedIds.map((leadId) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'lead_ids[]';
                        input.value = leadId;

                        return input;
                    }));
                }, true);
            });
        </script>
    @endpush
</x-layouts.user>
