<?php

namespace App\Modules\Currencies\Tests\Feature;

use App\Models\User;
use App\Modules\Currencies\Models\Currency;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CurrenciesModuleTest extends TestCase
{
    public function test_currencies_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('currencies');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.currencies.index'));
    }

    public function test_can_create_currency_and_forces_uppercase_code(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->post(route('admin.currencies.store'), [
                'code' => 'usd',
                'name' => 'United States Dollar',
                'symbol' => '$',
                'exchange_rate' => 1.0,
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('admin.currencies.index'));

        $this->assertDatabaseHas('currencies', [
            'code' => 'USD', // Upper-cased by prepareForValidation
            'name' => 'United States Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_create_validation_error_flashes_open_modal_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->post(route('admin.currencies.store'), [
                'code' => '', // Required
            ]);

        $response->assertSessionHas('open_modal', 'addCurrencyModal');
    }

    public function test_can_update_currency(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $currency = Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'exchange_rate' => 0.85,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->put(route('admin.currencies.update', $currency), [
                'code' => 'eur',
                'name' => 'Euro Updated',
                'symbol' => '€',
                'exchange_rate' => 0.90,
                'is_active' => false,
                'sort_order' => 5,
            ]);

        $response->assertRedirect(route('admin.currencies.index'));

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'code' => 'EUR',
            'name' => 'Euro Updated',
            'exchange_rate' => 0.90,
            'is_active' => false,
            'sort_order' => 5,
        ]);
    }

    public function test_can_bulk_delete_currencies(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $c1 = Currency::query()->create([
            'code' => 'CAD',
            'name' => 'Canadian Dollar',
            'symbol' => 'C$',
            'exchange_rate' => 1.25,
            'is_active' => true,
        ]);
        $c2 = Currency::query()->create([
            'code' => 'AUD',
            'name' => 'Australian Dollar',
            'symbol' => 'A$',
            'exchange_rate' => 1.30,
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->postJson(route('admin.currencies.bulk-delete'), [
                'ids' => [$c1->id, $c2->id],
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('currencies', ['id' => $c1->id]);
        $this->assertDatabaseMissing('currencies', ['id' => $c2->id]);
    }
}
