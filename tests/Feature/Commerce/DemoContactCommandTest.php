<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the demo contact once in the store workspace', function (): void {
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());

    $this->artisan('commerce:demo-contact', ['phone' => '+8801876150613'])->assertSuccessful();
    $this->artisan('commerce:demo-contact', ['phone' => '+8801876150613'])->assertSuccessful();

    expect(Contact::query()->where('workspace_id', $workspace->id)->where('phone', '+8801876150613')->count())->toBe(1);
});

it('rejects a phone that is not in international format', function (): void {
    $this->artisan('commerce:demo-contact', ['phone' => '01876150613'])->assertFailed();
});
