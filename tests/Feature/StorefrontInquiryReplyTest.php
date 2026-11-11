<?php

use App\Models\User;
use App\Modules\Commerce\Mail\InquiryReplyMail;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'storefront.platform_secret' => str_repeat('p', 40),
        'storefront.preview_secret' => str_repeat('v', 40),
        'cache.default' => 'array',
        'mail.from.address' => 'noreply@platform.test',
    ]);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Schema::dropIfExists('users');
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    (require app_path('Modules/SystemNotifications/Database/Migrations/2026_03_29_000001_create_system_notifications_table.php'))->up();
    Queue::fake();
    Mail::fake();
    Gate::before(fn ($user, $ability) => true);

    $this->owner = User::query()->create([
        'name' => 'Store Owner',
        'email' => 'owner@shop.test',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $this->workspace = Workspace::query()->create([
        'name' => 'Demo Shop',
        'slug' => 'demo-'.Str::uuid(),
        'owner_id' => $this->owner->id,
        'status' => 'active',
    ]);
    WorkspaceMember::query()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'role' => 'owner',
        'is_active' => true,
    ]);
});

function makeInquiry(int $workspaceId, string $type = 'contact', array $data = []): StorefrontContent
{
    return StorefrontContent::query()->create([
        'workspace_id' => $workspaceId,
        'type' => $type,
        'slug' => $type.'-'.Str::uuid(),
        'data' => array_merge(['name' => 'Marcus', 'email' => 'marcus@buyer.test', 'subject' => 'Wholesale', 'message' => 'Need 200 tees.'], $data),
        'published_at' => now(),
    ]);
}

it('sends the reply from the dashboard with the owner email as reply-to', function (): void {
    $inquiry = makeInquiry($this->workspace->id);

    $this->actingAs($this->owner)
        ->post(route('user.commerce.storefront.contents.reply', $inquiry->id), [
            'subject' => 'Re: Wholesale',
            'message' => 'Yes, we can do that.',
        ])
        ->assertRedirect(route('user.commerce.storefront.index').'?tab=inquiries#inquiries');

    Mail::assertQueued(InquiryReplyMail::class, function (InquiryReplyMail $mail): bool {
        return $mail->hasTo('marcus@buyer.test')
            && $mail->hasReplyTo('owner@shop.test')
            && $mail->hasFrom('noreply@platform.test')
            && $mail->replyMessage === 'Yes, we can do that.';
    });

    $data = $inquiry->fresh()->data;
    expect($data['replied_at'])->not->toBeEmpty()
        ->and($data['reply_message'])->toBe('Yes, we can do that.');
});

it('rejects replies to other workspaces, subscribers and inquiries without email', function (): void {
    $other = Workspace::query()->create(['name' => 'Other', 'slug' => 'o-'.Str::uuid(), 'owner_id' => $this->owner->id, 'status' => 'active']);
    $payload = ['subject' => 'Hi', 'message' => 'Hello'];

    $this->actingAs($this->owner)->post(route('user.commerce.storefront.contents.reply', makeInquiry($other->id)->id), $payload)->assertNotFound();
    $this->actingAs($this->owner)->post(route('user.commerce.storefront.contents.reply', makeInquiry($this->workspace->id, 'subscriber')->id), $payload)->assertNotFound();
    $this->actingAs($this->owner)->post(route('user.commerce.storefront.contents.reply', makeInquiry($this->workspace->id, 'contact', ['email' => ''])->id), $payload)->assertStatus(422);

    Mail::assertNothingQueued();
});

it('validates the reply subject and message', function (): void {
    $inquiry = makeInquiry($this->workspace->id);

    $this->actingAs($this->owner)
        ->post(route('user.commerce.storefront.contents.reply', $inquiry->id), ['subject' => '', 'message' => ''])
        ->assertSessionHasErrors(['subject', 'message']);

    Mail::assertNothingQueued();
});

it('uses an in-app reply form instead of a mailto link', function (): void {
    $view = file_get_contents(base_path('app/Modules/Commerce/Resources/views/user/storefront-inquiries.blade.php'));

    expect($view)->toContain("route('user.commerce.storefront.contents.reply'")
        ->and($view)->toContain('Send Reply')
        ->and($view)->not->toContain('href="mailto:{{ $email }}?subject=');
});
