<?php

use App\Modules\Newsletter\Database\Seeders\NewsletterModuleSeeder;
use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('registers the newsletter module and routes', function (): void {
    $module = app(ModuleRegistry::class)->find('newsletter');

    expect($module)->not->toBeNull();
    expect(Route::has('admin.subscribers.index'))->toBeTrue();
    expect(Route::has('admin.subscribers.destroy'))->toBeTrue();
    expect(Route::has('admin.subscribers.toggle-status'))->toBeTrue();
    expect(Route::has('admin.subscribers.send.create'))->toBeTrue();
    expect(Route::has('admin.subscribers.send.store'))->toBeTrue();
});

it('lists subscribers to authorized admins', function (): void {
    $admin = createAdminUser();
    Subscriber::factory()->count(5)->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->get(route('admin.subscribers.index'))
        ->assertSuccessful()
        ->assertViewHas('subscribers');
});

it('toggles subscriber status', function (): void {
    $admin = createAdminUser();
    $subscriber = Subscriber::factory()->create(['active' => true]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.subscribers.toggle-status', $subscriber))
        ->assertRedirect();

    $subscriber->refresh();
    expect($subscriber->active)->toBeFalse();

    $this->post(route('admin.subscribers.toggle-status', $subscriber))
        ->assertRedirect();

    $subscriber->refresh();
    expect($subscriber->active)->toBeTrue();
});

it('deletes subscribers', function (): void {
    $admin = createAdminUser();
    $subscriber = Subscriber::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertRedirect(route('admin.subscribers.index'));

    expect(Subscriber::query()->find($subscriber->id))->toBeNull();
});

it('bulk deletes subscribers', function (): void {
    $admin = createAdminUser();
    $subscribers = Subscriber::factory()->count(3)->create();
    $ids = $subscribers->pluck('id')->toArray();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.subscribers.bulk-delete'), ['ids' => $ids])
        ->assertSuccessful();

    expect(Subscriber::query()->whereIn('id', $ids)->count())->toBe(0);
});

it('seeds subscribers via seeder', function (): void {
    $this->seed(NewsletterModuleSeeder::class);

    expect(Subscriber::query()->count())->toBe(3);
    expect(Subscriber::query()->where('email', 'john.doe@example.com')->exists())->toBeTrue();
});

it('allows guest users to subscribe to newsletter', function (): void {
    $this->postJson(route('newsletter.subscribe'), [
        'email' => 'new-subscriber@example.com',
    ])
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    expect(Subscriber::query()->where('email', 'new-subscriber@example.com')->exists())->toBeTrue();
});

it('allows non-json newsletter subscriptions from the footer form', function (): void {
    $this->from('/')
        ->post(route('newsletter.subscribe'), [
            'email' => 'footer-subscriber@example.com',
        ])
        ->assertRedirect('/')
        ->assertSessionHas('newsletter_success');

    expect(Subscriber::query()->where('email', 'footer-subscriber@example.com')->exists())->toBeTrue();
});

it('renders the send newsletter page', function (): void {
    $admin = createAdminUser();
    Subscriber::factory()->count(2)->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->withViewErrors([])
        ->get(route('admin.subscribers.send.create'))
        ->assertSuccessful()
        ->assertSee('Send Newsletter')
        ->assertSee('Active Subscribers');
});

it('queues a custom newsletter for active subscribers only', function (): void {
    Queue::fake();

    $admin = createAdminUser();
    $active = Subscriber::factory()->create(['email' => 'ada.lovelace@example.com', 'active' => true]);
    Subscriber::factory()->create(['email' => 'inactive@example.com', 'active' => false]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.subscribers.send.store'), [
        'recipient_type' => 'active',
        'title' => 'Hello {{name}}',
        'message' => '<p>You are subscribed as <strong>{{email}}</strong>.</p>',
    ])
        ->assertRedirect(route('admin.subscribers.send.create'))
        ->assertSessionHas('success');

    Queue::assertPushed(SendChannelNotificationJob::class, 1);

    $log = NotificationLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->notifiable_type)->toBe($active->getMorphClass());
    expect($log->notifiable_id)->toBe($active->id);
    expect($log->metadata['recipient_address'])->toBe('ada.lovelace@example.com');
    expect($log->metadata['subject'])->toBe('Hello Ada Lovelace');
    expect($log->metadata['source'])->toBe('custom_html');
    expect($log->metadata['body'])->toBe('<p>You are subscribed as <strong>ada.lovelace@example.com</strong>.</p>');
});

it('queues a templated newsletter for a single subscriber', function (): void {
    Queue::fake();

    $admin = createAdminUser();
    $subscriber = Subscriber::factory()->create(['email' => 'single@example.com']);
    Subscriber::factory()->count(2)->create();

    $template = NotificationTemplate::query()->create([
        'slug' => 'newsletter-update',
        'name' => 'Newsletter Update',
        'description' => 'Newsletter email template',
        'email_subject' => 'Update for {{name}}',
        'email_body' => '<p>{{headline}} for {{email}}</p>',
        'channels' => ['email'],
        'variables' => ['headline' => 'Main headline'],
        'is_active' => true,
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.subscribers.send.store'), [
        'recipient_type' => 'single',
        'subscriber_id' => $subscriber->id,
        'template_id' => $template->id,
        'template_variables' => [
            'headline' => 'Summer launch',
        ],
    ])
        ->assertRedirect(route('admin.subscribers.send.create'))
        ->assertSessionHas('success');

    Queue::assertPushed(SendChannelNotificationJob::class, 1);

    $log = NotificationLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->template_slug)->toBe('newsletter-update');
    expect($log->metadata['recipient_address'])->toBe('single@example.com');
    expect($log->metadata['subject'])->toBe('Update for Single');
    expect($log->metadata['body'])->toBe('<p>Summer launch for single@example.com</p>');
});
