<?php

use App\Models\User;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Schema::dropIfExists('users');
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    Queue::fake();
    Gate::before(fn ($user, $ability) => true);
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::query()->create(['name' => 'Store', 'slug' => 'test-store', 'owner_id' => $this->owner->id, 'status' => 'active']);
    WorkspaceMember::query()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'is_active' => true]);
    $this->actingAs($this->owner);
    $this->channels = array_replace_recursive(CmsService::CHAT_CHANNEL_DEFAULTS, ['enabled' => true, 'whatsapp' => ['enabled' => true, 'phone' => '8801769017621']]);
});

it('saves channels without replacing theme or deployment and queues revalidation', function (): void {
    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->workspace->id], ['config' => ['name' => 'Original', 'deployment' => ['session_secret' => 'private']]]);
    $this->put('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertRedirect()->assertSessionHasNoErrors();
    $saved = StorefrontSetting::query()->first()->config;
    expect($saved['name'])->toBe('Original')->and($saved['deployment']['session_secret'])->toBe('private')->and($saved['chat_channels']['whatsapp']['enabled'])->toBeTrue();
    Queue::assertPushed(RevalidateStorefront::class, fn ($job) => $job->workspace === $this->workspace->id && $job->tags === ['all']);
    $public = app(CmsService::class)->settings($this->workspace->id);
    expect($public)->not->toHaveKey('deployment')->and($public['chat_channels']['whatsapp']['phone'])->toBe('8801769017621');
    $this->put('/dashboard/commerce/storefront/settings', ['config' => ['name' => 'Changed', 'primary' => '#123456', 'hover' => '#123456', 'footer_color' => '#123456', 'chat_channels' => ['enabled' => false]]])->assertSessionHasNoErrors();
    expect(StorefrontSetting::query()->first()->config['chat_channels']['enabled'])->toBeTrue();
});

it('rejects invalid channel values', function (string $field, mixed $value): void {
    data_set($this->channels, $field, $value);
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertUnprocessable()->assertJsonValidationErrors('chat_channels.'.$field);
})->with([
    ['whatsapp.phone', 'invalid-phone'],
    ['whatsapp.phone', ''],
    ['position', 'top-right'],
    ['layout', 'unknown'],
    ['messenger.page_id', '../evil'],
    ['telegram.username', 'bad/name'],
]);

it('normalizes common formatting in channel identifiers', function (): void {
    $this->channels['whatsapp']['phone'] = '+880 1769-017621';
    $this->channels['telegram']['username'] = '@store_support';
    $this->channels['messenger']['page_id'] = 'https://m.me/store.support';
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertRedirect()->assertSessionHasNoErrors();
    $saved = StorefrontSetting::query()->first()->config['chat_channels'];
    expect($saved['whatsapp']['phone'])->toBe('8801769017621')
        ->and($saved['telegram']['username'])->toBe('store_support')
        ->and($saved['messenger']['page_id'])->toBe('store.support');
});

it('allows only active widgets in the current workspace and suppresses deactivated widgets', function (): void {
    $widget = ChatbotWidget::query()->create(['workspace_id' => $this->workspace->id, 'chatbot_id' => 1, 'name' => 'Help', 'public_token' => 'test-token', 'is_active' => true]);
    $this->channels['custom_chatbot']['enabled'] = true;
    $this->channels['custom_chatbot']['widget_token'] = 'test-token';
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertRedirect();
    $bot = app(CmsService::class)->settings($this->workspace->id)['chat_channels']['custom_chatbot'];
    expect($bot['script_url'])->toBe(route('widgets.chatbot.loader', 'test-token'));
    $widget->update(['workspace_id' => $this->workspace->id + 1]);
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertUnprocessable()->assertJsonValidationErrors('chat_channels.custom_chatbot.widget_token');
    $widget->update(['workspace_id' => $this->workspace->id, 'is_active' => false]);
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertUnprocessable();
    expect(app(CmsService::class)->settings($this->workspace->id)['chat_channels']['custom_chatbot']['enabled'])->toBeFalse();
});

it('rejects executable URLs and missing custom source values', function (string $source, string $field, string $value): void {
    $this->channels['custom_chatbot'] = array_replace($this->channels['custom_chatbot'], ['enabled' => true, 'source' => $source, $field => $value]);
    $this->putJson('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertUnprocessable()->assertJsonValidationErrors('chat_channels.custom_chatbot.'.$field);
})->with([
    ['direct_link', 'direct_url', 'javascript:alert(1)'],
    ['custom_script', 'script_url', '<script src="https://example.com/chat.js"></script>'],
    ['custom_script', 'script_url', ''],
]);

it('defaults to a disabled widget', function (): void {
    expect(app(CmsService::class)->settings($this->workspace->id)['chat_channels']['enabled'])->toBeFalse();
});

it('keeps the selected widget when temporarily disabling contact channels', function (): void {
    $this->channels['enabled'] = false;
    $this->channels['custom_chatbot']['enabled'] = true;
    $this->channels['custom_chatbot']['widget_token'] = 'saved-token';
    $this->put('/dashboard/commerce/storefront/chat-channels', ['chat_channels' => $this->channels])->assertSessionHasNoErrors();
    expect(StorefrontSetting::query()->first()->config['chat_channels']['custom_chatbot']['widget_token'])->toBe('saved-token');
});
