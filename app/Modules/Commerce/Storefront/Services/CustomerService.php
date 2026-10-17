<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function enroll(Contact $contact, bool $verified = false): Customer
    {
        return DB::transaction(function () use ($contact, $verified): Customer {
            $contact = Contact::query()->lockForUpdate()->findOrFail($contact->id);
            $customer = Customer::query()->firstOrCreate(['workspace_id' => $contact->workspace_id, 'contact_id' => $contact->id]);
            if ($verified) {
                $customer->update(['verified_phone' => $contact->phone, 'verified_at' => now(), 'registered_at' => $customer->registered_at ?? now()]);
            }

            return $customer;
        });
    }

    public function profile(Customer $customer): array
    {
        return ['id' => $customer->id, 'name' => $customer->contact->name, 'phone' => $customer->contact->phone, 'email' => $customer->contact->email, 'verified' => (bool) $customer->verified_at];
    }

    public function updateProfile(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $contact = Contact::query()->where('workspace_id', $customer->workspace_id)->lockForUpdate()->findOrFail($customer->contact_id);
            $contact->update(['name' => $data['name'], 'email' => $data['email'] ?? null]);

            return $customer->fresh('contact');
        });
    }

    public function session(Customer $customer, bool $freshVerification = false): array
    {
        $abilities = $freshVerification ? ['storefront', 'password:verified'] : ['storefront'];

        return ['token' => $customer->createToken('storefront', $abilities, now()->addDays(30))->plainTextToken, 'customer' => $this->profile($customer)];
    }
}
