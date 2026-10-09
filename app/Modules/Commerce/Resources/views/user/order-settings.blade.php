<x-layouts.user :title="__('Order settings')">
    <div class="space-y-6 max-w-4xl">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-gear text-xl"></i>
                    </span>
                    <h1 class="heading-3 text-title">{{ __('Store order settings') }}</h1>
                </div>
                <p class="mt-1 text-sm text-body">{{ __('Configure store currency, unpaid reservation hours, WhatsApp notifications, and payment options.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('user.commerce.payment-services.index') }}">
                <i class="ph ph-credit-card"></i> {{ __('Payment Services') }}
            </x-ui.button>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-success flex items-center justify-between" role="alert">
                <div class="flex items-center gap-2">
                    <i class="ph ph-check-circle text-lg"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('Please review the following errors:') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Dedicated Payment Services Card -->
        <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-primary/15 text-primary">
                    <i class="ph ph-credit-card text-xl"></i>
                </span>
                <div>
                    <h2 class="heading-5 text-title">{{ __('Manual Payment Services') }}</h2>
                    <p class="text-xs text-body mt-0.5">{{ __('Remitly, Taptap Send, MoneyGram, and bank deposit accounts are managed on their own dedicated page with sample test data.') }}</p>
                </div>
            </div>
            <x-ui.button href="{{ route('user.commerce.payment-services.index') }}" variant="primary" class="shrink-0 rounded-md text-xs py-1.5">
                <i class="ph ph-arrow-square-out mr-1"></i>
                {{ __('Open Payment Services') }}
            </x-ui.button>
        </div>

        <div class="section-card space-y-5 rounded-lg border border-neutral-200">
            <h2 class="heading-4 text-title">{{ __('Currency & Order Rules') }}</h2>
            <p class="text-sm text-body">{{ __('Your selected currency applies to all new product quotes and orders. Existing orders keep their original prices and currency. Review prices and shipping rates before changing currency.') }}</p>

            <form method="POST" enctype="multipart/form-data" action="{{ route('user.commerce.orders.settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label class="form-label" for="currency">{{ __('Store currency') }}</label>
                    <select class="form-input" id="currency" name="currency">
                        @foreach(['USD','BDT','EUR','GBP','CAD','AUD','JPY','KWD','BHD','OMR','CHF','SAR','AED','INR','SGD','NZD','CNY'] as $currency)
                            <option value="{{ $currency }}" @selected($settings->currency === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label" for="reservation_hours">{{ __('Unpaid reservation hours') }}</label>
                    <input class="form-input" id="reservation_hours" name="reservation_hours" type="number" min="1" max="168" required value="{{ $settings->reservation_hours }}">
                    <p class="text-xs text-body mt-1">{{ __('How long reserved inventory is held before unpaid orders automatically expire.') }}</p>
                </div>

                <div>
                    <label class="form-label" for="payment_instructions">{{ __('General payment instructions & notes') }}</label>
                    <textarea class="form-input" id="payment_instructions" name="payment_instructions" rows="4" maxlength="4000" placeholder="{{ __('Optional notes shown to customers during payment confirmation...') }}">{{ $settings->payment_instructions }}</textarea>
                </div>

                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 space-y-4">
                    <h3 class="heading-5 text-title">{{ __('WhatsApp Notifications') }}</h3>

                    <input type="hidden" name="whatsapp_notifications" value="0">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="whatsapp_notifications" value="1" @checked($settings->whatsapp_notifications) class="rounded border-neutral-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-title">{{ __('Send eligible WhatsApp order status updates') }}</span>
                    </label>

                    <div>
                        <label class="form-label">{{ __('WhatsApp channel') }}</label>
                        <select name="whatsapp_channel_id" class="form-input">
                            <option value="">{{ __('Native order channel only') }}</option>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}" @selected($settings->whatsapp_channel_id == $channel->id)>{{ $channel->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="owner_whatsapp_number">{{ __('Owner WhatsApp number') }}</label>
                        <input id="owner_whatsapp_number" type="text" name="owner_whatsapp_number" value="{{ old('owner_whatsapp_number', $settings->owner_whatsapp_number) }}" placeholder="+8801711223344" class="form-input" inputmode="tel" autocomplete="off">
                        @error('owner_whatsapp_number')<p class="text-xs text-error mt-1">{{ $message }}</p>@enderror
                        <p class="text-xs text-body mt-1">{{ __('Get a WhatsApp message here every time a customer places an order. Use the international format with +. If this number has not messaged your business in the last 24 hours, the approved utility template below is used.') }}</p>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Approved utility template for expired service windows') }}</label>
                        <select name="whatsapp_template_id" class="form-input">
                            <option value="">{{ __('Do not send outside the service window') }}</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected($settings->whatsapp_template_id == $template->id)>{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-body mt-1">{{ __('Use a utility template with one body parameter and no header or buttons. Outside the service window, only subscribed contacts with a template approved for the selected channel are eligible.') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <x-forms.submit :label="__('Save order settings')" />

                    @can('channels.manage')
                        <a href="{{ route('user.whatsapp-cloud.customer-login') }}" class="btn btn-outline text-xs">
                            <i class="ph ph-whatsapp-logo"></i> {{ __('WhatsApp customer login & OTP settings') }}
                        </a>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</x-layouts.user>
