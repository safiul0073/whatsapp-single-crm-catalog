<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use Carbon\CarbonImmutable;

/**
 * Works out when the owner-configured withdrawal window is open. Every calculation runs in
 * Asia/Dhaka, whatever timezone the request or the server happens to use.
 */
class WithdrawalScheduleService
{
    public const TIMEZONE = 'Asia/Dhaka';

    /**
     * The window that is open right now, if any.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public function currentWindow(ReferralSetting $settings, ?CarbonImmutable $now = null): ?array
    {
        $now = $this->now($now);
        $window = $this->windowOn($settings, $now);

        return $window && $now->gte($window['starts_at']) && $now->lt($window['ends_at']) ? $window : null;
    }

    /**
     * The next window that has not opened yet.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public function nextWindow(ReferralSetting $settings, ?CarbonImmutable $now = null): ?array
    {
        return $this->upcoming($settings, 1, $now)[0] ?? null;
    }

    /**
     * The next windows that have not opened yet, soonest first.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function upcoming(ReferralSetting $settings, int $count, ?CarbonImmutable $now = null): array
    {
        $now = $this->now($now);
        $windows = [];
        for ($offset = 0; $offset <= 400 && count($windows) < $count; $offset++) {
            $window = $this->windowOn($settings, $now->addDays($offset));
            if ($window && $window['starts_at']->gt($now)) {
                $windows[] = $window;
            }
        }

        return $windows;
    }

    /**
     * The window that falls on the given Dhaka calendar day, if that day is a withdrawal day.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    private function windowOn(ReferralSetting $settings, CarbonImmutable $day): ?array
    {
        $matches = $settings->withdrawal_frequency === 'weekly'
            ? $day->dayOfWeek === (int) $settings->withdrawal_weekly_day
            : $day->day === min(max(1, (int) $settings->withdrawal_monthly_day), $day->daysInMonth);
        if (! $matches) {
            return null;
        }
        $startsAt = $day->setTimeFromTimeString(($settings->withdrawal_opens_at ?: '09:00').':00');
        $endsAt = $day->setTimeFromTimeString(($settings->withdrawal_closes_at ?: '18:00').':00');

        return $endsAt->gt($startsAt) ? ['starts_at' => $startsAt, 'ends_at' => $endsAt] : null;
    }

    private function now(?CarbonImmutable $now): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE);
    }
}
