<?php

use App\Models\Admin;
use App\Modules\MetaSocial\Services\MetaAppSettingsService;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function metaAppSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'meta_app_id' => 'shared-app',
        'meta_app_secret' => 'shared-secret',
        'meta_graph_api_version' => 'v24.0',
        'meta_default_verify_token' => 'shared-verify',
        'meta_webhook_base_url' => 'https://hooks.example.com',
        'meta_business_config_id' => 'business-config',
        'meta_ads_config_id' => 'ads-config',
        'whatsapp_embedded_signup_config_id' => 'wa-config',
        'whatsapp_embedded_signup_enabled' => '1',
        'whatsapp_auto_sync_templates' => '0',
        'whatsapp_auto_sync_phone_numbers' => '1',
        'meta_social_messenger_config_id' => 'messenger-config',
        'meta_social_instagram_config_id' => 'instagram-config',
        'meta_social_embedded_signup_enabled' => '1',
    ], $overrides);
}

it('saves one Meta app that WhatsApp and Messenger/Instagram both use', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_id' => 'old-wa-app']);
    app(MetaSocialSettingsService::class)->update(['meta_social_app_id' => 'old-social-app']);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->put(route('admin.meta-app.settings.update'), metaAppSettingsPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $whatsapp = app(WhatsAppSettingsService::class);
    $social = app(MetaSocialSettingsService::class);

    expect($whatsapp->get('whatsapp_meta_app_id'))->toBe('shared-app')
        ->and($whatsapp->get('whatsapp_meta_app_secret'))->toBe('shared-secret')
        ->and($whatsapp->webhookBaseUrl())->toBe('https://hooks.example.com')
        ->and($whatsapp->get('whatsapp_embedded_signup_config_id'))->toBe('wa-config')
        ->and($whatsapp->enabled('whatsapp_auto_sync_templates'))->toBeFalse()
        ->and($whatsapp->embeddedSignupReady())->toBeTrue()
        ->and($social->get('meta_social_app_id'))->toBe('shared-app')
        ->and($social->get('meta_social_default_verify_token'))->toBe('shared-verify')
        ->and($social->graphApiVersion())->toBe('v24.0')
        ->and($social->embeddedSignupReady('instagram'))->toBeTrue()
        ->and(app(MetaAppSettingsService::class)->get('meta_business_config_id'))->toBe('business-config');
});

it('falls back to legacy per-product keys until the shared app is saved', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_id' => 'old-wa-app', 'whatsapp_graph_api_version' => 'v21.0']);
    app(MetaSocialSettingsService::class)->update(['meta_social_app_id' => 'old-social-app']);

    expect(app(WhatsAppSettingsService::class)->get('whatsapp_meta_app_id'))->toBe('old-wa-app')
        ->and(app(WhatsAppSettingsService::class)->graphApiVersion())->toBe('v21.0')
        ->and(app(MetaSocialSettingsService::class)->get('meta_social_app_id'))->toBe('old-social-app');
});

it('shows the unified page prefilled from legacy settings and warns about separate apps', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_id' => 'old-wa-app']);
    app(MetaSocialSettingsService::class)->update(['meta_social_app_id' => 'old-social-app']);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.meta-social.settings.index'))
        ->assertRedirect(route('admin.meta-app.settings.index'));

    $this->actingAs($admin, 'admin')
        ->get(route('admin.meta-app.settings.index'))
        ->assertOk()
        ->assertSee('value="old-wa-app"', false)
        ->assertSee('currently use different Meta apps');
});

it('rejects an invalid graph api version', function (): void {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->put(route('admin.meta-app.settings.update'), metaAppSettingsPayload(['meta_graph_api_version' => 'latest']))
        ->assertSessionHasErrors('meta_graph_api_version');
});

it('runs the admin platform checklist only when asked', function (): void {
    Http::fake(['*/meta-app-1*' => Http::response(['id' => 'meta-app-1', 'name' => 'Acme Platform'])]);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_id' => 'meta-app-1', 'whatsapp_meta_app_secret' => 'secret']);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.meta-app.settings.index'))->assertOk()->assertSee('Run check');
    Http::assertNothingSent();

    $this->actingAs($admin, 'admin')
        ->from(route('admin.meta-app.settings.index'))
        ->post(route('admin.meta-app.settings.check'))
        ->assertRedirect(route('admin.meta-app.settings.index'))
        ->assertSessionHas('error');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.meta-app.settings.index'))
        ->assertOk()
        ->assertSee('App: Acme Platform')
        ->assertSee('Business (all assets) configuration ID');
});
