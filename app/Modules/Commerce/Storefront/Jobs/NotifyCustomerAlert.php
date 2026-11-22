<?php

namespace App\Modules\Commerce\Storefront\Jobs;

use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Tells one storefront customer about a refund or withdrawal update by app push and email.
 */
class NotifyCustomerAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(public int $workspaceId, public int $customerId, public string $title, public string $body, public array $data = [])
    {
        $this->onQueue('commerce-orders');
    }

    public function handle(): void
    {
        $customer = Customer::query()->where('workspace_id', $this->workspaceId)->with('contact')->find($this->customerId);
        if (! $customer) {
            return;
        }
        MobileCustomerDevice::query()->where('workspace_id', $this->workspaceId)->where('customer_reference', 'storefront:'.$customer->id)
            ->each(fn (MobileCustomerDevice $device) => rescue(fn () => $device->notify(new MobileAlert($this->title, $this->body, $this->data, $this->workspaceId)), report: true));
        $email = $customer->contact?->email;
        if ($email) {
            Mail::raw($this->body, function ($message) use ($email): void {
                $message->to($email)->subject($this->title);
            });
        }
    }
}
