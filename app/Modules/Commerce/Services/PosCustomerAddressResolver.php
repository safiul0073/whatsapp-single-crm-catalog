<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\ContactAddress;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;

class PosCustomerAddressResolver
{
    private const MAX_ADDRESSES = 5;

    /**
     * Resolves POS-ready shipping addresses for a batch of contacts within a workspace,
     * merging POS-saved addresses, storefront/mobile customer addresses, past order
     * shipping addresses, and contact custom-field addresses.
     *
     * @param  Collection<int, Contact>  $contacts
     * @return array<int, list<array<string, mixed>>>
     */
    public function resolveForContacts(Workspace $workspace, Collection $contacts): array
    {
        if ($contacts->isEmpty()) {
            return [];
        }

        $contactIds = $contacts->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        $posAddresses = ContactAddress::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('contact_id', $contactIds)
            ->orderByDesc('is_default')
            ->latest('id')
            ->get()
            ->groupBy('contact_id');

        $customers = Customer::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('contact_id', $contactIds)
            ->get(['id', 'contact_id']);

        $customerIdToContactId = $customers->pluck('contact_id', 'id');

        $accountAddresses = $customers->isEmpty()
            ? collect()
            : CustomerAddress::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('customer_id', $customers->pluck('id'))
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->groupBy(fn (CustomerAddress $address): int => (int) ($customerIdToContactId[$address->customer_id] ?? 0));

        $orders = Order::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('shipping_address')
            ->latest('id')
            ->get(['id', 'contact_id', 'shipping_address'])
            ->groupBy('contact_id');

        $resolved = [];

        foreach ($contacts as $contact) {
            $contactId = (int) $contact->id;
            $candidates = [];

            foreach ($posAddresses->get($contactId, collect()) as $posAddress) {
                $normalized = $this->normalize(
                    $posAddress->only(['name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country']),
                    $contact,
                    $posAddress->id,
                    (bool) $posAddress->is_default,
                );
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }

            foreach ($accountAddresses->get($contactId, collect()) as $accountAddress) {
                if (! is_array($accountAddress->address)) {
                    continue;
                }
                $normalized = $this->normalize(
                    $accountAddress->address,
                    $contact,
                    'account-'.$accountAddress->id,
                    (bool) $accountAddress->is_default,
                );
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }

            foreach ($orders->get($contactId, collect())->take(10) as $order) {
                if (! is_array($order->shipping_address)) {
                    continue;
                }
                $normalized = $this->normalize(
                    $order->shipping_address,
                    $contact,
                    'order-'.$order->id,
                    false,
                );
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }

            $customAddress = is_array($contact->custom_fields['address'] ?? null)
                ? $contact->custom_fields['address']
                : null;
            if ($customAddress !== null) {
                $normalized = $this->normalize(
                    $customAddress,
                    $contact,
                    'contact-'.$contactId,
                    false,
                );
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }

            $resolved[$contactId] = $this->deduplicateAndSelectDefault($candidates);
        }

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveForContact(Workspace $workspace, Contact $contact): array
    {
        return $this->resolveForContacts($workspace, new Collection([$contact]))[(int) $contact->id] ?? [];
    }

    /**
     * Persists a delivery address into the POS contact address book (`commerce_contact_addresses`).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveContactAddress(Contact $contact, array $data): ContactAddress
    {
        $normalized = $this->normalize($data, $contact, 0, false) ?? [
            'name' => trim((string) ($data['name'] ?? $contact->name ?? '')),
            'phone' => trim((string) ($data['phone'] ?? $contact->phone ?? '')),
            'line1' => trim((string) ($data['line1'] ?? '')),
            'line2' => filled($data['line2'] ?? null) ? trim((string) $data['line2']) : null,
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => filled($data['state'] ?? null) ? trim((string) $data['state']) : null,
            'postal_code' => filled($data['postal_code'] ?? null) ? trim((string) $data['postal_code']) : null,
            'country' => strtoupper(trim((string) ($data['country'] ?? $contact->country ?: 'US'))) ?: 'US',
        ];

        $attributes = collect($normalized)->only(['name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'])->all();

        $address = ContactAddress::query()->firstOrNew([
            'workspace_id' => $contact->workspace_id,
            'contact_id' => $contact->id,
            'line1' => $attributes['line1'],
            'postal_code' => $attributes['postal_code'] ?? null,
        ]);

        $address->fill([...$attributes, 'country' => $attributes['country'] ?? 'US']);
        if (! $address->exists) {
            $address->is_default = ! ContactAddress::query()->where('contact_id', $contact->id)->exists();
        }
        $address->save();

        return $address;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalize(array $raw, Contact $contact, int|string $id, bool $isDefault): ?array
    {
        $line1 = $this->firstFilled($raw, ['line1', 'address_line1', 'address_line_1', 'street', 'address']);
        $city = $this->firstFilled($raw, ['city', 'town']);

        if ($line1 === null || $city === null) {
            return null;
        }

        $line2 = $this->firstFilled($raw, ['line2', 'address_line2', 'address_line_2', 'area']);
        $state = $this->firstFilled($raw, ['state', 'region', 'division', 'province']);
        $postalCode = $this->firstFilled($raw, ['postal_code', 'zip', 'postcode']);
        $country = strtoupper((string) ($this->firstFilled($raw, ['country', 'country_code']) ?? ($contact->country ?: 'US')));
        if (strlen($country) !== 2) {
            $country = 'US';
        }

        $name = $this->firstFilled($raw, ['name', 'recipient_name']) ?? (filled($contact->name) ? trim((string) $contact->name) : '');
        $phone = $this->firstFilled($raw, ['phone', 'recipient_phone']) ?? (filled($contact->phone) ? trim((string) $contact->phone) : '');

        return [
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'line1' => $line1,
            'line2' => $line2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
            'is_default' => $isDefault,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    private function firstFilled(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) || is_numeric($value)) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function deduplicateAndSelectDefault(array $candidates): array
    {
        $unique = [];
        foreach ($candidates as $candidate) {
            $key = mb_strtolower(
                trim((string) $candidate['line1'])
                .'|'.trim((string) ($candidate['postal_code'] ?? ''))
                .'|'.trim((string) ($candidate['country'] ?? 'US'))
            );

            if (! isset($unique[$key])) {
                $unique[$key] = $candidate;
            } elseif ($candidate['is_default'] && ! $unique[$key]['is_default']) {
                $unique[$key]['is_default'] = true;
            }
        }

        $items = array_slice(array_values($unique), 0, self::MAX_ADDRESSES);
        if ($items === []) {
            return [];
        }

        $defaultAssigned = false;
        foreach ($items as $index => $item) {
            if ($item['is_default'] && ! $defaultAssigned) {
                $defaultAssigned = true;
            } else {
                $items[$index]['is_default'] = false;
            }
        }

        if (! $defaultAssigned) {
            $items[0]['is_default'] = true;
        }

        return $items;
    }
}
