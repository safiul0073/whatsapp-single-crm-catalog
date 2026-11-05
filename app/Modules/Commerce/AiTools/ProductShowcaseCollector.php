<?php

namespace App\Modules\Commerce\AiTools;

/**
 * Collects the products an assistant looked up during one reply, so channels can send their color images.
 * Registered as a scoped binding: one instance per request/job.
 */
class ProductShowcaseCollector
{
    public const MAX_PRODUCTS = 3;

    /** @var array<int, array{product_id: int, name: string, slug: string, url: string, colors: list<array{color: string, image_url: string, price_line: ?string}>, more_colors: int}> */
    protected array $products = [];

    /**
     * @param  array{product_id: int, name: string, slug: string, url: string, colors: list<array{color: string, image_url: string, price_line: ?string}>, more_colors: int}  $showcase
     */
    public function add(array $showcase): void
    {
        if (isset($this->products[$showcase['product_id']]) || count($this->products) >= self::MAX_PRODUCTS) {
            return;
        }

        $this->products[$showcase['product_id']] = $showcase;
    }

    public function has(int $productId): bool
    {
        return isset($this->products[$productId]);
    }

    /**
     * @return list<array{product_id: int, name: string, slug: string, url: string, colors: list<array{color: string, image_url: string, price_line: ?string}>, more_colors: int}>
     */
    public function all(): array
    {
        return array_values($this->products);
    }

    public function clear(): void
    {
        $this->products = [];
    }
}
