<?php

use App\Http\Controllers\Auth\LoginController;
use App\Modules\Crm\Module as CrmModule;
use App\Modules\MessageTemplates\Module as MessageTemplatesModule;
use Illuminate\Support\Facades\Route;

it('does not expose multi-user billing or onboarding routes', function (string $uri): void {
    $this->get($uri)->assertNotFound();
})->with([
    '/register',
    '/auth/google/redirect',
    '/api/v1/auth/social/google/redirect',
    '/invite/example-token',
    '/onboarding/workspace',
    '/onboarding/plan',
    '/pricing',
    '/dashboard/workspaces',
    '/dashboard/subscription',
]);

it('does not inject onboarding into password login', function (): void {
    $constructor = new ReflectionMethod(LoginController::class, '__construct');

    expect(collect($constructor->getParameters())->pluck('name')->all())
        ->toBe(['auditLogService', 'loginActivityService']);
});

it('grants the single user access to CRM and templates', function (): void {
    $crmRoute = Route::getRoutes()->getByName('user.crm.index');
    $templatesRoute = Route::getRoutes()->getByName('user.message-templates.index');

    expect(Route::has('user.crm.index'))->toBeTrue()
        ->and(Route::has('user.message-templates.index'))->toBeTrue()
        ->and($crmRoute->gatherMiddleware())->not->toContain('can:crm.view')
        ->and($templatesRoute->gatherMiddleware())->not->toContain('can:templates.manage')
        ->and(app(CrmModule::class)->permissions()['web'])->toHaveKey('crm.view')
        ->and(app(MessageTemplatesModule::class)->permissions()['web'])->toHaveKey('templates.manage');
});

it('shows CRM and templates in the user sidebar definition', function (): void {
    $sidebar = file_get_contents(resource_path('views/components/layouts/partials/user-sidebar.blade.php'));

    expect($sidebar)->toContain("'label' => __('CRM')")
        ->and($sidebar)->toContain("'route' => 'user.crm.index'")
        ->and($sidebar)->toContain("'label' => __('Templates')")
        ->and($sidebar)->toContain("'route' => 'user.message-templates.index'");
});
