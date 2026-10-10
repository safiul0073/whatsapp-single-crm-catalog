<x-layouts.user :title="__('WhatsApp customer login')">
    <x-marketing-channels::setup-header provider="whatsapp" :description="__('Manage your WhatsApp channel and customer account verification.')" />
    @include('whatsapp-cloud::user.setup-tabs')
    <x-marketing-channels::status-banner />
    <div class="section-card max-w-3xl space-y-5">
        <section class="space-y-4">
            <h2 class="heading-4">{{ __('WhatsApp customer login') }}</h2>
            <p class="text-body">{{ __('Customers receive a verification code on WhatsApp and one welcome message after their first registration. This does not subscribe them to promotions.') }}</p>
            @if($errors->any())<div role="alert" class="text-danger">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('user.commerce.orders.settings.customer-auth') }}" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="enabled" value="0">
                <label class="flex gap-2"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $authSettings->enabled))>{{ __('Enable WhatsApp customer login') }}</label>
                <label class="form-label" for="auth_channel">{{ __('Login WhatsApp channel') }}</label>
                <select id="auth_channel" name="channel_id" class="form-input"><option value="">{{ __('Select a connected channel') }}</option>@foreach($channels as $channel)<option value="{{ $channel->id }}" @selected(old('channel_id', $authSettings->channel_id) == $channel->id)>{{ $channel->name }}</option>@endforeach</select>
                <label class="form-label" for="auth_template">{{ __('Authentication template') }}</label>
                <select id="auth_template" aria-describedby="auth_template_help" name="authentication_template_id" class="form-input"><option value="">{{ __('Select a copy-code authentication template') }}</option>@foreach($authTemplates as $template)<option value="{{ $template->id }}" @selected(old('authentication_template_id', $authSettings->authentication_template_id) == $template->id)>{{ $template->name }} · {{ $template->language }}</option>@endforeach</select>
                <p id="auth_template_help" class="text-sm text-body">{{ __('Verification code — :variable is automatically replaced with the customer’s login code. Example: 482913.', ['variable' => '{'.'{1}'.'}']) }}</p>
                <label class="form-label" for="welcome_template">{{ __('First-registration welcome template') }}</label>
                <select id="welcome_template" aria-describedby="welcome_template_help" name="welcome_template_id" class="form-input"><option value="">{{ __('Select an approved welcome template') }}</option>@foreach($welcomeTemplates as $template)<option value="{{ $template->id }}" @selected(old('welcome_template_id', $authSettings->welcome_template_id) == $template->id)>{{ $template->name }} · {{ $template->language }}</option>@endforeach</select>
                <p id="welcome_template_help" class="text-sm text-body">{{ __('Customer name — :variable is automatically replaced with the customer’s name. Example: Hello Test User.', ['variable' => '{'.'{1}'.'}']) }}</p>
                <p class="text-sm text-body">{{ __('Both templates must be approved for the selected WhatsApp business account. Welcome templates must contain no header or buttons, and either no variables or one customer-name variable in the body. Write a brief account greeting without promotions. Each template uses its configured language.') }}</p>
                <x-forms.submit :label="__('Save customer login settings')" />
            </form>
            @if($welcomeAttempts->isNotEmpty())
                <h3 class="font-semibold">{{ __('Recent welcome deliveries') }}</h3>
                <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="p-2 text-start">{{ __('Phone') }}</th><th class="p-2 text-start">{{ __('Status') }}</th><th class="p-2 text-start">{{ __('Attempts') }}</th></tr></thead><tbody>@foreach($welcomeAttempts as $attempt)<tr class="border-t border-border"><td class="p-2">{{ $attempt->phone }}</td><td class="p-2">{{ $attempt->delivery_status ?? $attempt->welcome_status }}</td><td class="p-2">{{ $attempt->welcome_attempts }}</td></tr>@endforeach</tbody></table></div>
                <p class="text-sm text-body">{{ __('Failed welcomes retry up to three attempts. Uncertain deliveries wait for WhatsApp delivery confirmation and are not automatically resent.') }}</p>
            @endif
        </section>
    </div>
</x-layouts.user>
