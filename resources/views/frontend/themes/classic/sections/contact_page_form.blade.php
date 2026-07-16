@php
    $d = $section->data ?? [];
    $infoHeading = $d['info_heading'] ?? __('Contact information');
    $infoDescription =
        $d['info_description'] ??
        __('Talk to us about Cloud API setup, migration, campaign deliverability, or account support.');
    $email = $d['email'] ?? 'hello@wapro.test';
    $phone = $d['phone'] ?? '+1 (415) 555-1234';
    $phoneHrefSource = (string) ($d['phone_href'] ?? $phone);
    $phoneHrefSource = str_starts_with($phoneHrefSource, 'tel:') ? substr($phoneHrefSource, 4) : $phoneHrefSource;
    $phoneDigits = preg_replace('/\D+/', '', $phoneHrefSource) ?: '';
    $phoneHref = str_starts_with(trim($phoneHrefSource), '+') && $phoneDigits !== ''
        ? 'tel:+'.$phoneDigits
        : 'tel:'.$phoneDigits;
    $officeAddress = $d['office_address'] ?? __("340 Pine Street, Suite 800\nSan Francisco, CA 94104, USA");
    $businessHours = $d['business_hours'] ?? __("Mon – Fri, 9 am – 6 pm PST\nAsync replies on weekends");
    $formHeading = $d['form_heading'] ?? __('Start the conversation');
    $formSubheading = $d['form_subheading'] ?? __("We'll get back to you within one business day.");
