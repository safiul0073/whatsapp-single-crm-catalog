<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('displays the configured application version in admin settings pages', function (): void {
    config([
        'app.env' => 'production',
        'app.version' => '2.5.0',
    ]);

    $admin = Admin::factory()->create();
    Permission::findOrCreate('settings.view', 'admin');
    $admin->givePermissionTo('settings.view');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('production v2.5.0');
});
