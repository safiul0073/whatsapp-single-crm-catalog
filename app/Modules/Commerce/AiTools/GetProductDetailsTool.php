<?php

namespace App\Modules\Commerce\AiTools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Full facts for one product. Calling it also queues the product's color images to be sent to the customer.
 */
class GetProductDetailsTool implements Tool
{
    public function __construct(public int $workspaceId) {}

    public function description(): string
    {
        return 'Get full details of one product by slug or id: every color with its image, sizes in stock, allowed prices, and wholesale box/pack rules. Calling this also sends the product color photos to the customer automatically, so do not paste image URLs yourself.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product' => $schema->string()->description('Product slug or id from SearchProductsTool, or from the page the customer is viewing.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => $this->workspaceId]);
        $product = $presenter->find((string) $request->string('product'));

        if (! $product) {
            return 'Product not found or not available in this store. Use SearchProductsTool to find the right one.';
        }

        app(ProductShowcaseCollector::class)->add($presenter->showcase($product));

        return json_encode([
            'store_mode' => $presenter->policy()->mode(),
            'product' => $presenter->details($product),
            'note' => 'Color photos for up to '.ChatbotProductPresenter::MAX_SHOWCASE_COLORS.' colors are sent automatically after your reply.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
