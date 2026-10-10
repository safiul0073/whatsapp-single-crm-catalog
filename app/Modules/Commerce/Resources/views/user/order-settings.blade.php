<x-layouts.user :title="__('Order settings')">
    <div class="mx-auto w-full max-w-4xl space-y-4">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-md bg-primary/10 text-primary">
                        <i class="ph ph-gear text-xl"></i>
                    </span>
                    <h1 class="heading-3 text-title">{{ __('Store order settings') }}</h1>
                </div>
            </div>
            <x-ui.button variant="outline" href="{{ route('user.commerce.payment-services.index') }}">
                <i class="ph ph-credit-card"></i> {{ __('Payment services') }}
            </x-ui.button>
        </header>

        @if (session('success'))
            <div class="rounded-md border border-success/30 bg-success/10 p-4 text-sm text-success flex items-center justify-between" role="alert">
                <div class="flex items-center gap-2">
                    <i class="ph ph-check-circle text-lg"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('Please review the following errors:') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ route('user.commerce.orders.settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <section class="section-card space-y-4 rounded-md border border-neutral-200">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="heading-4 text-title">{{ __('Order defaults') }}</h2>
                    <p class="text-xs text-body">{{ __('New orders only. Review prices and shipping rates before changing.') }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="currency">{{ __('Store currency') }}</label>
                        <select class="form-input" id="currency" name="currency">
                            @foreach(['USD','BDT','EUR','GBP','CAD','AUD','JPY','KWD','BHD','OMR','CHF','SAR','AED','INR','SGD','NZD','CNY'] as $currency)
                                <option value="{{ $currency }}" @selected($settings->currency === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="reservation_hours">{{ __('Unpaid order hold (hours)') }}</label>
                        <input class="form-input" id="reservation_hours" name="reservation_hours" type="number" min="1" max="168" required value="{{ $settings->reservation_hours }}">
                    </div>
                </div>

                <div>
                    <label class="form-label" for="payment_instructions">{{ __('Payment note (optional)') }}</label>
                    <textarea class="form-input" id="payment_instructions" name="payment_instructions" rows="2" maxlength="4000" placeholder="{{ __('Shown to customers during payment confirmation.') }}">{{ $settings->payment_instructions }}</textarea>
                </div>
            </section>

            <section class="section-card space-y-4 rounded-md border border-neutral-200">
                <h2 class="heading-4 text-title">{{ __('WhatsApp notifications') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                    <div>
                        <input type="hidden" name="whatsapp_notifications" value="0">
                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5">
                            <input type="checkbox" name="whatsapp_notifications" value="1" @checked($settings->whatsapp_notifications) class="rounded border-neutral-300 text-primary focus:ring-primary">
                            <span class="text-sm font-medium text-title">{{ __('Send order updates to customers') }}</span>
                        </label>
                        <p class="text-xs text-body">{{ __('Eligible, opted-in customers only.') }}</p>
                    </div>

                    <div>
                        <label class="form-label" for="whatsapp_channel_id">{{ __('WhatsApp channel') }}</label>
                        <select id="whatsapp_channel_id" name="whatsapp_channel_id" class="form-input">
                            <option value="">{{ __('Use order channel') }}</option>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}" @selected($settings->whatsapp_channel_id == $channel->id)>{{ $channel->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="owner_whatsapp_number">{{ __('New-order alerts to owner') }}</label>
                        <input id="owner_whatsapp_number" type="text" name="owner_whatsapp_number" value="{{ old('owner_whatsapp_number', $settings->owner_whatsapp_number) }}" placeholder="+8801711223344" class="form-input" inputmode="tel" autocomplete="off">
                        @error('owner_whatsapp_number')<p class="text-xs text-error mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-body">{{ __('Enter an international number with +.') }}</p>
                    </div>

                    <div>
                        <label class="form-label" for="whatsapp_template_id">{{ __('Utility template (outside 24 hours)') }}</label>
                        <select id="whatsapp_template_id" name="whatsapp_template_id" class="form-input">
                            <option value="">{{ __('Do not send') }}</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected($settings->whatsapp_template_id == $template->id)>{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-body">{{ __('Choose an approved utility template.') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-4">
                    <x-forms.submit :label="__('Save settings')" />

                    @can('channels.manage')
                        <a href="{{ route('user.whatsapp-cloud.customer-login') }}" class="btn-sm btn-outline">
                            <i class="ph ph-whatsapp-logo"></i> {{ __('Customer login & OTP') }}
                        </a>
                    @endcan
                </div>
            </section>
        </form>
    </div>
</x-layouts.user>
