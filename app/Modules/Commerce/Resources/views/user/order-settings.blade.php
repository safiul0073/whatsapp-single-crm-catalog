<x-layouts.user :title="__('Order settings')">
    <div class="section-card space-y-5 max-w-3xl">
        <h1 class="heading-3">{{ __('Store order settings') }}</h1>
        <p class="text-body">{{ __('Your selected currency applies to all new product quotes and orders. Existing orders keep their original prices and currency. Review prices and shipping rates before changing currency.') }}</p>
        <form method="POST" action="{{ route('user.commerce.orders.settings.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <label class="form-label" for="currency">{{ __('Store currency') }}</label>
            <select class="form-input" id="currency" name="currency">@foreach(['USD','BDT','EUR','GBP','CAD','AUD','JPY','KWD','BHD','OMR','CHF','SAR','AED','INR','SGD','NZD','CNY'] as $currency)<option value="{{ $currency }}" @selected($settings->currency === $currency)>{{ $currency }}</option>@endforeach</select>
            <label class="form-label" for="reservation_hours">{{ __('Unpaid reservation hours') }}</label>
            <input class="form-input" id="reservation_hours" name="reservation_hours" type="number" min="1" max="168" required value="{{ $settings->reservation_hours }}">
            <label class="form-label" for="payment_instructions">{{ __('Payment instructions') }}</label>
            <textarea class="form-input" id="payment_instructions" name="payment_instructions" rows="5" maxlength="4000">{{ $settings->payment_instructions }}</textarea>
            <input type="hidden" name="whatsapp_notifications" value="0">
            <label class="flex gap-2"><input type="checkbox" name="whatsapp_notifications" value="1" @checked($settings->whatsapp_notifications)>{{ __('Send eligible WhatsApp order updates') }}</label>
            <label class="form-label">{{ __('WhatsApp channel') }}<select name="whatsapp_channel_id" class="form-input"><option value="">{{ __('Native order channel only') }}</option>@foreach($channels as $channel)<option value="{{ $channel->id }}" @selected($settings->whatsapp_channel_id == $channel->id)>{{ $channel->name }}</option>@endforeach</select></label>
            <label class="form-label">{{ __('Approved utility template for expired service windows') }}<select name="whatsapp_template_id" class="form-input"><option value="">{{ __('Do not send outside the service window') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected($settings->whatsapp_template_id == $template->id)>{{ $template->name }}</option>@endforeach</select></label>
            <p class="text-sm text-body">{{ __('Use a utility template with one body parameter and no header or buttons. Outside the service window, only subscribed contacts with a template approved for the selected channel are eligible.') }}</p>
            <x-forms.submit :label="__('Save settings')" />
        </form>
        @can('channels.manage')
            <a href="{{ route('user.whatsapp-cloud.customer-login') }}" class="btn btn-outline">{{ __('WhatsApp customer login and integration settings') }}</a>
        @endcan
    </div>
</x-layouts.user>
