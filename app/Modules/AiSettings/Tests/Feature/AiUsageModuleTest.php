<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\AiSettings\Models\AiUsageLog;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Usage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function aiUsageAdminWithPermissions(array $permissions): Admin
{
    $admin = Admin::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'admin');
    }

    $admin->givePermissionTo($permissions);

    return $admin;
}

function aiUsageWorkspaceFor(User $user, string $name = 'AI Usage Workspace'): Workspace
{
    return Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => $name,
        'slug' => str()->slug($name.'-'.str()->random(6)),
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
        'settings' => [],
    ]);
}

it('allows permitted admins to view usage overview chart filters and logs', function (): void {
    $admin = aiUsageAdminWithPermissions(['ai-usage.view']);
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);
    $workspace = aiUsageWorkspaceFor($user, 'Acme Workspace');

    AiUsageLog::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'feature' => 'inbox_ai_reply',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => 'success',
        'duration_ms' => 340,
        'input_tokens' => 40,
        'output_tokens' => 25,
        'total_tokens' => 65,
        'estimated_cost' => '0.012300',
        'request_excerpt' => 'Draft a helpful reply.',
        'response_excerpt' => 'Thanks for reaching out.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    AiUsageLog::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'feature' => 'chatbot_reply',
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-20250514',
        'status' => 'failed',
        'duration_ms' => 910,
        'error_message' => 'Rate limit exceeded',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ai-usage.index', [
            'range' => 30,
            'provider' => 'openai',
            'status' => 'success',
            'workspace' => 'Acme',
        ]))
        ->assertSuccessful()
        ->assertSee('AI Usage')
        ->assertSee('Total Calls')
        ->assertSee('Successful')
        ->assertSee('Failed')
        ->assertSee('Estimated Cost')
        ->assertSee('ai-usage-chart')
        ->assertSee('Openai')
        ->assertSee('Inbox Ai Reply')
        ->assertSee('Acme Workspace')
        ->assertSee('ada@example.com');
});

it('blocks admins without the usage permission', function (): void {
    $admin = Admin::factory()->create();

    Permission::findOrCreate('ai-usage.view', 'admin');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ai-usage.index'))
        ->assertForbidden();
});

it('stores safe success and failure usage records', function (): void {
    $logger = app(AiUsageLogger::class);
    $user = User::factory()->create();
    $workspace = aiUsageWorkspaceFor($user);

    $logger->recordSuccess([
        'workspace' => $workspace,
        'user' => $user,
        'feature' => 'message_template_generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'duration_ms' => 120,
        'input_tokens' => 10,
        'output_tokens' => 20,
        'total_tokens' => 30,
        'estimated_cost' => '0.001200',
        'request' => str_repeat('Write promo copy with api_key=sk-test-secret. ', 40),
        'response' => 'Generated template body.',
        'metadata' => [
            'api_key' => 'sk-hidden',
            'nested' => ['token' => 'Bearer abc123', 'safe' => 'kept'],
        ],
    ]);

    $logger->recordFailure([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'feature' => 'chatbot_reply',
        'provider' => 'anthropic',
        'request' => 'Authorization: Bearer private-token',
    ], new RuntimeException('Provider failed with sk-live-private'));

    $success = AiUsageLog::query()->where('status', 'success')->firstOrFail();
    $failure = AiUsageLog::query()->where('status', 'failed')->firstOrFail();

    expect($success->workspace_id)->toBe($workspace->id)
        ->and($success->user_id)->toBe($user->id)
        ->and($success->total_tokens)->toBe(30)
        ->and($success->request_excerpt)->not->toContain('sk-test-secret')
        ->and($success->metadata)->not->toHaveKey('api_key')
        ->and(data_get($success->metadata, 'nested.token'))->toBeNull()
        ->and(data_get($success->metadata, 'nested.safe'))->toBe('kept')
        ->and($failure->error_message)->not->toContain('sk-live-private')
        ->and($failure->request_excerpt)->not->toContain('private-token');
});

it('records measured provider responses with usage metadata', function (): void {
    $logger = app(AiUsageLogger::class);

    $response = $logger->measure([
        'feature' => 'automation_flow_generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'request' => 'Build a welcome flow.',
    ], fn (): object => (object) [
        'text' => '{"name":"Welcome"}',
        'usage' => [
            'input_tokens' => 9,
            'output_tokens' => 7,
            'total_tokens' => 16,
        ],
    ]);

    expect($response->text)->toBe('{"name":"Welcome"}');

    $this->assertDatabaseHas('ai_usage_logs', [
        'feature' => 'automation_flow_generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => 'success',
        'input_tokens' => 9,
        'output_tokens' => 7,
        'total_tokens' => 16,
    ]);
});

it('records measured provider responses with Laravel Ai Usage object', function (): void {
    $logger = app(AiUsageLogger::class);

    $usageObject = new Usage(promptTokens: 12, completionTokens: 8);

    $response = $logger->measure([
        'feature' => 'automation_flow_generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'request' => 'Build a welcome flow.',
    ], fn (): object => (object) [
        'text' => '{"name":"Welcome"}',
        'usage' => $usageObject,
    ]);

    expect($response->text)->toBe('{"name":"Welcome"}');

    $this->assertDatabaseHas('ai_usage_logs', [
        'feature' => 'automation_flow_generation',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => 'success',
        'input_tokens' => 12,
        'output_tokens' => 8,
        'total_tokens' => 20,
    ]);
});
