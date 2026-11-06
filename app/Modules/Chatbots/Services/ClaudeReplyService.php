<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Commerce\AiAgents\StoreAssistantAgent;
use App\Modules\Commerce\AiTools\ChatbotProductPresenter;
use App\Modules\Commerce\AiTools\ProductShowcaseCollector;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\StorefrontUrl;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchResult;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Throwable;

class ClaudeReplyService
{
    public const CUSTOMER_FALLBACK_REPLY = 'Thanks for your message! Our team will get back to you shortly.';

    public const HISTORY_LIMIT = 10;

    public const HANDOFF_AI_UNAVAILABLE = 'ai_unavailable';

    /** Phrases that mean "let me talk to a person" (English, Bangla and common romanised Bangla). */
    protected const HUMAN_REQUEST_PATTERN = '/\b(talk|speak|chat|connect)\s+(to|with)\s+(a\s+|an\s+|the\s+|your\s+)?(human|person|agent|someone|somebody|representative|support|team|owner|manager)\b|\b(real|live)\s+(person|human|agent)\b|\bhuman\s+(agent|support|please)\b|\bcustomer\s+(service|care|support)\b|\bcall\s+me\b|\bi\s+(want|need)\s+(a\s+)?(human|person|agent)\b|^\s*(human|agent|operator)\s*[.!?]*\s*$|মানুষ|এজেন্ট|প্রতিনিধি|কথা\s*বলতে\s*চাই|manush(er)?\s+(sathe|shathe|sathe)|agent\s+lagbe/iu';

    public function __construct(
        protected AiSettingsService $settings,
        protected KnowledgeBaseSearchService $knowledgeBases,
        protected AiUsageLogger $usageLogger,
    ) {}

    /**
     * Context keys: chatbot (required), conversation_id, inbound_message_id, page_url, channel.
     *
     * handoff_reason: customer_request | bot_unsure (switch on Human takeover) or ai_unavailable (technical, never pauses).
     *
     * @return array{provider: ?string, model: ?string, reply: string, confidence: float, handoff: bool, handoff_reason?: ?string, context: array<string, mixed>, input: string, products: list<array<string, mixed>>}
     */
    public function draftReply(string $message, array $context = []): array
    {
        $chatbot = $context['chatbot'] ?? null;
        $showcase = app(ProductShowcaseCollector::class);
        $showcase->clear();

        if (! $chatbot instanceof Chatbot) {
            return $this->fallback($message, $context, null, null, $this->customerFallback(null));
        }

        $chatbot->loadMissing('knowledgeBases');
        $provider = $this->settings->textProvider();
        $model = $this->settings->textModel();
        $search = $this->knowledgeBases->search($chatbot, $message);

        if ($chatbot->handoffEnabled('on_request') && $this->asksForHuman($message)) {
            return $this->fallback($message, $context, $provider, $model, $this->customerFallback($chatbot), Conversation::AI_PAUSE_CUSTOMER_REQUEST);
        }

        if (! app()->runningUnitTests() && ! $this->settings->hasConfiguredProvider($provider)) {
            Log::warning('Chatbot AI is not configured.', [
                'chatbot_id' => $chatbot->id,
                'provider' => $provider,
                'model' => $model,
            ]);

            return $this->fallback($message, $context, $provider, $model, $this->customerFallback($chatbot));
        }

        if ($this->isGreeting($message)) {
            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => filled($chatbot->greeting) ? $chatbot->greeting : 'Hi, how can I help you?',
                'confidence' => 0.7,
                'handoff' => false,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
                'products' => [],
            ];
        }

        if ($chatbot->fallback_only_knowledge_base && $chatbot->knowledgeBases->isNotEmpty() && $search->isEmpty()) {
            return $this->fallback($message, $this->contextWithSearch($context, $search), $provider, $model, data_get($chatbot->handoff_rules, 'message') ?: 'I could not find this in the selected knowledge base. I will connect you with a team member.', Conversation::AI_PAUSE_BOT_UNSURE);
        }

