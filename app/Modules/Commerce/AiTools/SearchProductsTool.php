<?php

namespace App\Modules\Commerce\AiTools;

use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchProductsTool implements Tool
{
    public function __construct(public int $workspaceId) {}

    public function description(): string
    {
        return 'Search for products in the e-commerce catalog using keywords or a search phrase. Returns a list of products with their ID, name, price, and URL.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query or keywords to find products.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $query = $request->string('query');

        $products = Product::query()
            ->with(['primaryMedia'])
            ->where('workspace_id', $this->workspaceId)
            ->where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return 'No products found matching that query.';
        }

        $results = $products->map(function (Product $product) {
            $price = $product->resolveUnitPrice(1, 'single');
            $url = url("/products/{$product->slug}"); // Base format for product URL

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => '$'.number_format($price, 2),
                'url' => $url,
            ];
        });

        return "Found the following products:\n\n".json_encode($results->toArray(), JSON_PRETTY_PRINT);
    }
}
