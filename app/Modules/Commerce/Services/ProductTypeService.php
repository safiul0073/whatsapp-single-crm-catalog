<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\ProductType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductTypeService
{
    /**
     * Preset types copied into a workspace the first time it needs them.
     *
     * @return array<string, array{name: string, layout: string, variant_axes: array<int, string>, attributes: array<int, array<string, mixed>>}>
     */
    public function presets(): array
    {
        return [
            'apparel' => [
                'name' => 'Apparel',
                'layout' => 'apparel',
                'variant_axes' => ['size', 'color'],
                'attributes' => [],
            ],
            'jewelry' => [
                'name' => 'Jewelry',
                'layout' => 'jewelry',
                'variant_axes' => ['size'],
                'attributes' => [
                    $this->attr('metal', 'Metal', 'select', ['Gold', 'Silver', 'Platinum', 'Rose Gold', 'Brass', 'Other'], filterable: true, card: true),
                    $this->attr('purity', 'Purity / Karat', 'select', ['24K', '22K', '18K', '14K', '925 Sterling', 'Other'], filterable: true),
                    $this->attr('stone', 'Stone', 'text', filterable: true),
                    $this->attr('weight_g', 'Weight (g)', 'number', card: true),
                ],
            ],
            'cosmetics' => [
                'name' => 'Cosmetics',
                'layout' => 'cosmetics',
                'variant_axes' => ['shade', 'volume'],
                'attributes' => [
                    $this->attr('skin_type', 'Skin type', 'select', ['All', 'Oily', 'Dry', 'Combination', 'Sensitive'], filterable: true),
                    $this->attr('volume_ml', 'Volume (ml)', 'number', card: true),
                    $this->attr('ingredients', 'Ingredients', 'text'),
                    $this->attr('expiry', 'Expiry / shelf life', 'text'),
                ],
            ],
            'food' => [
                'name' => 'Food',
                'layout' => 'food',
                'variant_axes' => ['weight'],
                'attributes' => [
                    $this->attr('diet', 'Diet', 'select', ['Veg', 'Non-veg', 'Vegan'], filterable: true, card: true),
                    $this->attr('ingredients', 'Ingredients', 'text'),
                    $this->attr('allergens', 'Allergens', 'text'),
                    $this->attr('shelf_life', 'Shelf life', 'text', card: true),
                ],
            ],
            'accessories' => [
                'name' => 'Accessories',
                'layout' => 'accessories',
                'variant_axes' => ['color'],
                'attributes' => [
                    $this->attr('material', 'Material', 'text', filterable: true, card: true),
                    $this->attr('compatible_with', 'Compatible with', 'text'),
                ],
            ],
            'custom' => [
                'name' => 'Custom',
                'layout' => 'generic',
                'variant_axes' => [],
                'attributes' => [],
            ],
        ];
    }

    /**
     * Create any missing preset types for the workspace and return all of its types.
     *
     * @return Collection<int, ProductType>
     */
    public function ensureDefaults(int $workspaceId): Collection
    {
        if (! ProductType::query()->where('workspace_id', $workspaceId)->exists()) {
            foreach ($this->presets() as $slug => $preset) {
                ProductType::query()->create([...$preset, 'slug' => $slug, 'workspace_id' => $workspaceId, 'is_system' => true]);
            }
        }

        return ProductType::query()->where('workspace_id', $workspaceId)->orderBy('name')->get();
    }

    /**
     * Resolve the type for a category, walking up to its parents. Falls back to the workspace's
     * apparel type so existing categories behave as before, then to an unsaved generic type.
     */
    public function forCategory(?Category $category, int $workspaceId): ProductType
    {
        $visited = [];
        while ($category && ! in_array($category->id, $visited, true)) {
            $visited[] = $category->id;
            if ($category->product_type_id && $type = ProductType::query()->where('workspace_id', $workspaceId)->find($category->product_type_id)) {
                return $type;
            }
            $category = $category->parent_id ? Category::query()->where('workspace_id', $workspaceId)->find($category->parent_id) : null;
        }

        $this->ensureDefaults($workspaceId);

        return ProductType::query()->where('workspace_id', $workspaceId)->where('slug', 'apparel')->first()
            ?? new ProductType(['name' => 'Generic', 'slug' => 'generic', 'layout' => 'generic', 'variant_axes' => [], 'attributes' => []]);
    }

    /**
     * Keep only values for attributes the type defines, and enforce required/select rules.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitizeValues(ProductType $type, array $values): array
    {
        $clean = [];
        $errors = [];

        foreach ($type->attributes ?? [] as $definition) {
            $key = $definition['key'];
            $value = $values[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $empty = $value === null || $value === '';

            if ($empty) {
                if (! empty($definition['required'])) {
                    $errors["attribute_values.$key"] = __(':label is required.', ['label' => $definition['label']]);
                }

                continue;
            }

            match ($definition['type']) {
                'number' => is_numeric($value) ? $clean[$key] = $value + 0 : $errors["attribute_values.$key"] = __(':label must be a number.', ['label' => $definition['label']]),
                'boolean' => $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'select' => in_array($value, $definition['options'] ?? [], true) ? $clean[$key] = $value : $errors["attribute_values.$key"] = __(':label has an invalid choice.', ['label' => $definition['label']]),
                default => $clean[$key] = mb_substr((string) $value, 0, 500),
            };
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    private function attr(string $key, string $label, string $type, array $options = [], bool $filterable = false, bool $card = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'options' => $options, 'required' => false, 'filterable' => $filterable, 'show_on_card' => $card];
    }
}
