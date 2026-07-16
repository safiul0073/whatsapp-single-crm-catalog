<?php

use App\Modules\Settings\Services\SettingsService;
use Carbon\Carbon;

if (! function_exists('setting')) {
    /**
     * Get a setting value
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date using the system date_format setting.
     *
     * @param  bool  $withTime  Append time (h:i A) after the date format
     */
    function format_date(DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if (! $date) {
            return '';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $format = setting('date_format', 'd M, Y');

        if ($withTime) {
            $format .= ' \a\t h:i A';
        }

        return $date->format($format);
    }
}
