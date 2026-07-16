<?php

namespace App\Modules\SchedulerQueue\Tests\Feature;

use App\Models\Admin;
use App\Modules\PlansSubscriptions\Jobs\ExpireSubscriptionsJob;
use App\Modules\PlansSubscriptions\Jobs\SendSubscriptionExpiryReminderJob;
use App\Modules\SchedulerQueue\Models\SchedulerEntry;
use App\Modules\SchedulerQueue\Services\ManagedSchedulerService;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SchedulerQueueModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_queue_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('scheduler-queue');

        $this->assertNotNull($module);
        $this->assertTrue(Route::has('admin.scheduler-queues.index'));
    }

    public function test_view_permission_can_access_page_and_seeded_entries_render(): void
    {
        $admin = $this->adminWithPermissions(['scheduler-queues.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.scheduler-queues.index'))
            ->assertSuccessful()
            ->assertSee('Scheduler &amp; Queues', false)
            ->assertSee('Subscription Expiry Reminders')
            ->assertSee('Subscription Expiry Processing');
    }

    public function test_admin_without_permission_cannot_access_page(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.scheduler-queues.index'))
            ->assertForbidden();
    }

    public function test_edit_requires_edit_permission(): void
    {
        $entry = $this->syncAndEntry('subscription-expiry-reminders');
        $viewer = $this->adminWithPermissions(['scheduler-queues.view']);

        $this->actingAs($viewer, 'admin')
            ->put(route('admin.scheduler-queues.update', $entry), [
                'frequency' => 'daily',
                'queue' => 'default',
                'enabled' => '1',
            ])
            ->assertForbidden();

        $editor = $this->adminWithPermissions(['scheduler-queues.view', 'scheduler-queues.edit']);

        $this->actingAs($editor, 'admin')
            ->put(route('admin.scheduler-queues.update', $entry), [
                'frequency' => 'daily',
                'queue' => 'default',
                'enabled' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('scheduler_entries', [
            'key' => 'subscription-expiry-reminders',
            'frequency' => 'daily',
            'enabled' => false,
        ]);
    }

    public function test_run_now_requires_run_permission_and_dispatches_approved_job(): void
    {
        Bus::fake();
        $entry = $this->syncAndEntry('subscription-expiry-processing');
        $viewer = $this->adminWithPermissions(['scheduler-queues.view']);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.scheduler-queues.run', $entry))
            ->assertForbidden();

        $runner = $this->adminWithPermissions(['scheduler-queues.view', 'scheduler-queues.run']);

        $this->actingAs($runner, 'admin')
            ->post(route('admin.scheduler-queues.run', $entry))
            ->assertRedirect();

        Bus::assertDispatched(ExpireSubscriptionsJob::class);
        $this->assertSame('success', $entry->fresh()->last_status);
    }

    public function test_generic_scheduler_dispatches_due_enabled_entries_and_skips_disabled_entries(): void
    {
        Bus::fake();
        $enabled = $this->syncAndEntry('subscription-expiry-reminders');
        $disabled = $this->syncAndEntry('subscription-expiry-processing');
        $disabled->update(['enabled' => false]);

        app(ManagedSchedulerService::class)->runDue();

        Bus::assertDispatched(SendSubscriptionExpiryReminderJob::class);
        Bus::assertNotDispatched(ExpireSubscriptionsJob::class);
        $this->assertSame('success', $enabled->fresh()->last_status);
        $this->assertNull($disabled->fresh()->last_status);
    }

    public function test_unregistered_scheduler_entry_is_rejected(): void
    {
        $entry = SchedulerEntry::query()->create([
            'key' => 'unsafe-entry',
            'label' => 'Unsafe Entry',
            'type' => 'job',
            'target' => 'App\\Unsafe\\Job',
            'frequency' => 'hourly',
            'queue' => 'default',
            'enabled' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ManagedSchedulerService::class)->run($entry);
    }

    public function test_queue_tables_render_pending_and_failed_jobs(): void
    {
        $admin = $this->adminWithPermissions(['scheduler-queues.view']);

        $this->seedQueueRows();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.scheduler-queues.index', ['tab' => 'pending']))
            ->assertSuccessful()
            ->assertSee('ExamplePendingJob')
            ->assertSee('ExampleFailedJob');
    }

    public function test_queue_manage_actions_require_manage_permission(): void
    {
        $viewer = $this->adminWithPermissions(['scheduler-queues.view']);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.scheduler-queues.workers.restart'))
            ->assertForbidden();

        Artisan::spy();
        $manager = $this->adminWithPermissions(['scheduler-queues.view', 'scheduler-queues.manage']);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.scheduler-queues.workers.restart'))
            ->assertRedirect();

        Artisan::shouldHaveReceived('call')->with('queue:restart')->once();
    }

    public function test_queue_artisan_actions_call_expected_commands(): void
    {
        Artisan::spy();
        $manager = $this->adminWithPermissions(['scheduler-queues.view', 'scheduler-queues.manage']);
        $this->seedQueueRows();

        $this->actingAs($manager, 'admin')->post(route('admin.scheduler-queues.failed.retry', 1))->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('queue:retry', ['id' => ['1']])->once();

        $this->actingAs($manager, 'admin')->post(route('admin.scheduler-queues.failed.retry-all'))->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('queue:retry', ['id' => ['all']])->once();

        $this->actingAs($manager, 'admin')->post(route('admin.scheduler-queues.failed.forget', 1))->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('queue:forget', ['id' => '1'])->once();

        $this->actingAs($manager, 'admin')->post(route('admin.scheduler-queues.failed.flush'))->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('queue:flush')->once();

        $this->actingAs($manager, 'admin')->post(route('admin.scheduler-queues.pending.clear'), ['queue' => 'default'])->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('queue:clear', [
            'connection' => 'database',
            '--queue' => 'default',
            '--force' => true,
        ])->once();
    }

    protected function adminWithPermissions(array $permissions): Admin
    {
        $admin = Admin::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);

        return $admin;
    }

    protected function syncAndEntry(string $key): SchedulerEntry
    {
        app(ManagedSchedulerService::class)->syncRegisteredEntries();

        return SchedulerEntry::query()->where('key', $key)->firstOrFail();
    }

    protected function seedQueueRows(): void
    {
        $payload = json_encode([
            'displayName' => 'ExamplePendingJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['commandName' => 'App\\Jobs\\ExamplePendingJob'],
        ]);
        $failedPayload = json_encode([
            'displayName' => 'ExampleFailedJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['commandName' => 'App\\Jobs\\ExampleFailedJob'],
        ]);

        $this->assertIsString($payload);
        $this->assertIsString($failedPayload);

        \DB::table('jobs')->insert([
            'id' => 1,
            'queue' => 'default',
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        \DB::table('failed_jobs')->insert([
            'id' => 1,
            'uuid' => (string) \Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $failedPayload,
            'exception' => 'Example exception preview',
            'failed_at' => now(),
        ]);

    }
}
