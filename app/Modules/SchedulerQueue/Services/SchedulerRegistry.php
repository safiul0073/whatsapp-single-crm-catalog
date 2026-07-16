<?php

namespace App\Modules\SchedulerQueue\Services;

use App\Modules\Crm\Jobs\SendCrmTaskRemindersJob;
use App\Modules\PlansSubscriptions\Jobs\ExpireSubscriptionsJob;
use App\Modules\PlansSubscriptions\Jobs\SendSubscriptionExpiryReminderJob;

class SchedulerRegistry
{
    public const TYPE_JOB = 'job';

    public const TYPE_COMMAND = 'command';

    /**
     * @return array<string, array{label: string, type: string, target: class-string|string, frequency: string, queue: string, enabled: bool, options?: array<string, mixed>}>
     */
    public function entries(): array
    {
        return [
            'crm-task-reminders' => [
                'label' => 'CRM Task Reminders',
                'type' => self::TYPE_JOB,
                'target' => SendCrmTaskRemindersJob::class,
                'frequency' => 'every_minute',
                'queue' => 'default',
                'enabled' => true,
                'options' => [],
            ],
            'subscription-expiry-reminders' => [
                'label' => 'Subscription Expiry Reminders',
                'type' => self::TYPE_JOB,
                'target' => SendSubscriptionExpiryReminderJob::class,
                'frequency' => 'hourly',
                'queue' => 'default',
                'enabled' => true,
                'options' => [],
            ],
            'subscription-expiry-processing' => [
                'label' => 'Subscription Expiry Processing',
                'type' => self::TYPE_JOB,
                'target' => ExpireSubscriptionsJob::class,
                'frequency' => 'hourly',
                'queue' => 'default',
                'enabled' => true,
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function frequencies(): array
    {
        return [
            'every_minute' => 'Every minute',
            'every_five_minutes' => 'Every five minutes',
            'every_fifteen_minutes' => 'Every fifteen minutes',
            'every_thirty_minutes' => 'Every thirty minutes',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
        ];
    }

    public function registered(string $key): ?array
    {
        return $this->entries()[$key] ?? null;
    }

    public function isRegistered(string $key): bool
    {
        return isset($this->entries()[$key]);
    }

    public function frequencyIsSupported(string $frequency): bool
    {
        return isset($this->frequencies()[$frequency]);
    }

    /**
     * @return array<int, string>
     */
    public function queueNames(): array
    {
        return array_values(array_unique(array_filter([
            config('queue.connections.database.queue', 'default'),
            'default',
        ])));
    }
}
