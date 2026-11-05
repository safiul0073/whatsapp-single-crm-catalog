<?php

namespace App\Modules\Commerce\AiTools;

use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Finds products the customer can actually buy, with prices allowed by the store's retail/wholesale settings.
 */
class SearchProductsTool implements Tool
{
    public function __construct(public int $workspaceId) {}

    public function description(): string
    {
        return 'Search the store catalog. Use it whenever the customer asks about products, styles, colors, sizes, prices or availability. Returns up to 5 products with name, slug, storefront URL, allowed prices, colors and stock. Call GetProductDetailsTool with a slug for full color/size/wholesale details.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords, e.g. "black hoodie" or "cotton shirt". Leave empty to browse.'),
            'color' => $schema->string()->description('Optional color name filter, e.g. "Black".'),
            'size' => $schema->string()->description('Optional size filter, e.g. "M".'),
            'category' => $schema->string()->description('Optional category name or slug.'),
        ];
    }

    public function handle(Request $request): string
    {
        $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => $this->workspaceId]);
        $query = trim((string) $request->string('query'));
        $color = trim((string) $request->string('color'));
        $size = trim((string) $request->string('size'));
        $category = trim((string) $request->string('category'));

        $products = $presenter->query()
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $words = collect(preg_split('/\s+/u', mb_substr($query, 0, 120)) ?: [])->filter(fn (string $word): bool => mb_strlen($word) > 1)->take(6);
                $builder->where(function (Builder $where) use ($words, $query): void {
                    $where->where('name', 'like', '%'.$query.'%');
                    foreach ($words as $word) {
                        $where->orWhere('name', 'like', '%'.$word.'%')
                            ->orWhere('short_description', 'like', '%'.$word.'%')
                            ->orWhere('brand', 'like', '%'.$word.'%')
                            ->orWhereHas('colors', fn (Builder $c) => $c->where('name', 'like', '%'.$word.'%'))
                            ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', '%'.$word.'%'));
                    }
                });
            })
            ->when($color !== '', fn (Builder $builder) => $builder->whereHas('colors', fn (Builder $c) => $c->where('name', 'like', '%'.$color.'%')))
            ->when($size !== '', fn (Builder $builder) => $builder->whereHas('variants', fn (Builder $v) => $v->where('status', 'active')->where('size', $size)))
            ->when($category !== '', fn (Builder $builder) => $builder->whereHas('category', fn (Builder $c) => $c->where('slug', $category)->orWhere('name', 'like', '%'.$category.'%')))
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return 'No products found. Try fewer or different keywords, or tell the customer politely that this item is not available.';
        }

        return json_encode([
            'store_mode' => $presenter->policy()->mode(),
            'products' => $products->map(fn (Product $product): array => $presenter->summary($product))->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
