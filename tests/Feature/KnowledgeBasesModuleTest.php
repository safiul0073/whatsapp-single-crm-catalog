<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ClaudeReplyService;
use App\Modules\KnowledgeBases\Jobs\IndexKnowledgeBaseSourceJob;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseIndexingService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;

uses(RefreshDatabase::class);

function knowledgeBaseTestMiddleware(): array
{
    return [Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class];
}

it('lets a user create update and delete a knowledge base', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.store'), [
            'name' => 'Support Library',
            'description' => 'Answers for support questions.',
            'visibility' => 'workspace',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Knowledge base created.');

    $knowledgeBase = KnowledgeBase::query()->firstOrFail();

    expect($knowledgeBase)
        ->workspace_id->toBe(app(WorkspaceResolver::class)->current($user)->id)
        ->name->toBe('Support Library')
        ->status->toBe('ready');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->get(route('user.knowledge-bases.index'))
        ->assertOk()
        ->assertSee('Support Library')
        ->assertDontSee('Order Helper');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->put(route('user.knowledge-bases.update', $knowledgeBase), [
            'name' => 'Help Center Library',
            'description' => 'Updated support content.',
            'visibility' => 'workspace',
        ])
        ->assertRedirect(route('user.knowledge-bases.index'))
        ->assertSessionHas('status', 'Knowledge base updated.');

    expect($knowledgeBase->refresh())
        ->name->toBe('Help Center Library')
        ->description->toBe('Updated support content.');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->delete(route('user.knowledge-bases.destroy', $knowledgeBase))
        ->assertRedirect(route('user.knowledge-bases.index'))
        ->assertSessionHas('status', 'Knowledge base deleted.');

    expect(KnowledgeBase::query()->exists())->toBeFalse();
});

it('adds text and qa sources and indexes searchable chunks', function (): void {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Billing KB',
        'description' => 'Billing content',
        'settings' => [],
    ]);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'text',
            'title' => 'Plan policy',
            'content' => 'Premium plans include invoice exports and priority support.',
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase))
        ->assertSessionHas('status', 'Source queued for indexing.');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'qa',
            'title' => 'Refund answer',
            'question' => 'Can I get a refund?',
            'answer' => 'Refunds are available within 14 days of purchase.',
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    Queue::assertPushed(IndexKnowledgeBaseSourceJob::class, 2);
    KnowledgeBaseSource::query()->each(fn (KnowledgeBaseSource $source) => app(KnowledgeBaseIndexingService::class)->index($source));

    expect($knowledgeBase->refresh())
        ->sources_count->toBe(2)
        ->chunks_count->toBe(2)
        ->status->toBe('ready')
        ->last_indexed_at->not->toBeNull();

    expect(KnowledgeBaseSource::query()->pluck('status')->all())->toBe(['ready', 'ready'])
        ->and(KnowledgeBaseSource::query()->pluck('vector_status')->all())->toBe(['fallback', 'fallback'])
        ->and(KnowledgeBaseChunk::query()->pluck('content')->implode("\n"))
        ->toContain('invoice exports')
        ->toContain('Refunds are available within 14 days');
});

it('adds url sitemap docx and pdf sources', function (): void {
    Queue::fake();
    Http::fake([
        'https://example.com/help' => Http::response('<html><body>Enterprise onboarding includes migration support.</body></html>'),
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/shipping</loc></url></urlset>'),
        'https://example.com/shipping' => Http::response('<html><body>Shipping windows are three to five business days.</body></html>'),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Docs KB',
        'settings' => [],
    ]);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'url',
            'title' => 'Website help',
            'url' => 'https://example.com/help',
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'sitemap',
            'title' => 'Website sitemap',
            'url' => 'https://example.com/sitemap.xml',
            'crawl_limit' => 5,
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    $docxPath = tempnam(sys_get_temp_dir(), 'kb_docx_').'.docx';
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Uploaded DOCX files may include warranty and shipping instructions.');
    (new Word2007($phpWord))->save($docxPath);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'file',
            'title' => 'Uploaded DOCX policy',
            'file' => new UploadedFile($docxPath, 'policy.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    $pdfPath = tempnam(sys_get_temp_dir(), 'kb_pdf_').'.pdf';
    file_put_contents($pdfPath, '%PDF-1.4 queued upload acceptance');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'file',
            'title' => 'Queued PDF policy',
            'file' => new UploadedFile($pdfPath, 'policy.pdf', 'application/pdf', null, true),
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    KnowledgeBaseSource::query()
        ->where('title', '!=', 'Queued PDF policy')
        ->each(fn (KnowledgeBaseSource $source) => app(KnowledgeBaseIndexingService::class)->index($source));

    expect($knowledgeBase->refresh())
        ->sources_count->toBe(4)
        ->chunks_count->toBe(3)
        ->and(KnowledgeBaseChunk::query()->pluck('content')->implode("\n"))
        ->toContain('Enterprise onboarding includes migration support')
        ->toContain('Shipping windows are three to five business days')
        ->toContain('warranty and shipping instructions');
});

it('prevents users from accessing another workspace knowledge base or source', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $ownerWorkspace = app(WorkspaceResolver::class)->current($owner);

    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'name' => 'Owner KB',
        'settings' => [],
    ]);
    $source = $knowledgeBase->sources()->create([
        'type' => 'text',
        'title' => 'Owner source',
        'content' => 'Private workspace content.',
        'status' => 'ready',
        'metadata' => [],
    ]);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($other)
        ->get(route('user.knowledge-bases.show', $knowledgeBase))
        ->assertNotFound();

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($other)
        ->post(route('user.knowledge-bases.sources.reindex', $source))
        ->assertNotFound();
});

