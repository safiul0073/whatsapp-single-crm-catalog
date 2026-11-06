<x-layouts.user :title="__('My ads')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('My ads') }}</h2>
            <p class="m-text mt-1">{{ __('Ads you created here. New ads stay paused until you press Start, so nothing is spent by surprise.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('user.meta-ads.index') }}" class="btn-sm btn-outline"><i class="ph ph-chart-line-up text-base"></i> {{ __('Ad results') }}</a>
            @if ($context['can_manage'])
                <a href="{{ route('user.meta-ads.ads.create') }}" class="btn-sm btn-primary" data-meta-ads-create><i class="ph ph-plus text-base"></i> {{ __('Create ad') }}</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <x-ui.alert type="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="error" class="mt-4">{{ session('error') }}</x-ui.alert>
    @endif

    @include('meta-ads::user.partials.ads-access', ['context' => $context])

    <section class="section-card mt-6">
        @if ($context['ads']->isEmpty())
            <p class="text-sm text-body" data-meta-ads-none>{{ __('No ads yet. Promote a product, boost a Page post or run a creator\'s post as a partnership ad.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr>
                            <th>{{ __('Ad') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Daily budget') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Spend') }}</th>
                            <th>{{ __('Clicks') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($context['ads'] as $ad)
                            @php($result = $results[$ad->id] ?? null)
                            <tr data-meta-ad="{{ $ad->id }}">
                                <td>
                                    <p class="font-semibold text-heading">{{ $ad->name }}</p>
                                    @if ($ad->status === 'failed' && $ad->last_error)
                                        <p class="mt-1 text-xs text-error">{{ $ad->last_error }}</p>
                                    @endif
                                </td>
                                <td>{{ $ad->kindLabel() }}</td>
                                <td>{{ $ad->budgetLabel() }}</td>
                                <td>
                                    <span class="badge {{ $ad->statusBadge() }}">{{ $ad->statusLabel() }}</span>
                                </td>
                                <td>{{ $result ? $ad->currency.' '.$result['spend'] : '—' }}</td>
                                <td>{{ $result ? number_format($result['clicks']) : '—' }}</td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        @if ($ad->status === 'paused')
                                            <form method="POST" action="{{ route('user.meta-ads.ads.start', $ad) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="btn-sm btn-primary"
                                                    data-confirm
                                                    data-confirm-title="{{ __('Start this ad?') }}"
                                                    data-confirm-message="{{ __('Meta will start spending up to :amount per day from your ad account.', ['amount' => $ad->budgetLabel()]) }}"
                                                    data-confirm-button="{{ __('Start ad') }}"
                                                    data-confirm-icon="ph-play"
                                                >{{ __('Start') }}</button>
                                            </form>
                                        @elseif ($ad->status === 'active')
                                            <form method="POST" action="{{ route('user.meta-ads.ads.pause', $ad) }}">
                                                @csrf
                                                <button type="submit" class="btn-sm btn-outline">{{ __('Pause') }}</button>
                                            </form>
                                        @endif
                                        @if ($ad->status !== 'creating')
                                            <form method="POST" action="{{ route('user.meta-ads.ads.destroy', $ad) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                                                    data-confirm
                                                    data-confirm-title="{{ __('Delete this ad?') }}"
                                                    data-confirm-message="{{ __('The campaign is deleted in Meta too.') }}"
                                                    data-confirm-button="{{ __('Delete') }}"
                                                    data-confirm-variant="danger"
                                                    data-confirm-icon="ph-trash"
                                                >{{ __('Delete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.user>
