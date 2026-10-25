<?php

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\KnowledgeBases\Jobs\IndexKnowledgeBaseSourceJob;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Services\DefaultKnowledgeBaseService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function defaultKbWorkspace(): Workspace
{
    return app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
}

beforeEach(fn () => config(['knowledge-bases.auto_provision' => true]));

it('creates the default knowledge base, chatbot and widget when a workspace is created', function (): void {
    Queue::fake();

    $workspace = defaultKbWorkspace();

    $knowledgeBase = KnowledgeBase::query()->where('workspace_id', $workspace->id)->firstOrFail();
    $chatbot = Chatbot::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($knowledgeBase->name)->toBe('Store Help')
        ->and($knowledgeBase->sources()->count())->toBe(4)
        ->and($chatbot->is_active)->toBeTrue()
        ->and($chatbot->knowledgeBases->pluck('id')->all())->toBe([$knowledgeBase->id])
        ->and(ChatbotWidget::query()->where('chatbot_id', $chatbot->id)->where('is_active', true)->exists())->toBeTrue();

    Queue::assertPushed(IndexKnowledgeBaseSourceJob::class, 4);
});

it('is idempotent and only re-indexes sources whose text changed', function (): void {
    Queue::fake();

    $workspace = defaultKbWorkspace();
    Queue::fake();

    app(DefaultKnowledgeBaseService::class)->provision($workspace);

    expect(KnowledgeBase::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and(Chatbot::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and(ChatbotWidget::query()->where('workspace_id', $workspace->id)->count())->toBe(1);

    Queue::assertNothingPushed();
});

it('never puts payment recipient details in the knowledge base', function (): void {
    Queue::fake();

    $workspace = defaultKbWorkspace();
    $workspace->update(['settings' => ['commerce' => ['payment_methods' => [[
        'id' => 'bank',
        'name' => 'Bank Transfer',
        'recipient_details' => 'Account Number: 99887766',
        'instructions' => 'Send the order total.',
        'active' => '1',
        'sort_order' => 0,
    ]]]]]);

    $knowledgeBase = app(DefaultKnowledgeBaseService::class)->refresh($workspace);
    $payment = $knowledgeBase->sources()->where('metadata->managed_key', 'payment')->firstOrFail();

    expect($payment->content)->toContain('Bank Transfer')
        ->toContain('Send the order total.')
        ->not->toContain('99887766');
});

it('adds question and answer sources with an automatic title', function (): void {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $knowledgeBase = app(DefaultKnowledgeBaseService::class)->find($workspace->id);

    $source = app(KnowledgeBaseService::class)->addSource($user, $knowledgeBase, [
        'type' => 'faq',
        'faq' => [['question' => 'Do you ship abroad?', 'answer' => 'Yes, worldwide.']],
    ]);

    expect($source->title)->toBe('Questions and answers')
        ->and($source->content)->toBe("Q: Do you ship abroad?\nA: Yes, worldwide.");
});
