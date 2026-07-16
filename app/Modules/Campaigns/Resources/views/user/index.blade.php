<x-layouts.user :title="__('Campaigns')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Campaigns') }}</h2>
            <p class="m-text mt-1">{{ __('Send and track multi-channel broadcasts.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('user.campaigns.create') }}" class="btn-sm btn-primary">
                <i class="ph ph-plus text-base"></i>
                {{ __('New Campaign') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Total campaigns') }}</p>
                <span class="stat-card__icon"><i class="ph ph-megaphone-simple text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $campaigns->total() }}</p>
        </div>

        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Sent count') }}</p>
                <span class="stat-card__icon"><i class="ph ph-paper-plane-tilt text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($totalSent) }}</p>
        </div>

        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Delivered count') }}</p>
                <span class="stat-card__icon"><i class="ph ph-check-circle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($totalDelivered) }}</p>
        </div>

        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Failed count') }}</p>
                <span class="stat-card__icon"><i class="ph ph-x-circle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ number_format($totalFailed) }}</p>
        </div>
    </div>

    <div class="mt-6 app-card overflow-hidden">
        <div class="overflow-x-auto">
            <div class="list-table" style="--list-cols: 2.5rem minmax(14rem, 2fr) minmax(7rem, 0.9fr) minmax(9rem, 1.1fr) minmax(13rem, 1.6fr) minmax(10rem, 1.2fr) 10rem;">
                <div class="list-table__head">
                    <span><input type="checkbox" data-select-all class="app-checkbox" aria-label="Select all"></span>
                    <span>{{ __('Campaign') }}</span>
                    <span>{{ __('Status') }}</span>
                    <span>{{ __('Audience') }}</span>
                    <span>{{ __('Sent / Delivered / Read') }}</span>
                    <span>{{ __('Schedule') }}</span>
                    <span class="text-right">{{ __('Actions') }}</span>
                </div>

                @forelse ($campaigns as $campaign)
                    <div class="list-table__row">
                        <span><input type="checkbox" data-select-row class="app-checkbox" aria-label="Select {{ $campaign->name }}"></span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-title">{{ $campaign->name }}</p>
                            <p class="truncate text-xs text-neutral-400">{{ ucfirst($campaign->provider) }} &middot; {{ $campaign->channelAccount?->name ?? '-' }}</p>
                        </div>
                        <span><span class="badge badge-{{ $campaign->status->badgeClass() }}">{{ ucfirst($campaign->status->value) }}</span></span>
                        <span>{{ number_format($campaign->total_recipients) }} {{ __('recipients') }}</span>
                        <span class="flex items-center gap-2 text-xs font-semibold">
                            <span class="text-title">{{ number_format($campaign->sent_count) }}</span>
                            <span class="text-neutral-300">/</span>
                            <span class="text-success">{{ number_format($campaign->delivered_count) }}</span>
                            <span class="text-neutral-300">/</span>
                            <span class="text-info">{{ number_format($campaign->read_count) }}</span>
                        </span>
                        <span class="text-xs">{{ $campaign->scheduled_at?->toDateTimeString() ?? ($campaign->created_at?->toDateTimeString() ?? '-') }}</span>
                        <span class="flex justify-end gap-1">
                            <a href="{{ route('user.campaigns.report', $campaign) }}" class="row-action" aria-label="View report for {{ $campaign->name }}" title="{{ __('View report') }}">
                                <i class="ph ph-chart-line-up text-lg"></i>
                            </a>
                            <a href="{{ route('user.campaigns.edit', $campaign) }}" class="row-action" aria-label="Edit {{ $campaign->name }}" title="{{ __('Edit') }}">
                                <i class="ph ph-pencil-simple text-lg"></i>
                            </a>
                            <form method="POST" action="{{ route('user.campaigns.duplicate', $campaign) }}" class="block">
                                @csrf
                                <button type="submit" class="row-action" aria-label="Duplicate {{ $campaign->name }}" title="{{ __('Duplicate') }}">
                                    <i class="ph ph-copy text-lg"></i>
                                </button>
                            </form>
                            @if ($campaign->status === \App\Modules\Campaigns\Enums\CampaignStatus::Sending)
                                <form method="POST" action="{{ route('user.campaigns.pause', $campaign) }}" class="block">
                                    @csrf
                                    <button type="submit" class="row-action" aria-label="Pause {{ $campaign->name }}" title="{{ __('Pause') }}">
                                        <i class="ph ph-pause text-lg"></i>
                                    </button>
                                </form>
                            @endif
                            @if ($campaign->status === \App\Modules\Campaigns\Enums\CampaignStatus::Paused)
                                <form method="POST" action="{{ route('user.campaigns.resume', $campaign) }}" class="block">
                                    @csrf
                                    <button type="submit" class="row-action" aria-label="Resume {{ $campaign->name }}" title="{{ __('Resume') }}">
                                        <i class="ph ph-play text-lg"></i>
                                    </button>
                                </form>
                            @endif
                            @if (in_array($campaign->status, [\App\Modules\Campaigns\Enums\CampaignStatus::Completed, \App\Modules\Campaigns\Enums\CampaignStatus::Failed], true))
                                <form method="POST" action="{{ route('user.campaigns.re-run', $campaign) }}" class="block">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="row-action"
                                        aria-label="Re-run {{ $campaign->name }}"
                                        title="{{ __('Re-run') }}"
                                        data-modal-trigger="globalConfirmModal"
                                        data-confirm-action="{{ route('user.campaigns.re-run', $campaign) }}"
                                        data-confirm-method="POST"
                                        data-confirm-title="{{ __('Re-run campaign?') }}"
                                        data-confirm-message="{{ __('This will clear the previous run and send the campaign again using the same setup.') }}"
                                        data-confirm-button="{{ __('Re-run') }}"
                                    >
                                        <i class="ph ph-arrow-clockwise text-lg"></i>
                                    </button>
                                </form>
                            @endif
                            @if (! in_array($campaign->status, [\App\Modules\Campaigns\Enums\CampaignStatus::Completed, \App\Modules\Campaigns\Enums\CampaignStatus::Cancelled, \App\Modules\Campaigns\Enums\CampaignStatus::Failed], true))
                                <form method="POST" action="{{ route('user.campaigns.cancel', $campaign) }}" class="block">
                                    @csrf
                                    <button type="submit" class="row-action text-error hover:bg-error/10 hover:text-error" aria-label="Cancel {{ $campaign->name }}" title="{{ __('Cancel') }}">
                                        <i class="ph ph-x-circle text-lg"></i>
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('user.campaigns.destroy', $campaign) }}" class="block">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="row-action text-error hover:bg-error/10 hover:text-error"
                                    aria-label="Delete {{ $campaign->name }}"
                                    title="{{ __('Delete') }}"
                                    data-modal-trigger="globalConfirmModal"
                                    data-confirm-action="{{ route('user.campaigns.destroy', $campaign) }}"
                                    data-confirm-method="DELETE"
                                    data-confirm-title="{{ __('Delete campaign?') }}"
                                    data-confirm-message="{{ __('This campaign and its recipient history will be removed.') }}"
                                    data-confirm-button="{{ __('Delete') }}"
                                >
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </form>
                        </span>
                    </div>
                @empty
                    <div class="list-table__row">
                        <span colspan="7" class="py-8 text-center text-sm text-neutral-400">{{ __('No campaigns yet.') }}</span>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-4 px-6 pb-6">
            {{ $campaigns->links() }}
        </div>
    </div>
</x-layouts.user>
