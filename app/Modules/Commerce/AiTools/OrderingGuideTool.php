<?php

namespace App\Modules\Commerce\AiTools;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\StorefrontUrl;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Step-by-step storefront ordering instructions that follow the store's retail/wholesale settings.
 */
class OrderingGuideTool implements Tool
{
    public function __construct(public int $workspaceId) {}

    public function description(): string
    {
        return 'Get exact step-by-step instructions for ordering on the storefront, matching what this store sells (retail and/or wholesale). Use it when the customer asks how to order, buy, check out or pay. Pass the product and color when known so the steps include the right link and selection.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product' => $schema->string()->description('Optional product slug or id.'),
            'color' => $schema->string()->description('Optional color the customer chose.'),
            'mode' => $schema->string()->enum(['retail', 'wholesale'])->description('Optional: how the customer wants to buy, when the store sells both.'),
        ];
    }

    public function handle(Request $request): string
    {
        $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => $this->workspaceId]);
        $urls = app(StorefrontUrl::class);
        $policy = $presenter->policy();
        $product = $request->filled('product') ? $presenter->find((string) $request->string('product')) : null;
        $color = trim((string) $request->string('color'));
        $wanted = (string) $request->string('mode');

        $mode = match ($policy->mode()) {
            'both' => in_array($wanted, ['retail', 'wholesale'], true) ? $wanted : 'both',
            default => $policy->mode(),
        };
        if ($product && $mode !== 'both') {
            $mode = match (true) {
                $mode === 'wholesale' && ! $product->isWholesaleEnabled() => 'retail',
                $mode === 'retail' && ! $product->isRetailEnabled() => 'wholesale',
                default => $mode,
            };
        }

        $open = $product
            ? "Open the product page: {$urls->product($this->workspaceId, $product->slug)}"
            : "Open the store: {$urls->base($this->workspaceId)} and find the product (use search or the menu).";
        $pickColor = $color !== '' ? "Select the color \"{$color}\"." : 'Select the color you want.';

        $retail = [
            $open,
            ...($policy->mode() === 'both' ? ['Choose the "Retail" tab.'] : []),
            $pickColor,
            'Select your size and quantity, then tap "Add to cart".',
            'Open the cart and tap "Checkout".',
            'Log in with your WhatsApp number (you get a one-time code on WhatsApp), enter your delivery address and pick a shipping method.',
            'Choose a payment method and place the order. You will get the order confirmation on WhatsApp.',
        ];

        $wholesale = [
            $open,
            ...($policy->mode() === 'both' ? ['Choose the "Wholesale" tab.'] : []),
            $pickColor.' You can add more colors.',
            'Choose how many boxes/packs you want for each color. Each box has a fixed size ratio set by the store'.($product ? $this->boxHint($product) : '.'),
            'Make sure the total meets the minimum order'.($product ? $this->minimumHint($presenter, $product) : '.').' Wholesale prices include shipping.',
            "Tap \"Add to cart\" and checkout. To mix different products in one pack, use the custom pack builder: {$urls->path($this->workspaceId, 'packs')}",
            'Log in with your WhatsApp number (one-time code), confirm the address, choose a payment method and place the order.',
        ];

        $steps = match ($mode) {
            'retail' => ['Retail order' => $retail],
            'wholesale' => ['Wholesale order' => $wholesale],
            default => ['Retail order (single pieces)' => $retail, 'Wholesale order (boxes/packs)' => $wholesale],
        };

        return json_encode(['store_mode' => $policy->mode(), 'steps' => $steps], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function boxHint(Product $product): string
    {
        $ratio = collect($product->getEffectiveSizeRatios())->first();
        $multiplier = max(1, (int) ($product->ws_ratio_multiplier ?: 1));

        return $ratio ? ' (one box = '.collect($ratio)->map(fn ($qty, $size): string => $size.':'.((int) $qty * $multiplier))->implode(', ').').' : '.';
    }

    protected function minimumHint(ChatbotProductPresenter $presenter, Product $product): string
    {
        $minimum = $presenter->details($product)['wholesale']['minimum_order'] ?? null;

        return $minimum ? " ({$minimum})" : '';
    }
}
