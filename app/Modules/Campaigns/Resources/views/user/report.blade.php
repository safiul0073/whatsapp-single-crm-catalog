<x-layouts.user :title="__('Campaign Report')">
    @php
        $summary = $summary ?? [];
        $recipients = $recipients ?? $campaign->recipients()->with('contact')->paginate(20);
        $canReRun = in_array($campaign->status, [\App\Modules\Campaigns\Enums\CampaignStatus::Completed, \App\Modules\Campaigns\Enums\CampaignStatus::Failed], true);
        $statusCopy = match ($campaign->status) {
            \App\Modules\Campaigns\Enums\CampaignStatus::Draft => __('This campaign is still a draft. Send it to see report activity.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Scheduled => __('This campaign is scheduled and has not started yet.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Queued => __('This campaign is queued and waiting to start sending.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Sending => __('This campaign is currently sending to recipients.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Completed => __('This campaign completed successfully. You can re-run it to send it again with the same setup.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Paused => __('This campaign is paused and can be resumed from the campaigns list.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Cancelled => __('This campaign was cancelled and will not continue sending.'),
            \App\Modules\Campaigns\Enums\CampaignStatus::Failed => __('This campaign failed. You can re-run it after reviewing the issue.'),
        };
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.campaigns.index') }}" class="row-action" aria-label="Back to campaigns">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="heading-2">{{ $campaign->name }}</h2>
                    <span class="badge badge-{{ $campaign->status->badgeClass() }}">{{ ucfirst($campaign->status->value) }}</span>
                </div>
                <p class="m-text mt-1">{{ ucfirst($campaign->provider) }} &middot; {{ $campaign->channelAccount?->name ?? '-' }} &middot; {{ $campaign->scheduled_at?->toDateTimeString() ?? $campaign->created_at?->toDateTimeString() }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canReRun)
                <button
                    type="button"
                    class="btn-sm btn-primary"
                    data-modal-open="rerunCampaignConfirm"
                >
                    <i class="ph ph-arrow-clockwise text-base"></i>
                    {{ __('Re-run') }}
                </button>
            @else
                <button type="button" class="btn-sm btn-outline cursor-not-allowed opacity-60" disabled>
                    <i class="ph ph-arrow-clockwise text-base"></i>
                    {{ __('Re-run') }}
                </button>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-body">
        <span class="font-semibold text-title">{{ __('Status') }}:</span>
        {{ $statusCopy }}
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Recipients') }}</p>
                <span class="stat-card__icon"><i class="ph ph-users-three text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($summary['total_recipients'] ?? 0) }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Delivered') }}</p>
                <span class="stat-card__icon"><i class="ph ph-check-circle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($summary['delivered'] ?? 0) }}</p>
            <p class="mt-1 text-xs font-semibold text-success">{{ $summary['delivery_rate'] ?? 0 }}% {{ __('delivery rate') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Read') }}</p>
                <span class="stat-card__icon"><i class="ph ph-eye text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($summary['read'] ?? 0) }}</p>
            <p class="mt-1 text-xs font-semibold text-success">{{ $summary['read_rate'] ?? 0 }}% {{ __('read rate') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Replied') }}</p>
                <span class="stat-card__icon"><i class="ph ph-chat-circle-dots text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($summary['replied'] ?? 0) }}</p>
            <p class="mt-1 text-xs font-semibold text-success">{{ $summary['reply_rate'] ?? 0 }}% {{ __('reply rate') }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[24rem_minmax(0,1fr)]">
        <div class="rounded-2xl border border-neutral-200 bg-neutral-0 p-6">
            <p class="font-title text-base font-bold text-title">{{ __('Delivery funnel') }}</p>
            <div class="mt-5 space-y-4">
                @foreach ([
                    'sent' => 'Sent',
                    'delivered' => 'Delivered',
                    'opened' => 'Opened',
                    'read' => 'Read',
                    'clicked' => 'Clicked',
                    'replied' => 'Replied',
                    'failed' => 'Failed',
                ] as $key => $label)
                    @php
                        $count = $summary[$key] ?? 0;
                        $total = max(1, $summary['total_recipients'] ?? 1);
                        $pct = round(($count / $total) * 100, 1);
                    @endphp
                    <div>
                        <div class="f-between text-sm">
                            <span class="font-semibold text-title">{{ $label }}</span>
                            <span class="font-semibold text-neutral-500">{{ number_format($count) }} &middot; {{ $pct }}%</span>
                        </div>
                        <div class="funnel-track mt-1.5">
                            <span class="funnel-bar bg-primary" style="width: {{ $pct }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-neutral-0 p-6">
            <p class="font-title text-base font-bold text-title">{{ __('Skipped breakdown') }}</p>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="f-between">
                    <dt class="text-body">{{ __('Opt-outs') }}</dt>
                    <dd class="font-semibold text-title">{{ number_format($summary['skipped_opt_out'] ?? 0) }}</dd>
                </div>
                <div class="f-between">
                    <dt class="text-body">{{ __('Invalid') }}</dt>
                    <dd class="font-semibold text-title">{{ number_format($summary['skipped_invalid'] ?? 0) }}</dd>
                </div>
                <div class="f-between">
                    <dt class="text-body">{{ __('Policy') }}</dt>
                    <dd class="font-semibold text-title">{{ number_format($summary['skipped_policy'] ?? 0) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6">
        <h3 class="heading-4">{{ __('Recipient delivery') }}</h3>
        <div class="app-card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <div class="list-table" style="--list-cols: minmax(12rem, 1.8fr) minmax(9rem, 1.1fr) minmax(8rem, 1fr) minmax(8rem, 1fr) minmax(8rem, 1fr);">
                    <div class="list-table__head">
                        <span>{{ __('Contact') }}</span>
                        <span>{{ __('Address') }}</span>
                        <span>{{ __('Status') }}</span>
                        <span>{{ __('Sent at') }}</span>
                        <span>{{ __('Error') }}</span>
                    </div>
                    @forelse ($recipients as $recipient)
                        <div class="list-table__row">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-title">{{ $recipient->contact?->name ?? '-' }}</p>
                            </div>
                            <span class="text-xs">{{ $recipient->recipient_address ?? $recipient->to ?? '-' }}</span>
                            <span>
                                <span class="badge badge-{{ $recipient->status?->badgeClass() ?? 'neutral' }}">
                                    {{ ucfirst(str_replace('_', ' ', $recipient->status?->value ?? 'pending')) }}
                                </span>
                            </span>
                            <span class="text-xs">{{ $recipient->sent_at?->toDateTimeString() ?? '-' }}</span>
                            <span class="text-xs text-error">{{ $recipient->error_message ?? '-' }}</span>
                        </div>
                    @empty
                        <div class="list-table__row">
                            <div class="py-8 text-center text-sm text-neutral-400" style="grid-column: 1 / -1;">{{ __('No recipients yet.') }}</div>
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="px-6 pb-6">
                {{ $recipients->links() }}
            </div>
        </div>
    </div>

    @push('modals')
        <x-ui.confirm
            id="rerunCampaignConfirm"
            :title="__('Re-run campaign?')"
            :message="__('This will clear the previous run and send the campaign again using the same setup.')"
            :confirmText="__('Re-run')"
            :cancelText="__('Cancel')"
            formId="rerunCampaignForm"
        />
    @endpush

        <form id="rerunCampaignForm" method="POST" action="{{ route('user.campaigns.re-run', $campaign) }}" class="hidden">
        @csrf
    </form>
</x-layouts.user>
