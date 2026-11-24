<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Services\ContactService;
use Illuminate\Validation\ValidationException;

/**
 * Shares an order's packing slip PDF on WhatsApp with several people, free-form first with the template fallback.
 */
class OrderPackingSlipShareService
{
    public function __construct(protected OrderInvoiceWhatsAppService $whatsApp, protected ContactService $contacts) {}

    /**
     * The numbers to share with: the picked saved recipients plus one optional extra number, normalized and de-duplicated.
     *
     * @param  list<string>  $picked
     * @return list<array{name: ?string, phone: string}>
     *
     * @throws ValidationException when a number is not a valid international number
     */
    public function recipients(int $workspaceId, array $picked, ?string $extraPhone): array
    {
        $names = collect(StoreOrderSetting::forWorkspace($workspaceId)->packing_slip_recipients ?? [])->pluck('name', 'phone');
        $recipients = [];
        foreach (array_filter([...$picked, $extraPhone]) as $phone) {
            try {
                $normalized = $this->contacts->normalizePhone((string) $phone);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['extra_phone' => 'Enter a valid international WhatsApp number, for example +8801711223344.']);
            }
            $recipients[$normalized] = ['name' => $names[$normalized] ?? null, 'phone' => $normalized];
        }

        return array_values($recipients);
    }

    /**
     * @param  list<array{name: ?string, phone: string}>  $recipients
     * @return array{sent: int, problems: list<string>}
     */
    public function share(Order $order, array $recipients): array
    {
        $sent = 0;
        $problems = [];
        foreach ($recipients as $recipient) {
            $contact = $this->contacts->findOrCreate($order->workspace_id, $recipient['phone'], $recipient['name']);
            $result = $this->whatsApp->sendPackingSlip($order, $contact);
            if ($result['status'] === OrderInvoiceWhatsAppService::SENT) {
                $sent++;
            } else {
                $problems[] = ($recipient['name'] ?? $recipient['phone']).': '.$result['message'];
            }
        }

        return ['sent' => $sent, 'problems' => $problems];
    }
}
