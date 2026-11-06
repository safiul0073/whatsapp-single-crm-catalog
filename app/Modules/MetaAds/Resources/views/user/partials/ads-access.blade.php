@if (! $context['connected'])
    <section class="section-card mt-6" data-meta-ads-access="connect">
        <h3 class="heading-5">{{ __('Connect your Meta Business to create ads') }}</h3>
        <p class="mt-1 text-sm text-body">{{ __('Ads run from your own Meta ad account and Facebook Page.') }}</p>
        <a href="{{ route('user.channels.index') }}" class="btn btn-primary mt-4">{{ __('Go to Channels') }}</a>
    </section>
@elseif (! $context['can_manage'])
    <section class="section-card mt-6" data-meta-ads-access="reconnect">
        <h3 class="heading-5">{{ __('Allow ad management') }}</h3>
        <p class="mt-1 text-sm text-body">{{ __('Your Meta connection can read ad results but not create ads. Reconnect Meta and allow "Manage ads" for your ad account and Page.') }}</p>
        <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-primary mt-4">{{ __('Reconnect Meta') }}</a>
    </section>
@elseif ($context['ad_accounts'] === [])
    <section class="section-card mt-6" data-meta-ads-access="accounts">
        <h3 class="heading-5">{{ __('No ad accounts selected') }}</h3>
        <p class="mt-1 text-sm text-body">{{ __('Choose the ad account to pay for ads from your Meta Business assets.') }}</p>
        <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-primary mt-4">{{ __('Manage assets') }}</a>
    </section>
@endif
