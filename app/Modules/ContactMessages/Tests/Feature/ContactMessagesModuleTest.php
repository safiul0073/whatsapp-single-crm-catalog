<?php

namespace App\Modules\ContactMessages\Tests\Feature;

use App\Modules\ContactMessages\Models\ContactMessage;
use App\Modules\ContactMessages\Models\ContactMessageReply;
use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function contactMessagePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'company' => 'Analytical Engines Ltd',
        'interest' => 'Demo',
        'message' => 'Please contact me about a product demo.',
    ], $overrides);
}

it('registers the contact messages module and routes', function (): void {
    $module = app(ModuleRegistry::class)->find('ContactMessages');

    expect($module)->not->toBeNull();
    expect(Route::has('contact.submit'))->toBeTrue();
    expect(Route::has('admin.contact-messages.index'))->toBeTrue();
    expect(Route::has('admin.contact-messages.show'))->toBeTrue();
    expect(Route::has('admin.contact-messages.reply'))->toBeTrue();
    expect(Route::has('admin.contact-messages.subscribe-newsletter'))->toBeTrue();
    expect(Route::has('admin.contact-messages.update-status'))->toBeTrue();
    expect(Route::has('admin.contact-messages.destroy'))->toBeTrue();
});

it('stores frontend contact form submissions', function (): void {
    $this->from(route('contact'))
        ->post(route('contact.submit'), contactMessagePayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('contact_success');

    $message = ContactMessage::query()->first();

    expect($message)->not->toBeNull();
    expect($message->full_name)->toBe('Ada Lovelace');
    expect($message->email)->toBe('ada@example.com');
    expect($message->status)->toBe(ContactMessage::STATUS_NEW);
});

it('stores json contact form submissions', function (): void {
    $this->postJson(route('contact.submit'), contactMessagePayload([
        'email' => 'json@example.com',
    ]))
        ->assertSuccessful()
        ->assertJson(['success' => true]);

    expect(ContactMessage::query()->where('email', 'json@example.com')->exists())->toBeTrue();
});

it('lets admins list and view contact messages', function (): void {
    $admin = createAdminUser();
    $message = ContactMessage::factory()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->get(route('admin.contact-messages.index'))
        ->assertSuccessful()
        ->assertViewHas('messages')
        ->assertSee('Grace Hopper');

    $this->withViewErrors([]);

    $this->get(route('admin.contact-messages.show', $message))
        ->assertSuccessful()
        ->assertSee('grace@example.com');

    expect($message->refresh()->status)->toBe(ContactMessage::STATUS_READ);
    expect($message->read_at)->not->toBeNull();
});

it('lets admins update status and delete contact messages', function (): void {
    $admin = createAdminUser();
    $message = ContactMessage::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.contact-messages.update-status', $message), [
        'status' => ContactMessage::STATUS_ARCHIVED,
    ])
        ->assertRedirect();

    expect($message->refresh()->status)->toBe(ContactMessage::STATUS_ARCHIVED);

    $this->delete(route('admin.contact-messages.destroy', $message))
        ->assertRedirect(route('admin.contact-messages.index'));

    expect(ContactMessage::query()->find($message->id))->toBeNull();
});

it('lets admins send a custom email reply to a contact message', function (): void {
    Queue::fake();

    $admin = createAdminUser();
    $message = ContactMessage::factory()->create([
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'email' => 'katherine@example.com',
        'interest' => 'Demo',
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.contact-messages.reply', $message), [
        'reply_type' => 'custom',
        'subject' => 'Re: Your WaPro enquiry',
        'body' => 'Hi {{first_name}}, we can help with {{interest}}.',
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reply = ContactMessageReply::query()->first();
    $log = NotificationLog::query()->first();

    expect($reply)->not->toBeNull();
    expect($reply->contact_message_id)->toBe($message->id);
    expect($reply->admin_id)->toBe($admin->id);
    expect($reply->source)->toBe('custom');
    expect($reply->subject)->toBe('Re: Your WaPro enquiry');
    expect($reply->body)->toBe('Hi Katherine, we can help with Demo.');
    expect($log)->not->toBeNull();
    expect($log->metadata['recipient_address'])->toBe('katherine@example.com');

    Queue::assertPushed(SendChannelNotificationJob::class, 1);
});

it('lets admins add a contact message email to newsletter subscribers', function (): void {
    $admin = createAdminUser();
    $message = ContactMessage::factory()->create([
        'email' => 'newsletter-contact@example.com',
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.contact-messages.subscribe-newsletter', $message))
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscriber = Subscriber::query()->where('email', 'newsletter-contact@example.com')->first();

    expect($subscriber)->not->toBeNull();
    expect($subscriber->active)->toBeTrue();

    $subscriber->update(['active' => false]);

    $this->post(route('admin.contact-messages.subscribe-newsletter', $message))
        ->assertRedirect();

    expect($subscriber->refresh()->active)->toBeTrue();
});

it('lets admins send an email reply using a notification template', function (): void {
    Queue::fake();

    $admin = createAdminUser();
    $message = ContactMessage::factory()->create([
        'first_name' => 'Dorothy',
        'last_name' => 'Vaughan',
        'email' => 'dorothy@example.com',
        'company' => 'NACA',
        'interest' => 'Pricing',
    ]);
    $template = NotificationTemplate::query()->create([
        'slug' => 'contact-reply',
        'name' => 'Contact Reply',
        'description' => 'Reply to public contact form enquiries',
        'email_subject' => 'Hello {{first_name}} from {{site_name}}',
        'email_body' => '<p>Hi {{first_name}}, {{answer}}</p><p>Company: {{company}}</p>',
        'channels' => ['email'],
        'variables' => ['answer' => 'Reply body'],
        'is_active' => true,
    ]);

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.contact-messages.reply', $message), [
        'reply_type' => 'template',
        'template_id' => $template->id,
        'template_variables' => [
            'answer' => 'pricing details are on the way',
        ],
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reply = ContactMessageReply::query()->first();

    expect($reply)->not->toBeNull();
    expect($reply->source)->toBe('template');
    expect($reply->template_slug)->toBe('contact-reply');
    expect($reply->subject)->toContain('Dorothy');
    expect($reply->body)->toContain('pricing details are on the way');
    expect($reply->body)->toContain('NACA');

    Queue::assertPushed(SendChannelNotificationJob::class, 1);
});
