<?php

use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Services\WithdrawalScheduleService;
use Carbon\CarbonImmutable;

function withdrawalSchedule(array $overrides = []): ReferralSetting
{
    return (new ReferralSetting)->forceFill(['withdrawal_frequency' => 'weekly', 'withdrawal_weekly_day' => 5, 'withdrawal_monthly_day' => 31, 'withdrawal_opens_at' => '09:00', 'withdrawal_closes_at' => '18:00', ...$overrides]);
}

it('opens the weekly window only on the chosen day between the opening and closing time in Dhaka', function (): void {
    $service = new WithdrawalScheduleService;
    $friday = fn (string $time) => CarbonImmutable::parse('2026-11-20 '.$time, 'Asia/Dhaka');

    expect($service->currentWindow(withdrawalSchedule(), $friday('08:59')))->toBeNull()
        ->and($service->currentWindow(withdrawalSchedule(), $friday('09:00')))->not->toBeNull()
        ->and($service->currentWindow(withdrawalSchedule(), $friday('17:59')))->not->toBeNull()
        ->and($service->currentWindow(withdrawalSchedule(), $friday('18:00')))->toBeNull()
        ->and($service->currentWindow(withdrawalSchedule(), CarbonImmutable::parse('2026-11-19 12:00', 'Asia/Dhaka')))->toBeNull();
});

it('uses Dhaka time even when the clock is given in another timezone', function (): void {
    $service = new WithdrawalScheduleService;

    // 03:30 UTC on Friday is 09:30 in Dhaka; 20:00 UTC on Thursday is already 02:00 Friday in Dhaka.
    expect($service->currentWindow(withdrawalSchedule(), CarbonImmutable::parse('2026-11-20 03:30', 'UTC')))->not->toBeNull()
        ->and($service->currentWindow(withdrawalSchedule(), CarbonImmutable::parse('2026-11-20 13:00', 'UTC')))->toBeNull()
        ->and($service->nextWindow(withdrawalSchedule(), CarbonImmutable::parse('2026-11-19 20:00', 'UTC'))['starts_at']->format('Y-m-d H:i'))->toBe('2026-11-20 09:00');
});

it('lists upcoming weekly windows and skips the one that is already open', function (): void {
    $upcoming = (new WithdrawalScheduleService)->upcoming(withdrawalSchedule(), 3, CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Dhaka'));

    expect(array_map(fn (array $w): string => $w['starts_at']->format('Y-m-d'), $upcoming))->toBe(['2026-11-27', '2026-12-04', '2026-12-11']);
});

it('falls back to the last day in months shorter than the chosen monthly date', function (): void {
    $upcoming = (new WithdrawalScheduleService)->upcoming(withdrawalSchedule(['withdrawal_frequency' => 'monthly']), 4, CarbonImmutable::parse('2027-01-15 10:00', 'Asia/Dhaka'));

    expect(array_map(fn (array $w): string => $w['starts_at']->format('Y-m-d'), $upcoming))->toBe(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30']);
});
