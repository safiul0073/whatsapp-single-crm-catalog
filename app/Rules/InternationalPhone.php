<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** An international phone number that libphonenumber considers possible; normalise() yields E.164. */
class InternationalPhone implements ValidationRule
{
    /**
     * Returns the E.164 form of a possible number, or null when it cannot be parsed.
     * Numbers without a "+" are read in $defaultRegion when one is given.
     */
    public static function normalize(mixed $phone, ?string $defaultRegion = null): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }
        $compact = preg_replace('/[\s().\-\/]+/', '', trim($phone)) ?? '';
        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }
        if (! str_starts_with($compact, '+') && $defaultRegion === null) {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($compact, str_starts_with($compact, '+') ? null : strtoupper((string) $defaultRegion));
        } catch (NumberParseException) {
            return null;
        }

        return $util->isPossibleNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalize($value) === null) {
            $fail('Enter a valid phone number including the country code.');
        }
    }
}
