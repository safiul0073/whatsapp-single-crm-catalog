<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function enroll(Contact $contact, bool $verified = false, ?string $source = null): Customer
    {
        return DB::transaction(function () use ($contact, $verified, $source): Customer {
            $contact = Contact::query()->lockForUpdate()->findOrFail($contact->id);
            $channel = $source ?: ($this->isMobileRequest() ? 'mobile_app' : 'storefront');
            $customer = Customer::query()->firstOrCreate(
                ['workspace_id' => $contact->workspace_id, 'contact_id' => $contact->id],
                ['source' => $channel]
            );
            $updates = [
                'last_login_at' => now(),
                'last_login_channel' => $channel,
            ];
            if ($verified) {
                $updates['verified_phone'] = $contact->phone;
                $updates['verified_at'] = now();
                $updates['registered_at'] = $customer->registered_at ?? now();
            }
            $customer->update($updates);

            return $customer;
        });
    }

    public function profile(Customer $customer): array
    {
        $defaultAddress = CustomerAddress::query()
            ->where('workspace_id', $customer->workspace_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->first();

        $address = $defaultAddress?->address ?? ($customer->contact->custom_fields['address'] ?? null);
        if (! $address && ($customer->contact->city || $customer->contact->country)) {
            $address = array_filter([
                'city' => $customer->contact->city,
                'country' => $customer->contact->country,
            ]);
        }

        return [
            'id' => $customer->id,
            'name' => $customer->contact->name,
            'phone' => $customer->contact->phone,
            'email' => $customer->contact->email,
            'verified' => (bool) $customer->verified_at,
            'address' => $address,
        ];
    }

    public function updateProfile(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $contact = Contact::query()->where('workspace_id', $customer->workspace_id)->lockForUpdate()->findOrFail($customer->contact_id);
            $contactData = ['name' => $data['name'], 'email' => $data['email'] ?? null];

            if (! empty($data['address']) && is_array($data['address'])) {
                $addr = array_filter($data['address']);
                if (! empty($addr['city'])) {
                    $contactData['city'] = $addr['city'];
                }
                if (! empty($addr['country'])) {
                    $contactData['country'] = $addr['country'];
                }
                $customFields = $contact->custom_fields ?? [];
                $customFields['address'] = $addr;
                $contactData['custom_fields'] = $customFields;

                $defaultAddress = CustomerAddress::query()
                    ->where('workspace_id', $customer->workspace_id)
                    ->where('customer_id', $customer->id)
                    ->orderByDesc('is_default')
                    ->first();

                if ($defaultAddress) {
                    $defaultAddress->update(['address' => array_merge($defaultAddress->address ?? [], $addr)]);
                } elseif (! empty($addr['line1'])) {
                    CustomerAddress::query()->create([
                        'workspace_id' => $customer->workspace_id,
                        'customer_id' => $customer->id,
                        'label' => 'Home',
                        'is_default' => true,
                        'address' => array_merge(['name' => $data['name'], 'phone' => $customer->phone], $addr),
                    ]);
                }
            }

            $contact->update($contactData);

            return $customer->fresh('contact');
        });
    }

    public function session(Customer $customer, bool $freshVerification = false): array
    {
        $channel = $this->isMobileRequest() ? 'mobile_app' : 'storefront';
        $customer->update([
            'last_login_at' => now(),
            'last_login_channel' => $channel,
        ]);

        $abilities = $freshVerification ? ['storefront', 'password:verified'] : ['storefront'];

        return ['token' => $customer->createToken('storefront', $abilities, now()->addDays(30))->plainTextToken, 'customer' => $this->profile($customer)];
    }

    private function isMobileRequest(): bool
    {
        return request()->is('api/v1/mobile/*')
            || request()->routeIs('mobile.*')
            || filled(request()->header('X-Mobile-Platform'))
            || request()->header('X-Platform') === 'mobile';
    }
}
