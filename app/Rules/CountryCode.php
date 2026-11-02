<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\PhoneNumberUtil;

/** An ISO 3166-1 alpha-2 country code. */
class CountryCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array(strtoupper($value), PhoneNumberUtil::getInstance()->getSupportedRegions(), true)) {
            $fail('Choose a valid country.');
        }
    }
}
