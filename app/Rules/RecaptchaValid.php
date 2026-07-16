<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

class RecaptchaValid implements ValidationRule
{
    public bool $implicit = true;

    protected string $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! (bool) setting('plugin_turnstile_enabled', false)) {
            return;
        }

        $secret = trim((string) setting('plugin_turnstile_secret_key', ''));

        if ($secret === '') {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail(__('Please complete the reCAPTCHA challenge.'));

            return;
        }

        try {
            $response = Http::asForm()->post($this->verifyUrl, [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if ($response->json('success') !== true) {
                $fail(__('reCAPTCHA verification failed. Please try again.'));
            }
        } catch (Throwable) {
            $fail(__('Could not verify reCAPTCHA. Please try again.'));
        }
    }
}
