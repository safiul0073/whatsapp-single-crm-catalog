<?php

use App\Models\User;
use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the import history page and keeps the json endpoint', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    ContactImport::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'file_name' => 'leads-november.csv',
        'file_path' => 'imports/leads-november.csv',
        'status' => ContactImportStatus::cases()[0],
        'created_rows' => 12,
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.imports.history'))
        ->assertOk()
        ->assertViewIs('contacts::user.imports')
        ->assertSee('Import History')
        ->assertSee('leads-november.csv');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.imports.history'))
        ->assertOk()
        ->assertJsonPath('0.file_name', 'leads-november.csv');
});
