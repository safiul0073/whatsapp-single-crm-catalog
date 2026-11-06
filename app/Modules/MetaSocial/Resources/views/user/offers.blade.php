<x-layouts.user :title="__('Messenger offer subscribers')">
    <div class="min-w-0">
        <h2 class="heading-2">{{ __('Messenger offer subscribers') }}</h2>
        <p class="m-text mt-1">{{ __('Customers who tapped "Get messages" in a Messenger chat with your Page. Only they receive your offer broadcasts, and they can stop at any time.') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert type="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif
    @if (($errors ?? null)?->any())
        <x-ui.alert type="error" class="mt-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    @forelse ($pages as $page)
        @php($account = $page['account'])
        <section class="section-card mt-6" data-messenger-offers="{{ $account->id }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="heading-5">{{ $account->provider_display_id ?: $account->name }}</h3>
                    <p class="text-sm text-body">{{ trans_choice(':count subscribed customer|:count subscribed customers', $page['subscribers'], ['count' => $page['subscribers']]) }}</p>
                </div>
                @can('campaigns.view')
                    <a href="{{ route('user.campaigns.create', ['channel_account_id' => $account->id]) }}" class="btn-sm btn-primary"><i class="ph ph-paper-plane-tilt text-base"></i> {{ __('Send an offer') }}</a>
                @endcan
            </div>

            <form method="POST" action="{{ route('user.meta-social.setup.offers', $account) }}" class="mt-4 grid gap-4 rounded-md border border-neutral-200 p-4 md:grid-cols-[minmax(0,1fr)_auto]">
                @csrf
                @method('PUT')
                <div>
                    <label for="offers_title_{{ $account->id }}" class="form-label">{{ __('"Get messages" button title') }}</label>
                    <input id="offers_title_{{ $account->id }}" name="title" type="text" maxlength="65" class="form-input" value="{{ $page['settings']['title'] }}" required>
                    <div class="mt-3 flex items-center gap-2.5">
                        <input type="hidden" name="auto_request" value="0">
                        <input id="offers_auto_{{ $account->id }}" name="auto_request" type="checkbox" value="1" class="app-checkbox" @checked($page['settings']['auto_request'])>
                        <label for="offers_auto_{{ $account->id }}" class="text-sm text-body">{{ __('Ask new customers automatically after their first message') }}</label>
                    </div>
                    <p class="form-hint">{{ __('You can also ask any customer from their Messenger chat in the Inbox ("Ask to get offers"). Meta only allows asking within 24 hours of their last message.') }}</p>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-sm btn-outline">{{ __('Save') }}</button>
                </div>
            </form>

            @if ($page['optins']->isEmpty())
                <p class="mt-4 text-sm text-body">{{ __('No subscribers yet.') }}</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="py-2 pr-3">{{ __('Customer') }}</th>
                                <th class="py-2 pr-3">{{ __('Status') }}</th>
                                <th class="py-2 pr-3">{{ __('Subscribed') }}</th>
                                <th class="py-2 pr-3">{{ __('How often') }}</th>
                                <th class="py-2">{{ __('Expires') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($page['optins'] as $optin)
                                <tr class="border-t border-neutral-200">
                                    <td class="py-2 pr-3 text-heading">{{ $optin->contact?->name ?? $optin->psid }}</td>
                                    <td class="py-2 pr-3">
                                        @if ($optin->isUsable())
                                            <span class="badge badge-success">{{ __('Subscribed') }}</span>
                                        @elseif ($optin->status === 'pending')
                                            <span class="badge badge-warning">{{ __('Asked, not answered') }}</span>
                                        @elseif ($optin->status === 'stopped')
                                            <span class="badge badge-light">{{ __('Stopped') }}</span>
                                        @else
                                            <span class="badge badge-light">{{ __('Expired') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-body">{{ $optin->opted_in_at?->toFormattedDateString() ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-body">{{ $optin->frequency ? ucfirst(strtolower($optin->frequency)) : '—' }}</td>
                                    <td class="py-2 text-body">{{ $optin->expires_at?->toFormattedDateString() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <section class="section-card mt-6" data-messenger-offers-empty>
            <h3 class="heading-5">{{ __('Connect a Messenger Page first') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('Offer broadcasts are sent from your Facebook Page on Messenger.') }}</p>
            <a href="{{ route('user.meta-social.setup') }}" class="btn btn-primary mt-4">{{ __('Connect Messenger') }}</a>
        </section>
    @endforelse
</x-layouts.user>
