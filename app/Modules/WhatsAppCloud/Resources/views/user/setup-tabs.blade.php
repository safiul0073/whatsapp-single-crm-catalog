<nav aria-label="{{ __('WhatsApp settings') }}" class="my-5 flex flex-wrap gap-2 border-b border-border pb-3">
    <a href="{{ route('user.whatsapp-cloud.channel-setup') }}" @if(request()->routeIs('user.whatsapp-cloud.channel-setup')) aria-current="page" @endif class="btn-sm {{ request()->routeIs('user.whatsapp-cloud.channel-setup') ? 'btn-primary' : 'btn-outline' }}">{{ __('Channel Setup') }}</a>
    @can('commerce.manage')
        <a href="{{ route('user.whatsapp-cloud.customer-login') }}" @if(request()->routeIs('user.whatsapp-cloud.customer-login')) aria-current="page" @endif class="btn-sm {{ request()->routeIs('user.whatsapp-cloud.customer-login') ? 'btn-primary' : 'btn-outline' }}">{{ __('Customer Login') }}</a>
    @endcan
</nav>