@endphp
<section class="relative bg-white py-[clamp(56px,7vw,96px)]" aria-labelledby="contact-form-heading">
    <div class="section-container">
        <div class="grid gap-12 lg:grid-cols-[1fr_480px] xl:grid-cols-[1fr_540px] lg:gap-16 xl:gap-20 items-start">

            <!-- LEFT — Contact info -->
            <div>
                <h2 id="contact-form-heading"
                    class="font-display text-[clamp(22px,2.5vw,32px)] font-extrabold leading-heading tracking-heading text-brand-navy-ink">
                    {{ $infoHeading }}</h2>
                <p class="mt-2 font-body text-body-sm text-text-muted leading-relaxed-body max-w-[42ch]">
                    {{ $infoDescription }}</p>

                <!-- Info cards -->
                <div class="mt-8 flex flex-col gap-4">
                    <!-- Email -->
                    <a href="mailto:{{ $email }}"
                        class="group flex items-center gap-4 rounded-2xl border border-border-soft bg-bg-soft px-5 py-4 no-underline hover:border-border-strong hover:bg-white transition-colors duration-150">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-blue text-brand-blue inline-grid place-items-center flex-none transition-colors duration-150">
                            <i data-lucide="mail" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <p
                                class="font-display font-semibold text-text-strong text-[13px] tracking-body uppercase font-mono text-micro">
                                {{ __('Email') }}</p>
                            <p class="font-body text-body-sm font-medium text-brand-blue group-hover:underline mt-0.5">
                                {{ $email }}</p>
                        </div>
                        <i data-lucide="arrow-right"
                            class="w-4 h-4 text-text-light ml-auto opacity-0 group-hover:opacity-100 transition-opacity duration-150"></i>
                    </a>

                    <!-- Phone -->
                    <a href="{{ $phoneHref }}"
                        class="group flex items-center gap-4 rounded-2xl border border-border-soft bg-bg-soft px-5 py-4 no-underline hover:border-border-strong hover:bg-white transition-colors duration-150">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-green text-brand-green inline-grid place-items-center flex-none">
                            <i data-lucide="phone" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <p
                                class="font-display font-semibold text-text-strong text-[13px] tracking-body uppercase font-mono text-micro">
                                {{ __('Phone') }}</p>
                            <p class="font-body text-body-sm font-medium text-text-strong mt-0.5">{{ $phone }}
                            </p>
                        </div>
                        <i data-lucide="arrow-right"
                            class="w-4 h-4 text-text-light ml-auto opacity-0 group-hover:opacity-100 transition-opacity duration-150"></i>
                    </a>

                    <!-- Office -->
                    <div class="flex items-start gap-4 rounded-2xl border border-border-soft bg-bg-soft px-5 py-4">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-blue text-brand-blue inline-grid place-items-center flex-none mt-0.5">
                            <i data-lucide="map-pin" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <p
                                class="font-display font-semibold text-text-strong text-[13px] tracking-body uppercase font-mono text-micro">
                                {{ __('Office') }}</p>
                            <p class="font-body text-body-sm text-text-muted leading-relaxed-body mt-0.5">
                                {!! nl2br(e($officeAddress)) !!}</p>
                        </div>
                    </div>

                    <!-- Hours -->
                    <div class="flex items-start gap-4 rounded-2xl border border-border-soft bg-bg-soft px-5 py-4">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-blue text-brand-blue inline-grid place-items-center flex-none mt-0.5">
                            <i data-lucide="clock-4" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <p
                                class="font-display font-semibold text-text-strong text-[13px] tracking-body uppercase font-mono text-micro">
                                {{ __('Business hours') }}</p>
                            <p class="font-body text-body-sm text-text-muted leading-relaxed-body mt-0.5">
                                {!! nl2br(e($businessHours)) !!}</p>
                        </div>
                    </div>
                </div>

                <!-- Social links -->
                <div class="border-t border-border-soft mt-8 pt-6">
                    <p class="font-mono text-micro font-semibold uppercase tracking-[0.14em] text-text-light mb-4">
                        {{ __('Follow us') }}</p>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <a href="#" aria-label="{{ __('LinkedIn') }}"
                            class="w-9 h-9 rounded-xl border border-border-default bg-white inline-grid place-items-center text-text-muted hover:border-brand-blue hover:text-brand-blue hover:bg-tint-blue transition-colors duration-150">
                            <i data-lucide="linkedin" class="w-4 h-4"></i>
                        </a>
                        <a href="#" aria-label="{{ __('Twitter / X') }}"
                            class="w-9 h-9 rounded-xl border border-border-default bg-white inline-grid place-items-center text-text-muted hover:border-brand-blue hover:text-brand-blue hover:bg-tint-blue transition-colors duration-150">
                            <i data-lucide="twitter" class="w-4 h-4"></i>
                        </a>
                        <a href="#" aria-label="{{ __('GitHub') }}"
                            class="w-9 h-9 rounded-xl border border-border-default bg-white inline-grid place-items-center text-text-muted hover:border-brand-blue hover:text-brand-blue hover:bg-tint-blue transition-colors duration-150">
                            <i data-lucide="github" class="w-4 h-4"></i>
                        </a>
                        <a href="#" aria-label="{{ __('Dribbble') }}"
                            class="w-9 h-9 rounded-xl border border-border-default bg-white inline-grid place-items-center text-text-muted hover:border-brand-blue hover:text-brand-blue hover:bg-tint-blue transition-colors duration-150">
                            <i data-lucide="dribbble" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RIGHT — Form card -->
            <div class="rounded-3xl border border-border-soft bg-white shadow-xs p-7 md:p-9 lg:sticky lg:top-28">
                <!-- Eyebrow -->
                <div class="flex items-center gap-2 mb-5">
                    <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>
                    <span class="font-mono text-micro font-semibold uppercase tracking-[0.14em] text-brand-blue">{{ __('Send a message') }}</span>
                </div>

                <h3
                    class="font-display font-extrabold text-[clamp(18px,2vw,24px)] leading-heading tracking-heading text-brand-navy-ink mb-1">
                    {{ $formHeading }}</h3>
                <p class="font-body text-body-sm text-text-muted mb-6">{{ $formSubheading }}</p>

                <form id="contact-form" action="{{ route('contact.submit') }}" method="POST" novalidate
                    class="flex flex-col gap-4">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <!-- Name row -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label for="cf-first" class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('First name') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="cf-first" name="first_name" type="text" autocomplete="given-name" required
                                placeholder="{{ __('Jane') }}"
                                class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong placeholder:text-text-light focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150" />
                            <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please enter your first name.') }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="cf-last" class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('Last name') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="cf-last" name="last_name" type="text" autocomplete="family-name" required
                                placeholder="{{ __('Doe') }}"
                                class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong placeholder:text-text-light focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150" />
                            <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please enter your last name.') }}</p>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="flex flex-col gap-1.5">
                        <label for="cf-email" class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('Work email') }}
                            <span class="text-red-500" aria-hidden="true">*</span></label>
                        <input id="cf-email" name="email" type="email" autocomplete="email" required
                            placeholder="jane@company.com"
                            class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong placeholder:text-text-light focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150" />
                        <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please enter a valid work email address.') }}</p>
                    </div>

                    <!-- Company + Interest row -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label for="cf-company"
                                class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('Company') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="cf-company" name="company" type="text" autocomplete="organization" required
                                placeholder="Acme Inc."
                                class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong placeholder:text-text-light focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150" />
                            <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please enter your company name.') }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="cf-interest"
                                class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('Topic') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <select id="cf-interest" name="interest" required
                                class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150 appearance-none">
                                <option value="" disabled selected>{{ __('Select…') }}</option>
                                <option value="cloud-api-setup">{{ __('Cloud API setup') }}</option>
                                <option value="migration">{{ __('Migration from QR sender') }}</option>
                                <option value="campaigns">{{ __('Campaign deliverability') }}</option>
                                <option value="billing">{{ __('Billing or subscription') }}</option>
                                <option value="support">{{ __('Product support') }}</option>
                            </select>
                            <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please select a topic.') }}</p>
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="flex flex-col gap-1.5">
                        <label for="cf-message" class="font-body text-[12.5px] font-semibold text-text-strong">{{ __('Message') }}
                            <span class="text-red-500" aria-hidden="true">*</span></label>
                        <textarea id="cf-message" name="message" rows="4" required
                            placeholder="{{ __('Tell us what you need help with: Cloud API setup, templates, campaigns, automations, or support.') }}"
                            class="cf-field w-full rounded-xl border border-border-default bg-bg-soft px-3.5 py-2.5 font-body text-body-sm text-text-strong placeholder:text-text-light focus:outline-none focus:border-brand-blue focus:bg-white focus:ring-2 focus:ring-brand-blue/15 transition-colors duration-150 resize-none"></textarea>
                        <p class="cf-error hidden text-[11.5px] text-red-500 font-body">{{ __('Please enter your message (at least 10 characters).') }}</p>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="contact-submit-btn mt-1 w-full inline-flex items-center justify-center gap-2.5 rounded-xl bg-gradient-to-b from-brand-blue to-primary-hover px-6 py-3.5 text-sm font-bold text-white shadow-hero-cta border border-white/15">
                        {{ __('Send message') }} <i data-lucide="send" class="w-4 h-4"></i>
                    </button>

                    <!-- Success state (hidden) -->
                    <div id="cf-success"
                        class="hidden rounded-xl bg-tint-green border border-brand-green/20 px-4 py-3 items-center gap-3"
                        role="alert" aria-live="polite">
                        <i data-lucide="check-circle" class="w-5 h-5 text-brand-green flex-none"></i>
                        <p class="font-body text-body-sm font-semibold text-brand-green">{{ __("Message sent! We'll reply within one business day.") }}</p>
                    </div>

                    <p class="font-body text-[11.5px] text-text-light text-center leading-body">
                        <span class="text-red-500">*</span> {{ __('All fields are required. By submitting you agree to our') }}
                        <a href="#" class="underline text-text-muted hover:text-brand-blue transition-colors duration-150">{{ __('Privacy Policy') }}</a>. {{ __('No spam, ever.') }}
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('contact-form');
            const success = document.getElementById('cf-success');
            const submitBtn = form ? form.querySelector('.contact-submit-btn') : null;

            const inputClsValid = ['border-border-default'];
            const inputClsInvalid = ['border-red-400', 'bg-red-50/40', 'focus:border-red-400', 'focus:ring-red-400/15'];

            function setFieldState(field, valid) {
                const wrapper = field.closest('.flex.flex-col');
                const error = wrapper ? wrapper.querySelector('.cf-error') : null;
                if (valid) {
                    field.classList.remove(...inputClsInvalid);
                    field.classList.add(...inputClsValid);
                    error && error.classList.add('hidden');
                } else {
                    field.classList.add(...inputClsInvalid);
                    field.classList.remove(...inputClsValid);
                    error && error.classList.remove('hidden');
                }
            }

            function validateForm() {
                let valid = true;
                form.querySelectorAll('.cf-field').forEach(field => {
                    const ok = field.checkValidity();
                    setFieldState(field, ok);
                    if (!ok) valid = false;
                });
                return valid;
            }

            // Live validation: clear error as soon as the field becomes valid
            form && form.querySelectorAll('.cf-field').forEach(field => {
                field.addEventListener('input', () => { if (field.checkValidity()) setFieldState(field, true); });
                field.addEventListener('change', () => { if (field.checkValidity()) setFieldState(field, true); });
                field.addEventListener('blur', () => setFieldState(field, field.checkValidity()));
            });

            function resetSubmitBtn() {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                submitBtn.innerHTML = 'Send message <i data-lucide="send" class="w-4 h-4"></i>';
                if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [submitBtn] });
            }

            if (form && submitBtn) {
                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    if (!validateForm()) {
                        form.querySelector('.cf-field:invalid')?.focus();
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
                    submitBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Sending…';
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [submitBtn] });

                    const body = Object.fromEntries(new FormData(form));

                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': body._token ?? '',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(body),
                        });

                        const json = await res.json();

                        if (res.ok && json.success) {
                            form.reset();
                            form.querySelectorAll('.cf-field').forEach(f => setFieldState(f, true));
                            submitBtn.classList.add('hidden');
                            success.classList.remove('hidden');
                            success.classList.add('flex');
                            if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [success] });
                        } else if (res.status === 422 && json.errors) {
                            resetSubmitBtn();
                            Object.entries(json.errors).forEach(([field, messages]) => {
                                const input = form.querySelector(`[name="${field}"]`);
                                if (!input) { return; }
                                setFieldState(input, false);
                                const wrapper = input.closest('.flex.flex-col');
                                const errorEl = wrapper ? wrapper.querySelector('.cf-error') : null;
                                if (errorEl) { errorEl.textContent = messages[0]; }
                            });
                            form.querySelector('.cf-field[name]')?.focus();
                        } else {
                            resetSubmitBtn();
                        }
                    } catch {
                        resetSubmitBtn();
                    }
                });
            }
        });
    </script>
@endpush
