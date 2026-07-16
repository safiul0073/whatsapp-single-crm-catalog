<?php

use App\Models\Admin;
use App\Modules\PlaceApiSettings\Models\PlaceApiSetting;
use App\Modules\PlaceApiSettings\Services\PlaceApiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

it('saves google places settings and keeps the api key encrypted', function (): void {
    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.place-api-settings.update'), [
            'settings' => [
                'google_places_enabled' => '1',
                'google_places_api_key' => 'google-secret',
                'google_places_language' => 'en',
                'google_places_region' => 'US',
                'google_places_result_limit' => '25',
            ],
        ])
        ->assertRedirect(route('admin.place-api-settings.index'));

    $stored = PlaceApiSetting::query()->where('key', 'google_places_api_key')->value('value');

    expect($stored)->not->toBe('google-secret')
        ->and(Crypt::decryptString($stored))->toBe('google-secret')
        ->and(app(PlaceApiSettingsService::class)->isConfigured())->toBeTrue();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.place-api-settings.update'), [
            'settings' => [
                'google_places_enabled' => '1',
                'google_places_api_key' => '',
                'google_places_language' => 'bn',
                'google_places_region' => 'BD',
                'google_places_result_limit' => '15',
            ],
        ])
        ->assertRedirect(route('admin.place-api-settings.index'));

    expect(Crypt::decryptString(PlaceApiSetting::query()->where('key', 'google_places_api_key')->value('value')))->toBe('google-secret')
        ->and(app(PlaceApiSettingsService::class)->get('google_places_language'))->toBe('bn')
        ->and(app(PlaceApiSettingsService::class)->status()['region'])->toBe('BD');
});

it('requires a google places api key when the source is enabled', function (): void {
    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->from(route('admin.place-api-settings.index'))
        ->put(route('admin.place-api-settings.update'), [
            'settings' => [
                'google_places_enabled' => '1',
                'google_places_api_key' => '',
                'google_places_language' => 'en',
                'google_places_result_limit' => '25',
            ],
        ])
        ->assertRedirect(route('admin.place-api-settings.index'))
        ->assertSessionHasErrors('settings.google_places_api_key');
});
