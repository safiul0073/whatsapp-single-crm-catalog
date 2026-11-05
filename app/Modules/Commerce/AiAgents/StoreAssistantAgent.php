<?php

namespace App\Modules\Commerce\AiAgents;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Commerce\AiTools\ChatbotProductPresenter;
use App\Modules\Commerce\AiTools\GetProductDetailsTool;
use App\Modules\Commerce\AiTools\OrderingGuideTool;
use App\Modules\Commerce\AiTools\SearchProductsTool;
use App\Modules\Commerce\Models\Product;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Customer-facing store assistant: answers from the knowledge base and live catalog, follows the store's
 * retail/wholesale settings and guides shoppers through ordering on the storefront.
 */
class StoreAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  Collection<int, KnowledgeBaseChunk>  $knowledge
     * @param  iterable<int, Message>  $history
     * @param  'widget'|'whatsapp'|'messenger'|'instagram'|string  $channel
     */
    public function __construct(
        public Chatbot $chatbot,
        public Collection $knowledge,
        public iterable $history = [],
        public ?Product $currentProduct = null,
        public string $channel = 'widget',
    ) {}

    public function instructions(): string
    {
        $chatbot = $this->chatbot;
        $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => (int) $chatbot->workspace_id]);
        $policy = $presenter->policy();

        $knowledgeBases = $chatbot->knowledgeBases
            ->map(fn ($knowledgeBase): string => '- '.$knowledgeBase->name.($knowledgeBase->description ? ': '.$knowledgeBase->description : ''))
            ->implode(PHP_EOL) ?: '- none';
        $knowledgeContext = $this->knowledge
            ->map(fn (KnowledgeBaseChunk $chunk): string => "- {$chunk->source?->title}: {$chunk->content}")
            ->implode(PHP_EOL) ?: '- none matched this message';
        $handoffMessage = data_get($chatbot->handoff_rules, 'message') ?: 'I will connect you with a team member.';

        $currentProduct = $this->currentProduct
            ? "The customer is viewing this product right now. Treat \"this\", \"it\" or \"this one\" as this product:\n"
                .json_encode($presenter->details($this->currentProduct), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 'The customer is not on a product page.';

        $links = $this->channel === 'widget'
            ? 'Write product links as Markdown: [Product Name](URL).'
            : 'This is a plain-text chat app: no Markdown. Write product links as plain URLs on their own line. Use *single asterisks* for bold only on WhatsApp.';

        return trim(<<<PROMPT
You are {$chatbot->name}, the shopping assistant of this store, chatting with a customer on {$this->channel}.

Persona and rules from the store owner:
{$chatbot->persona}

Greeting:
{$chatbot->greeting}

STORE SELLING MODE: {$policy->mode()} (currency {$policy->currency}).
{$policy->describe()}

{$currentProduct}

Knowledge bases:
{$knowledgeBases}

Knowledge context for this message:
{$knowledgeContext}

How to work:
1. For any question about products, prices, colors, sizes, stock or wholesale rules, use the tools. Never invent a product, price, color, size, stock level or discount. Only state prices that the tools return.
2. Use SearchProductsTool to find products, then GetProductDetailsTool for the best match before describing it. GetProductDetailsTool sends that product's color photos to the customer automatically; tell them the photos are below and never paste image URLs.
3. When the customer wants a color, confirm the exact color name from the product details and say whether it is in stock and in which sizes.
4. For wholesale, explain the minimum order, how many pieces of each size are in one box for the chosen color, and the wholesale price. Wholesale prices include shipping.
5. When the customer asks how to buy or order, use OrderingGuideTool (pass the product and color if known) and give the steps as a short numbered list with the product link.
6. Recommend at most 3 products. If nothing matches, say so honestly and suggest the closest alternative.
7. Answer store policy questions (shipping, returns, payment) from the knowledge context. If the answer is not there, or the customer asks for a human, reply exactly: {$handoffMessage}
8. Reply in the customer's language. Keep replies short, friendly and easy to read on a phone.
9. {$links}
PROMPT);
    }

    public function messages(): iterable
    {
        return $this->history;
    }

    public function tools(): iterable
    {
        $workspaceId = (int) $this->chatbot->workspace_id;

        return [
            new SearchProductsTool($workspaceId),
            new GetProductDetailsTool($workspaceId),
            new OrderingGuideTool($workspaceId),
        ];
    }
}