it('stores indexing failures without breaking the manage page', function (): void {
    Queue::fake();
    Http::fake([
        'https://example.com/broken' => Http::response('Down', 500),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Error KB',
        'settings' => [],
    ]);

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->post(route('user.knowledge-bases.sources.store', $knowledgeBase), [
            'type' => 'url',
            'title' => 'Broken URL',
            'url' => 'https://example.com/broken',
        ])
        ->assertRedirect(route('user.knowledge-bases.show', $knowledgeBase));

    $source = KnowledgeBaseSource::query()->firstOrFail();
    app(KnowledgeBaseIndexingService::class)->index($source);
    $source->refresh();

    expect($source)
        ->status->toBe('error')
        ->error->toBe('URL returned HTTP 500.')
        ->and($knowledgeBase->refresh()->status)->toBe('error');

    $this->withoutMiddleware(knowledgeBaseTestMiddleware())
        ->actingAs($user)
        ->get(route('user.knowledge-bases.show', $knowledgeBase))
        ->assertOk()
        ->assertSee('Broken URL')
        ->assertSee('URL returned HTTP 500.');
});

it('syncs chunks to local qdrant and can retrieve them through vector search', function (): void {
    app(AiSettingsService::class)->set('vector_database_enabled', true);
    app(AiSettingsService::class)->set('vector_database_mode', 'local');
    app(AiSettingsService::class)->set('vector_database_provider', 'qdrant');
    app(AiSettingsService::class)->set('qdrant_url', 'http://localhost:6333');
    app(AiSettingsService::class)->set('qdrant_collection', 'knowledge_base_chunks');

    Http::fake([
        'http://localhost:6333/collections/knowledge_base_chunks' => Http::sequence()
            ->push([], 404)
            ->push(['result' => ['status' => 'green']], 200),
        'http://localhost:6333/collections/knowledge_base_chunks/points?wait=true' => Http::response(['result' => true], 200),
        'http://localhost:6333/collections/knowledge_base_chunks/points/search' => function ($request) {
            $chunk = KnowledgeBaseChunk::query()->firstOrFail();

            return Http::response([
                'result' => [
                    [
                        'id' => $chunk->vector_id,
                        'score' => 0.91,
                        'payload' => ['chunk_id' => $chunk->id],
                    ],
                ],
            ], 200);
        },
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Vector KB',
        'settings' => [],
    ]);
    $source = $knowledgeBase->sources()->create([
        'type' => 'text',
        'title' => 'Vector policy',
        'content' => 'Vector search should find priority onboarding details.',
        'status' => 'pending',
        'metadata' => [],
    ]);

    app(KnowledgeBaseIndexingService::class)->index($source);

    expect($source->refresh())
        ->status->toBe('ready')
        ->vector_status->toBe('synced')
        ->and(KnowledgeBaseChunk::query()->first()->vector_id)->not->toBeNull();

    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Vector Bot',
        'persona' => 'Answer from knowledge.',
        'is_active' => true,
    ]);
    $chatbot->knowledgeBases()->sync([$knowledgeBase->id]);

    $reply = app(ClaudeReplyService::class)->draftReply('priority onboarding', ['chatbot' => $chatbot->load('knowledgeBases')]);

    expect($reply['context']['search_mode'])->toBe('qdrant')
        ->and($reply['context']['knowledge_context_count'])->toBe(1)
        ->and($reply['reply'])->toContain('Vector search should find priority onboarding details.');
});
