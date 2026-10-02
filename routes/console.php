<?php

use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Jobs\SendCustomerWhatsAppWelcome;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\WhatsAppCustomerRegistration;
use App\Modules\Commerce\Services\OrderInventoryService;
use App\Modules\SchedulerQueue\Jobs\RunManagedSchedulerJob;
use Illuminate\Support\Facades\Schedule;

if (config('services.order_scheduler_scope') !== 'commerce') {
    Schedule::job(new RunManagedSchedulerJob)->everyMinute();
}

Schedule::call(fn () => app(OrderInventoryService::class)->expire())->name('commerce-expire-reservations')->everyMinute()->withoutOverlapping();

Schedule::call(function (): void {
    OrderEvent::query()->where('notification_attempts', '<', 10)->where(function ($query): void {
        $query->whereNull('staff_notified_at')->orWhereNull('customer_notified_at')->orWhereNull('whatsapp_notified_at');
    })->eachById(fn ($event) => NotifyOrderEvent::dispatch($event->id));
})->name('commerce-recover-event-notifications')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function (): void {
    WhatsAppCustomerRegistration::query()->where('welcome_status', 'processing')->where('updated_at', '<', now()->subMinutes(10))->update(['welcome_status' => 'uncertain']);
    WhatsAppCustomerRegistration::query()->whereIn('welcome_status', ['pending', 'failed'])->where('welcome_attempts', '<', 3)->each(fn ($registration) => SendCustomerWhatsAppWelcome::dispatch($registration->id));
})->name('commerce-recover-customer-welcome')->everyFiveMinutes()->withoutOverlapping();
