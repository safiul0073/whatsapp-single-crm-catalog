<?php

use Illuminate\Support\Facades\Route;

it('does not expose workspace ai provider routes', function (): void {
    expect(Route::has('user.chatbots.ai-providers.index'))->toBeFalse()
        ->and(Route::has('user.chatbots.ai-providers.store'))->toBeFalse()
        ->and(Route::has('user.chatbots.ai-providers.test'))->toBeFalse();
});

it('does not create or attach workspace ai providers in the fresh schema', function (): void {
    $baseMigration = file_get_contents(app_path('Modules/Chatbots/Database/Migrations/2026_07_02_000062_create_chatbots_table.php'));
    $featureMigration = file_get_contents(app_path('Modules/Chatbots/Database/Migrations/2026_07_06_000001_add_workspace_ai_providers_to_chatbots.php'));

    expect($baseMigration.$featureMigration)
        ->not->toContain('chatbot_ai_providers')
        ->not->toContain('chatbot_ai_provider_id')
        ->not->toContain("string('model')");
});

it('uses platform ai in chatbot replies embeddings and lead generation', function (): void {
    $replyService = file_get_contents(app_path('Modules/Chatbots/Services/ClaudeReplyService.php'));
    $embeddingService = file_get_contents(app_path('Modules/KnowledgeBases/Services/KnowledgeBaseEmbeddingService.php'));
    $leadService = file_get_contents(app_path('Modules/Leads/Services/LeadGenerationService.php'));

    expect($replyService)
        ->toContain('$this->settings->textProvider()')
        ->toContain('$this->settings->textModel()')
        ->not->toContain('ChatbotAiProvider')
        ->and($embeddingService)
        ->toContain('$this->settings->embeddingsProvider()')
        ->toContain('$this->settings->embeddingsModel()')
        ->not->toContain('ChatbotAiProvider')
        ->and($leadService)
        ->toContain('$this->settings->textProvider()')
        ->not->toContain('workspaceProvider');
});

it('removes provider and model controls from chatbot screens', function (): void {
    $form = file_get_contents(app_path('Modules/Chatbots/Resources/views/user/config.blade.php'));
    $sidebar = file_get_contents(resource_path('views/components/layouts/partials/user-sidebar.blade.php'));

    expect($form.$sidebar)
        ->not->toContain('chatbot_ai_provider_id')
        ->not->toContain('ai-providers')
        ->not->toContain('Model override');
});
