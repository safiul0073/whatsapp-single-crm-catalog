{{-- WhatsApp Embedded Signup: the session message fills the hidden fields, then the form posts the code. --}}
<form id="whatsappEmbeddedSignupForm" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.embedded') }}" class="hidden">
    @csrf
    <input type="hidden" name="code" data-embedded-field="code">
    <input type="hidden" name="waba_id" data-embedded-field="waba_id">
    <input type="hidden" name="phone_number_id" data-embedded-field="phone_number_id">
    <input type="hidden" name="business_id" data-embedded-field="business_id">
    <input type="hidden" name="display_name" data-embedded-field="display_name">
    <input type="hidden" name="signup_mode" data-embedded-field="signup_mode">
</form>

@foreach ([
    'coexistence' => ['class' => 'btn-primary', 'icon' => 'ph-fill ph-whatsapp-logo', 'label' => __('Use my WhatsApp Business app number'), 'extras' => '{"setup":{},"featureType":"whatsapp_business_app_onboarding","sessionInfoVersion":"3"}', 'opening' => __('Opening Meta… You will get a code in your WhatsApp Business app to confirm the number.')],
    'new' => ['class' => 'btn-outline', 'icon' => 'ph ph-plus', 'label' => __('Register a new number'), 'extras' => '{"feature":"whatsapp_embedded_signup","sessionInfoVersion":"3","setup":{}}', 'opening' => __('Opening Meta… Have the phone ready to receive an SMS or call with a code.')],
] as $mode => $button)
    <button
        type="button"
        class="btn {{ $button['class'] }}"
        data-whatsapp-embedded-signup
        data-signup-mode="{{ $mode }}"
        data-meta-connect="whatsapp"
        data-meta-connect-form="whatsappEmbeddedSignupForm"
        data-meta-connect-session="whatsapp"
        data-app-id="{{ $signup['app_id'] }}"
        data-config-id="{{ $signup['config_id'] }}"
        data-graph-api-version="{{ $signup['graph_api_version'] }}"
        data-finishing-text="{{ __('Setting up your WhatsApp number: registering it, subscribing to messages and syncing templates…') }}"
        data-error-text="{{ __('Meta returned an error during WhatsApp signup. Please try again.') }}"
        @foreach (collect($loginText)->except("data-opening-text") as $attribute => $text) {{ $attribute }}="{{ $text }}" @endforeach
        data-opening-text="{{ $button['opening'] }}"
        data-extras="{{ $button['extras'] }}"
    >
        <i class="{{ $button['icon'] }} text-base"></i>
        {{ $button['label'] }}
    </button>
@endforeach
