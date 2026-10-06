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
            <fieldset class="space-y-4 rounded border border-line p-4" x-data="{ methods: {{ Illuminate\Support\Js::from(old('payment_methods', $paymentMethods)) }}, addMethod() { this.methods.push({ id: 'method-' + Date.now(), name: '', recipient_details: '', instructions: '', active: '0', sort_order: this.methods.length, fields: [] }); } }">
                <legend class="heading-4">{{ __('Manual payment services') }}</legend>
                <p class="text-body">{{ __('Configure receiving details before enabling a service. Customers pay manually and submit screenshots for staff review.') }}</p>
                <input type="hidden" name="payment_methods" value="">
                <template x-for="(method, index) in methods" :key="method.id">
                    <div class="space-y-3 border border-line rounded p-4">
                        <input type="hidden" :name="`payment_methods[${index}][id]`" :value="method.id">
                        <label class="form-label">{{ __('Service name') }}<input class="form-input" :name="`payment_methods[${index}][name]`" x-model="method.name" maxlength="100" required></label>
                        <label class="form-label">{{ __('Recipient / receiving details') }}<textarea class="form-input" :name="`payment_methods[${index}][recipient_details]`" x-model="method.recipient_details" maxlength="2000" :required="Boolean(Number(method.active))" rows="3"></textarea></label>
                        <label class="form-label">{{ __('Payment instructions') }}<textarea class="form-input" :name="`payment_methods[${index}][instructions]`" x-model="method.instructions" maxlength="4000" rows="3"></textarea></label>
                        <label class="form-label">{{ __('Status') }}<select class="form-input" :name="`payment_methods[${index}][active]`" x-model="method.active"><option value="0">{{ __('Disabled') }}</option><option value="1">{{ __('Enabled') }}</option></select></label>
                        <label class="form-label">{{ __('Display order') }}<input type="number" class="form-input" :name="`payment_methods[${index}][sort_order]`" x-model="method.sort_order" min="0" max="999" required></label>
                        <p class="text-sm text-body">{{ __('Transaction ID and 1–5 payment screenshots are always required. Add any other details you need below.') }}</p>
                        <template x-for="(field, fieldIndex) in method.fields" :key="fieldIndex">
                            <div class="grid gap-2 sm:grid-cols-4">
                                <label class="form-label">{{ __('Field key') }}<input class="form-input" :name="`payment_methods[${index}][fields][${fieldIndex}][name]`" x-model="field.name" pattern="[a-z][a-z0-9_]*" maxlength="60" required></label>
                                <label class="form-label">{{ __('Label') }}<input class="form-input" :name="`payment_methods[${index}][fields][${fieldIndex}][label]`" x-model="field.label" maxlength="100" required></label>
                                <label class="form-label">{{ __('Required') }}<select class="form-input" :name="`payment_methods[${index}][fields][${fieldIndex}][required]`" x-model="field.required"><option value="0">{{ __('Optional') }}</option><option value="1">{{ __('Required') }}</option></select></label>
                                <button type="button" class="btn btn-outline" @click="method.fields.splice(fieldIndex, 1)">{{ __('Remove field') }}</button>
                            </div>
                        </template>
                        <div class="flex flex-wrap gap-2"><button type="button" class="btn btn-outline" @click="method.fields.push({name: '', label: '', required: '0'})" :disabled="method.fields.length >= 10">{{ __('Add field') }}</button><button type="button" class="btn btn-outline" @click="methods.splice(index, 1)">{{ __('Remove service') }}</button></div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline" @click="addMethod()" :disabled="methods.length >= 30">{{ __('Add payment service') }}</button>
            </fieldset>
            <x-forms.submit :label="__('Save settings')" />
        </form>
        @can('channels.manage')
            <a href="{{ route('user.whatsapp-cloud.customer-login') }}" class="btn btn-outline">{{ __('WhatsApp customer login and integration settings') }}</a>
        @endcan
    </div>
</x-layouts.user>
