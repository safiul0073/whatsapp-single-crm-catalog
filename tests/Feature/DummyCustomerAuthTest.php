<?php

use App\Modules\Commerce\Database\Seeders\DummyCustomerSeeder;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'storefront.platform_secret' => str_repeat('p', 40),
        'cache.default' => 'array',
    ]);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
});

test('dummy customer can login with phone and password', function (): void {
    $workspace = Workspace::query()->create([
        'name' => 'Default Store',
        'slug' => 'default-store',
        'status' => 'active',
    ]);

    StoreDomain::query()->create([
        'workspace_id' => $workspace->id,
        'host' => 'localhost',
        'handle' => 'default-store',
        'type' => 'system',
        'is_primary' => true,
        'status' => 'active',
        'verification_token' => 'test-token',
        'verified_at' => now(),
    ]);

    $this->seed(DummyCustomerSeeder::class);

    $response = $this->withHeaders([
        'X-Store-Host' => 'localhost',
        'Accept' => 'application/json',
    ])->postJson('/api/v1/mobile/storefront/auth/password/login', [
        'phone' => DummyCustomerSeeder::DUMMY_PHONE,
        'password' => DummyCustomerSeeder::DUMMY_PASSWORD,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.customer.phone', DummyCustomerSeeder::DUMMY_PHONE)
        ->assertJsonStructure([
            'data' => [
                'token',
                'customer' => ['id', 'name', 'phone', 'email', 'verified'],
            ],
        ]);

    $token = $response->json('data.token');
    expect($token)->not->toBeEmpty();

    $meResponse = $this->withHeaders([
        'X-Store-Host' => 'localhost',
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/mobile/storefront/me');

    $meResponse->assertStatus(200)
        ->assertJsonPath('data.phone', DummyCustomerSeeder::DUMMY_PHONE);
});
