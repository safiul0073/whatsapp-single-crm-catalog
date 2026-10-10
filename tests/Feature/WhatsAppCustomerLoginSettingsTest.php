<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureSubscriptionUsable;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Commerce\Models\CustomerAuthSetting;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Services\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureSubscriptionUsable::class, EnsureTwoFactorAuthenticated::class, PanelAccess::class]);
    $this->allowedPermissions = ['channels.manage', 'commerce.manage', 'commerce.view'];
    Gate::before(fn ($user, $ability) => in_array($ability, ['channels.manage', 'commerce.manage', 'commerce.view']) ? in_array($ability, $this->allowedPermissions) : null);
    $this->user = User::factory()->create();
    $this->workspace = Workspace::query()->create(['name' => 'Login settings', 'slug' => 'login-settings-'.Str::uuid(), 'owner_id' => $this->user->id, 'status' => 'active']);
    $this->withSession(['active_workspace_id' => $this->workspace->id]);
});

it('renders customer login configuration as a WhatsApp setup tab', function (): void {
    $this->actingAs($this->user)->get(route('user.whatsapp-cloud.customer-login'))->assertOk()
        ->assertSee('Channel Setup')->assertSee('Customer Login')->assertSee('Enable WhatsApp customer login')
        ->assertSee('Authentication template')->assertSee('First-registration welcome template')
        ->assertSee('Generate storefront integration credential');
});

it('explains login template variables beside their selectors', function (): void {
    $this->actingAs($this->user)->get(route('user.whatsapp-cloud.customer-login'))->assertOk()
        ->assertSeeInOrder([
            'id="auth_template" aria-describedby="auth_template_help"',
            'Verification code — {{1}} is automatically replaced with the customer’s login code. Example: 482913.',
            'id="welcome_template" aria-describedby="welcome_template_help"',
            'Customer name — {{1}} is automatically replaced with the customer’s name. Example: Hello Test User.',
        ], false);
});

it('links order settings to the new tab without embedding customer authentication controls', function (): void {
    $this->actingAs($this->user)->get(route('user.commerce.orders.settings'))->assertOk()
        ->assertSee(route('user.whatsapp-cloud.customer-login'))
        ->assertDontSee('Enable WhatsApp customer login')->assertDontSee('First-registration welcome template');
});

it('hides channels belonging to other workspaces', function (): void {
    $other = Workspace::query()->create(['name' => 'Other', 'slug' => 'other-login-'.Str::uuid(), 'status' => 'active']);
    ChannelAccount::query()->create(['workspace_id' => $other->id, 'provider' => 'whatsapp', 'name' => 'Private channel', 'status' => 'connected']);
    ChannelAccount::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'whatsapp', 'name' => 'Current channel', 'status' => 'connected']);
    $this->actingAs($this->user)->get(route('user.whatsapp-cloud.customer-login'))->assertOk()
        ->assertSee('Current channel')->assertDontSee('Private channel');
});

it('requires both channel and commerce management access', function (array $permissions): void {
    $this->allowedPermissions = $permissions;
    $this->mock(WorkspacePermissionResolver::class)
        ->shouldReceive('can')->andReturnUsing(fn ($user, $ability) => in_array($ability, $permissions));
    $this->actingAs($this->user)->get(route('user.whatsapp-cloud.customer-login'))->assertForbidden();
})->with([[['channels.manage']], [['commerce.manage']]]);

it('saves customer login settings and returns to the new tab', function (): void {
    $url = route('user.whatsapp-cloud.customer-login');
    $this->actingAs($this->user)->from($url)->put(route('user.commerce.orders.settings.customer-auth'), ['enabled' => '0'])
        ->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(CustomerAuthSetting::forWorkspace($this->workspace->id)->enabled)->toBeFalse();
});
