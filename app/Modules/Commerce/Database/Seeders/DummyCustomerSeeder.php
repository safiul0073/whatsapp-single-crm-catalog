<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyCustomerSeeder extends Seeder
{
    public const DUMMY_PHONE = '+8801876150613';

    public const DUMMY_PASSWORD = 'password';

    public function run(): void
    {
        $workspaces = Workspace::all();
        if ($workspaces->isEmpty()) {
            return;
        }

        foreach ($workspaces as $workspace) {
            // 1. Enable password_login in StorefrontSetting
            $setting = StorefrontSetting::query()->firstOrCreate(
                ['workspace_id' => $workspace->id],
                ['config' => []]
            );
            $config = $setting->config ?? [];
            $config['password_login'] = true;
            $setting->update(['config' => $config]);

            // 2. Create or update Contact
            $contact = Contact::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'phone' => self::DUMMY_PHONE,
                ],
                [
                    'name' => 'Test Customer',
                    'email' => 'customer@fashionfiest.com',
                    'country' => 'BD',
                    'city' => 'Dhaka',
                    'opt_in_status' => ContactOptInStatus::Subscribed,
                    'opt_in_at' => now(),
                    'blocked_at' => null,
                ]
            );

            // 3. Create or update Customer with password
            $customer = Customer::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'verified_phone' => self::DUMMY_PHONE,
                ],
                [
                    'contact_id' => $contact->id,
                    'password' => Hash::make(self::DUMMY_PASSWORD),
                    'verified_at' => now(),
                    'registered_at' => now(),
                ]
            );

            // 4. Create default shipping address if none exists
            if (! CustomerAddress::query()->where('customer_id', $customer->id)->exists()) {
                CustomerAddress::query()->create([
                    'workspace_id' => $workspace->id,
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                    'is_default' => true,
                    'address' => [
                        'name' => 'Test Customer',
                        'phone' => self::DUMMY_PHONE,
                        'line1' => 'House 12, Road 5, Block A',
                        'line2' => 'Banani',
                        'city' => 'Dhaka',
                        'state' => 'Dhaka',
                        'postal_code' => '1213',
                        'country' => 'BD',
                    ],
                ]);
            }
        }
    }
}
