<?php

namespace App\Modules\Currencies\Tests\Feature;

use App\Modules\Currencies\Models\Currency;
use App\Modules\Currencies\Services\VisitorCurrencyService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisitorCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::query()->delete();
        Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::query()->create(['code' => 'GBP', 'name' => 'Pound', 'symbol' => '£', 'exchange_rate' => 0.8, 'is_active' => true]);
        Currency::query()->create(['code' => 'BDT', 'name' => 'Taka', 'symbol' => '৳', 'exchange_rate' => 120, 'is_active' => false]);
        config(['commerce.geoip.url' => 'https://geo.test/{ip}']);
    }

    private function resolveFor(array $server = [], array $cookies = []): string
    {
        return app(VisitorCurrencyService::class)->resolve(Request::create('/', 'GET', [], $cookies, [], $server));
    }

    public function test_country_header_selects_matching_active_currency(): void
    {
        $this->assertSame('GBP', $this->resolveFor(['HTTP_CF_IPCOUNTRY' => 'GB']));
    }

    public function test_ip_lookup_selects_currency(): void
    {
        Http::fake(['geo.test/*' => Http::response(['countryCode' => 'GB'])]);

        $this->assertSame('GBP', $this->resolveFor(['REMOTE_ADDR' => '81.2.69.160']));
    }

    public function test_us_visitor_gets_usd(): void
    {
        $this->assertSame('USD', $this->resolveFor(['HTTP_CF_IPCOUNTRY' => 'US']));
    }

    public function test_inactive_or_unknown_currency_falls_back_to_default(): void
    {
        $this->assertSame(currency_default_code(), $this->resolveFor(['HTTP_CF_IPCOUNTRY' => 'BD']));
        $this->assertSame(currency_default_code(), $this->resolveFor(['HTTP_CF_IPCOUNTRY' => 'XX']));
    }

    public function test_cookie_overrides_ip_country(): void
    {
        $this->assertSame('USD', $this->resolveFor(['HTTP_CF_IPCOUNTRY' => 'GB'], [VisitorCurrencyService::COOKIE => 'USD']));
    }

    public function test_switch_route_sets_cookie_and_rejects_inactive_code(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('currency.switch'), ['code' => 'gbp'])
            ->assertRedirect()
            ->assertCookie(VisitorCurrencyService::COOKIE, 'GBP');

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('currency.switch'), ['code' => 'BDT'])
            ->assertSessionHasErrors('code');
    }

    public function test_display_helper_converts_from_default_currency(): void
    {
        app()->instance('currency.display', 'GBP');

        $this->assertSame('£80.00', currency_display(100, 'USD'));
    }
}
