<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\PosService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('POS demos are available only in local or testing environments.');

            return;
        }
        $workspaceId = config('commerce-demo.workspace_id');
        if (! $workspaceId && app()->environment('testing')) {
            $user = User::query()->where('email', 'pos-demo@example.test')->first() ?? User::factory()->create(['first_name' => 'POS', 'last_name' => 'Demo', 'email' => 'pos-demo@example.test']);
            $workspace = app(WorkspaceResolver::class)->current($user);
            $workspace->update(['settings' => array_replace($workspace->settings ?? [], ['onboarding_completed_at' => now()->toIso8601String()])]);
            $role = Role::findOrCreate('POS demo manager', 'web');
            $role->givePermissionTo([Permission::findOrCreate('commerce.view', 'web'), Permission::findOrCreate('commerce.manage', 'web')]);
            $user->assignRole($role);
        } elseif ($workspaceId) {
            $workspace = Workspace::query()->with('owner')->findOrFail($workspaceId);
            $user = $workspace->owner;
        } else {
            $this->command?->warn('Set COMMERCE_DEMO_WORKSPACE_ID to an existing demo workspace before seeding POS data.');

            return;
        }
        $submission = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'commerce-pos-demo:'.$workspace->id);
        if (Order::query()->where('workspace_id', $workspace->id)->where('submission_reference', $submission)->exists()) {
            return;
        }
        $product = Product::query()->firstOrCreate(['workspace_id' => $workspace->id, 'slug' => 'pos-demo-shirt'], ['name' => 'POS demo shirt', 'sku' => 'POS-DEMO-'.$workspace->id, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 10, 'wholesale_price' => 6, 'ws_enabled' => true, 'ws_main_moq' => 6, 'ws_color_moq' => 6, 'ws_min_sizes' => 3, 'ws_ratio_multiplier' => 1]);
        $color = $product->colors()->firstOrCreate(['name' => 'Blue'], ['workspace_id' => $workspace->id, 'hex_code' => '#2563eb']);
        $product->update(['ws_size_ratios' => [$color->id => ['S' => 2, 'M' => 2, 'L' => 2]]]);
        foreach (['S', 'M', 'L'] as $size) {
            $product->variants()->firstOrCreate(['sku' => 'POS-DEMO-'.$workspace->id.'-'.$size], ['workspace_id' => $workspace->id, 'color_id' => $color->id, 'size' => $size, 'meta_retailer_id' => 'POS-DEMO-'.$workspace->id.'-'.$size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']);
        }
        app(PosService::class)->checkout($workspace, [
            'submission_reference' => $submission, 'fulfillment_type' => 'pickup', 'walk_in' => false, 'handover' => false,
            'customer' => ['name' => 'POS demo customer', 'phone' => '+15555550199'],
            'groups' => [['product_id' => $product->id, 'mode' => 'wholesale', 'color_id' => $color->id, 'box_count' => 2]],
            'payment' => ['submission_reference' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'commerce-pos-demo-payment:'.$workspace->id), 'amount' => '5', 'currency' => StoreOrderSetting::forWorkspace($workspace->id)->currency, 'method' => 'cash'],
        ], $user);
        $this->command?->info('POS demo product and deposit sale created in the demo workspace.');
    }
}
