<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders the send notification page for admins with access', function () {
    $admin = createNotificationTestAdmin();

    $response = $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.notification-send.create'));

    $response
        ->assertSuccessful()
        ->assertSee('Send Notification')
        ->assertSee('Available Shortcodes');
});

it('queues custom email notifications for active recipients with email addresses', function () {
    Queue::fake();

    $admin = createNotificationTestAdmin();
    $firstUser = User::factory()->create([
        'name' => 'First Recipient',
        'email' => 'first@example.com',
        'is_active' => true,
    ]);
    $secondUser = User::factory()->create([
        'name' => 'Second Recipient',
        'email' => 'second@example.com',
        'is_active' => true,
    ]);
    User::factory()->create([
        'name' => 'Inactive Recipient',
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);
    User::factory()->create([
        'name' => 'No Email Recipient',
        'email' => '',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.notification-send.store'), [
            'channel' => 'email',
            'recipient_type' => 'all_users',
            'title' => 'Hello {{name}}',
            'message' => 'Email for {{email}}',
        ]);

    $response
        ->assertRedirect(route('admin.notification-send.create'))
        ->assertSessionHas('success');

    Queue::assertPushed(SendChannelNotificationJob::class, 2);

    $logs = NotificationLog::query()
        ->where('channel', 'email')
        ->orderBy('id')
        ->get();

    $recipientAddresses = $logs->map(fn (NotificationLog $log) => $log->metadata['recipient_address'] ?? null)->all();
    $firstRecipientLog = $logs->first(fn (NotificationLog $log) => ($log->metadata['recipient_address'] ?? null) === $firstUser->email);

    expect($logs)->toHaveCount(2);
    expect($recipientAddresses)->not()->toContain('inactive@example.com');
    expect($recipientAddresses)->toContain($firstUser->email, $secondUser->email);
    expect($firstRecipientLog?->metadata['subject'])->toBe('Hello First Recipient');
    expect($firstRecipientLog?->metadata['body'])->toBe('Email for first@example.com');
});

it('queues sms notifications from a selected template with merged variables', function () {
    Queue::fake();

    Setting::query()->create([
        'key' => 'enable_sms_notifications',
        'value' => '1',
    ]);
    app(SettingsService::class)->clearCache();

    $admin = createNotificationTestAdmin();
    $recipient = User::factory()->create([
        'name' => 'Template Recipient',
        'email' => 'template@example.com',
        'phone' => '+15555550123',
        'is_active' => true,
    ]);

    $template = NotificationTemplate::query()->create([
        'slug' => 'broadcast-sms',
        'name' => 'Broadcast SMS',
        'description' => 'Template for SMS broadcasts',
        'sms_body' => 'Hi {{name}}, your code is {{code}}.',
        'channels' => ['sms'],
        'variables' => ['code' => 'One-time code'],
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.notification-send.store'), [
            'channel' => 'sms',
            'template_id' => $template->id,
            'recipient_type' => 'all_users',
            'template_variables' => [
                'code' => 'ABC-123',
            ],
        ]);

    $response
        ->assertRedirect(route('admin.notification-send.create'))
        ->assertSessionHas('success');

    Queue::assertPushed(SendChannelNotificationJob::class, 1);

    $log = NotificationLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->template_slug)->toBe('broadcast-sms');
    expect($log->metadata['recipient_address'])->toBe($recipient->phone);
    expect($log->metadata['body'])->toBe('Hi Template Recipient, your code is ABC-123.');
});

function createNotificationTestAdmin(): Admin
{
    $role = Role::findOrCreate('super-admin', 'admin');

    $admin = Admin::query()->create([
        'name' => 'Notification Admin',
        'email' => 'notification-admin@example.com',
        'password' => 'password',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $admin->assignRole($role);

    return $admin;
}
