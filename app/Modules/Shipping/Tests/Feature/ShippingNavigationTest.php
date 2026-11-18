<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
});

function shippingMenu(User $user): array
{
    $commerce = collect(app(WorkspaceNavigationRegistry::class)->forUser($user)['modules'])->firstWhere('id', 'commerce');

    return collect($commerce['groups'] ?? [])
        ->filter(fn (array $group): bool => str_starts_with($group['label'], 'Shipping'))
        ->mapWithKeys(fn (array $group): array => [$group['label'] => array_column($group['items'], 'label')])
        ->all();
}

it('groups the shipping pages into a short menu', function () {
    expect(shippingMenu($this->owner))->toBe([
        'Shipping' => ['Shipping Overview', 'Shipments', 'Rates & Quotes', 'Bills & Payments', 'Profit & Loss'],
        'Shipping Setup' => ['Couriers & Agents', 'Storefront Shipping', 'Shipping Audit Log'],
    ]);
});

it('joins related pages with tabs and hides the tabs a role cannot open', function () {
    $this->actingAs($this->owner)->get(route('user.shipping.payments.index'))
        ->assertOk()
        ->assertSee(route('user.shipping.invoices.index'), false)
        ->assertSee(route('user.shipping.billing.index'), false);
    $this->actingAs($this->owner)->get(route('user.shipping.methods.index'))
        ->assertOk()
        ->assertSee(route('user.shipping.settings'), false);
    $this->actingAs($this->owner)->get(route('user.shipping.agents.index'))
        ->assertOk()
        ->assertSee(route('user.shipping.carriers.index'), false);

    $sales = User::factory()->create();
    $this->workspace->members()->attach($sales->id, [
        'workspace_role_id' => WorkspaceRole::query()->where('workspace_id', $this->workspace->id)->where('name', 'Sales Team')->value('id'),
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    expect(shippingMenu($sales))->toBe(['Shipping' => ['Shipping Overview', 'Shipments', 'Rates & Quotes']]);
    $this->actingAs($sales)->get(route('user.shipping.calculator'))
        ->assertOk()
        ->assertSee(route('user.shipping.remote-areas.index'), false)
        ->assertDontSee(route('user.shipping.comparison'), false);
});
