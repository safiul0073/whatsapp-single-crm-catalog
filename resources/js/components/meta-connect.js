'use strict';

// One-click Meta connect: a [data-meta-connect] button opens Facebook Login for Business with its
// configuration ID, then posts the returned code through the form named in data-meta-connect-form.
// WhatsApp Embedded Signup buttons also pass data-extras and read the WA_EMBEDDED_SIGNUP session
// message into the form's [data-embedded-field] inputs (WABA, phone number, business).
const SDK_ID = 'facebook-jssdk';
let sdkReady = null;

const loadSdk = (appId, version) => {
    if (sdkReady) {
        return sdkReady;
    }

    sdkReady = new Promise((resolve, reject) => {
        const init = () => {
            window.FB.init({ appId, autoLogAppEvents: true, xfbml: false, version });
            resolve(window.FB);
        };

        if (window.FB) {
            init();

            return;
        }

        window.fbAsyncInit = init;

        if (!document.getElementById(SDK_ID)) {
            const script = document.createElement('script');
            script.id = SDK_ID;
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.addEventListener('error', () => reject(new Error('sdk')));
            document.head.appendChild(script);
        }
    });

    return sdkReady;
};

const parseJson = (value, fallback) => {
    if (typeof value !== 'string' || value === '') {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
};

const readPath = (data, keys) => {
    for (const key of keys) {
        const value = key.split('.').reduce((carry, part) => carry && carry[part], data);

        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return '';
};

document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('[data-meta-connect]');

    if (triggers.length === 0) {
        return;
    }

    const first = triggers[0].dataset;
    loadSdk(first.appId, first.graphApiVersion).catch(() => {});

    // The flow currently running; only one Meta popup can be open at a time.
    let active = null;

    const setStatus = (flow, message, isError = false) => {
        const status = document.querySelector(`[data-meta-connect-status="${flow.trigger.dataset.metaConnect}"]`);

        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('text-error', isError);
        status.classList.toggle('text-body', !isError);
    };

    const field = (flow, name) => flow.form.querySelector(`[data-embedded-field="${name}"]`);

    const fill = (flow, name, value) => {
        const input = field(flow, name);

        if (input) {
            input.value = value;
        }
    };

    const submit = (flow) => {
        if (flow.submitted || !flow.code) {
            return;
        }

        flow.submitted = true;
        flow.form.querySelector('[name="code"]').value = flow.code;

        if (flow.session) {
            const data = flow.sessionData;
            fill(flow, 'waba_id', readPath(data, ['waba_id', 'whatsapp_business_account_id', 'data.waba_id', 'data.whatsapp_business_account_id']));
            fill(flow, 'phone_number_id', readPath(data, ['phone_number_id', 'data.phone_number_id']));
            fill(flow, 'business_id', readPath(data, ['business_id', 'data.business_id']));
            fill(flow, 'display_name', readPath(data, ['display_name', 'verified_name', 'data.display_name', 'data.verified_name']));
            fill(flow, 'signup_mode', flow.trigger.dataset.signupMode || '');
        }

        setStatus(flow, flow.trigger.dataset.finishingText || 'Finishing connection…');
        flow.form.submit();
    };

    window.addEventListener('message', (event) => {
        if (!active || !active.session || !event.origin.endsWith('facebook.com')) {
            return;
        }

        const payload = parseJson(event.data, event.data);

        if (payload?.type !== 'WA_EMBEDDED_SIGNUP') {
            return;
        }

        if (['FINISH', 'FINISH_ONLY_WABA', 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'].includes(payload.event)) {
            active.sessionData = payload.data || {};
            submit(active);
        }

        if (payload.event === 'CANCEL') {
            setStatus(active, active.trigger.dataset.cancelledText || 'Meta signup was cancelled. Nothing was saved.', true);
        }

        if (payload.event === 'ERROR') {
            setStatus(active, payload.data?.error_message || active.trigger.dataset.errorText || 'Meta returned an error. Please try again.', true);
        }
    });

    triggers.forEach((trigger) => {
        const form = document.getElementById(trigger.dataset.metaConnectForm || '');

        if (!form) {
            return;
        }

        trigger.addEventListener('click', () => {
            const flow = {
                trigger,
                form,
                code: null,
                submitted: false,
                session: trigger.dataset.metaConnectSession === 'whatsapp',
                sessionData: {},
            };
            active = flow;

            loadSdk(trigger.dataset.appId, trigger.dataset.graphApiVersion)
                .then((FB) => {
                    setStatus(flow, trigger.dataset.openingText || 'Opening Meta…');

                    const options = {
                        config_id: trigger.dataset.configId,
                        response_type: 'code',
                        override_default_response_type: true,
                    };
                    const extras = parseJson(trigger.dataset.extras, null);

                    if (extras) {
                        options.extras = extras;
                    }

                    const startedAt = Date.now();
                    const waitingTimer = window.setTimeout(() => {
                        if (!flow.code) {
                            setStatus(flow, trigger.dataset.waitingText || 'Waiting for you in the Meta window…');
                        }
                    }, 1500);

                    FB.login((response) => {
                        window.clearTimeout(waitingTimer);
                        const code = response.authResponse?.code || response.authResponse?.accessToken;

                        if (!code) {
                            // A blocked popup returns almost at once without the person doing anything.
                            const blocked = Date.now() - startedAt < 1200 && response.status !== 'connected';
                            setStatus(
                                flow,
                                blocked
                                    ? (trigger.dataset.popupBlockedText || 'Your browser blocked the Meta window. Allow pop-ups for this site and try again.')
                                    : (trigger.dataset.cancelledText || 'Meta login was not completed. Nothing was saved.'),
                                true
                            );

                            return;
                        }

                        flow.code = code;

                        // WhatsApp's session message can arrive just after the code; give it a moment.
                        if (flow.session) {
                            window.setTimeout(() => submit(flow), 800);
                        } else {
                            submit(flow);
                        }
                    }, options);
                })
                .catch(() => setStatus(flow, trigger.dataset.sdkErrorText || 'Could not load the Meta SDK. Check your connection or ad blocker.', true));
        });
    });
});
