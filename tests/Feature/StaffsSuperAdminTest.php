<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'admin');
    $this->superAdmin = createAdminUser();
    $this->superAdmin->assignRole('super-admin');
    $this->withoutMiddleware([PermissionMiddleware::class, EnsureTwoFactorAuthenticated::class])->actingAs($this->superAdmin, 'admin');
});

it('lists the super-admin on the staff index', function (): void {
    $this->get(route('admin.staffs.index'))
        ->assertSuccessful()
        ->assertViewHas('staffs', fn ($staffs): bool => $staffs->contains('id', $this->superAdmin->id));
});

it('lets the super-admin be edited while keeping the role', function (): void {
    $this->get(route('admin.staffs.edit', $this->superAdmin))->assertSuccessful();

    $this->put(route('admin.staffs.update', $this->superAdmin), [
        'name' => 'Renamed Admin',
        'email' => $this->superAdmin->email,
        'is_active' => true,
        'roles' => ['super-admin'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.staffs.index'));

    $fresh = $this->superAdmin->fresh();
    expect($fresh->name)->toBe('Renamed Admin')->and($fresh->hasRole('super-admin'))->toBeTrue();
});

it('refuses to remove the last active super-admin', function (): void {
    $other = createAdminUser();
    $this->actingAs($other, 'admin');

    $this->delete(route('admin.staffs.destroy', $this->superAdmin))->assertSessionHas('error');
    $this->put(route('admin.staffs.update', $this->superAdmin), [
        'name' => 'X', 'email' => $this->superAdmin->email, 'is_active' => true, 'roles' => [],
    ])->assertSessionHas('error');

    expect(Admin::query()->find($this->superAdmin->id))->not->toBeNull()
        ->and($this->superAdmin->fresh()->hasRole('super-admin'))->toBeTrue();
});