        if (app()->runningUnitTests() && ! StoreAssistantAgent::isFaked()) {
            $knowledgeReply = $search->isNotEmpty()
                ? 'Test reply generated from knowledge context: '.Str::limit($search->first()->content, 220, '')
                : 'Test reply generated from the saved chatbot configuration.';

            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => $knowledgeReply,
                'confidence' => 0.76,
                'handoff' => false,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
                'products' => [],
            ];
        }

        try {
            $currentProduct = $this->currentProduct($chatbot, $context['page_url'] ?? null);
            $agent = new StoreAssistantAgent(
                chatbot: $chatbot,
                knowledge: $search->chunks,
                history: $this->history($context, $message),
                currentProduct: $currentProduct,
                channel: (string) ($context['channel'] ?? 'widget'),
            );

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'chatbot_reply',
                    'workspace_id' => $chatbot->workspace_id,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $message,
                    'metadata' => [
                        'chatbot_id' => $chatbot->id,
                        'source' => 'platform',
                        'knowledge_context_count' => $search->count(),
                        'search_mode' => $search->mode,
                        'current_product_id' => $currentProduct?->id,
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $message,
                    provider: $provider,
                    model: $model,
                    timeout: 30,
                ),
            );
            $reply = trim((string) $response->text);
            $handedOff = $this->isHandoffReply($chatbot, $reply);
            if (! $handedOff) {
                $this->showCurrentProduct($chatbot, $currentProduct, $reply, $context['conversation_id'] ?? null);
            }

            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => $reply,
                'confidence' => $handedOff ? 0.0 : 0.8,
                'handoff' => $handedOff,
                'handoff_reason' => $handedOff ? Conversation::AI_PAUSE_BOT_UNSURE : null,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
                'products' => $handedOff ? [] : $showcase->all(),
            ];
        } catch (Throwable $exception) {
            Log::warning('Chatbot AI reply generation failed.', [
                'chatbot_id' => $chatbot->id,
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback($message, $context, $provider, $model, $this->customerFallback($chatbot));
        }
    }

    /**
     * The product on the storefront page the shopper is viewing, when it is sellable in this store.
     */
    protected function currentProduct(Chatbot $chatbot, mixed $pageUrl): ?Product
    {
        $slug = StorefrontUrl::productSlugFromUrl(is_string($pageUrl) ? $pageUrl : null);

        return $slug ? app(ChatbotProductPresenter::class, ['workspaceId' => (int) $chatbot->workspace_id])->find($slug) : null;
    }

    /**
     * Earlier text turns of this conversation, oldest first, excluding the message being answered.
     *
     * @param  array<string, mixed>  $context
     * @return list<UserMessage|AssistantMessage>
     */
    protected function history(array $context, string $message): array
    {
        $conversationId = $context['conversation_id'] ?? null;
        if (! $conversationId) {
            return [];
        }

        $messages = Message::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('direction', ['inbound', 'outbound'])
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->when($context['inbound_message_id'] ?? null, fn ($query, $id) => $query->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT + 1)
            ->get(['id', 'direction', 'body', 'type'])
            ->reverse()
            ->values();

        if (! isset($context['inbound_message_id']) && $messages->last()?->direction === 'inbound' && trim((string) $messages->last()->body) === trim($message)) {
            $messages->pop();
        }

        return $messages
            ->take(-self::HISTORY_LIMIT)
            ->map(fn (Message $item): UserMessage|AssistantMessage => $item->direction === 'inbound'
                ? new UserMessage(Str::limit((string) $item->body, 1500, ''))
                : new AssistantMessage(Str::limit((string) $item->body, 1500, '')))
            ->values()
            ->all();
    }

    /**
     * Shoppers on a product page get that product's color photos automatically: the first time in a
     * conversation, and again whenever the reply names it. Skipped when the assistant showed other products.
     */
    protected function showCurrentProduct(Chatbot $chatbot, ?Product $product, string $reply, mixed $conversationId): void
    {
        $showcase = app(ProductShowcaseCollector::class);
        if (! $product || $reply === '' || $showcase->has($product->id)) {
            return;
        }

        $mentioned = Str::contains(Str::lower($reply), [Str::lower($product->name), '/products/'.Str::lower($product->slug)]);
        $firstTime = $showcase->all() === [] && ! $this->alreadyShowcased($product->id, $conversationId);

        if ($mentioned || $firstTime) {
            $showcase->add(app(ChatbotProductPresenter::class, ['workspaceId' => (int) $chatbot->workspace_id])->showcase($product));
        }
    }

    protected function alreadyShowcased(int $productId, mixed $conversationId): bool
    {
        if (! $conversationId) {
            return false;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', 'outbound')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['id', 'payload'])
            ->contains(fn (Message $message): bool => collect(data_get($message->payload, 'products', []))->contains('product_id', $productId));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{provider: ?string, model: ?string, reply: string, confidence: float, handoff: bool, context: array<string, mixed>, input: string}
     */
    /**
     * Customer-facing reply used when AI is unavailable; never exposes setup or provider details.
     */
    protected function customerFallback(?Chatbot $chatbot): string
    {
        $handoff = trim((string) data_get($chatbot?->handoff_rules, 'message'));

        return $handoff !== '' ? $handoff : self::CUSTOMER_FALLBACK_REPLY;
    }

    protected function fallback(string $message, array $context, ?string $provider, ?string $model, string $reply, string $reason = self::HANDOFF_AI_UNAVAILABLE): array
    {
        return [
            'provider' => $provider,
            'model' => $model,
            'reply' => Str::limit($reply, 500, ''),
            'confidence' => 0.0,
            'handoff' => true,
            'handoff_reason' => $reason,
            'context' => $context,
            'input' => $message,
            'products' => [],
        ];
    }

    public function asksForHuman(string $message): bool
    {
        return preg_match(self::HUMAN_REQUEST_PATTERN, $message) === 1;
    }

    /**
     * The assistant is told to answer with the chatbot's handoff message when it cannot help.
     */
    protected function isHandoffReply(Chatbot $chatbot, string $reply): bool
    {
        $handoff = Str::lower(trim((string) data_get($chatbot->handoff_rules, 'message')));

        return $handoff !== '' && Str::contains(Str::lower($reply), $handoff);
    }

    protected function isGreeting(string $message): bool
    {
        $normalized = Str::of($message)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->toString();

        return in_array($normalized, [
            'hi',
            'hello',
            'hey',
            'hi there',
            'hello there',
            'hey there',
            'good morning',
            'good afternoon',
            'good evening',
            'salam',
            'assalamualaikum',
            'thank you',
            'thanks',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function contextWithSearch(array $context, KnowledgeBaseSearchResult $search): array
    {
        return array_merge($context, [
            'knowledge_context_count' => $search->count(),
            'search_mode' => $search->mode,
            'sources_used' => $search->sourcesUsed(),
        ]);
    }
}
