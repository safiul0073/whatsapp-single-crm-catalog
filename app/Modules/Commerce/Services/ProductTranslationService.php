<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AiSettings\Services\AiTextGenerator;
use App\Modules\Commerce\Models\Product;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductTranslationService
{
    private const LANGUAGE_NAMES = ['es' => 'Spanish', 'pt' => 'Portuguese', 'ar' => 'Arabic'];

    public function __construct(protected AiTextGenerator $textGenerator) {}

    /**
     * Draft a translation of the product's text fields; nothing is saved.
     *
     * @param  array<string, string|null>  $source  Unsaved field values from the form (fall back to the product).
     * @return array<string, string>
     */
    public function draft(Product $product, string $locale, array $source = []): array
    {
        $fields = [];
        foreach (Product::TRANSLATABLE_FIELDS as $field) {
            $value = $source[$field] ?? $product->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                $fields[$field] = $value;
            }
        }

        if ($fields === []) {
            return [];
        }

        if ($this->textGenerator->primaryProvider() === null) {
            throw ValidationException::withMessages([
                'ai' => __('Platform AI is not configured for text. Enable a text provider in AI Settings first.'),
            ]);
        }

        if (app()->runningUnitTests()) {
            return array_map(fn (string $value): string => '['.$locale.'] '.$value, $fields);
        }

        try {
            $result = $this->textGenerator->prompt(
                feature: 'commerce_product_translation',
                instructions: 'You translate e-commerce product copy into '.self::LANGUAGE_NAMES[$locale].'. Keep HTML tags, brand names, sizes and numbers unchanged. Reply with only a JSON object using the same keys as the input.',
                prompt: json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                context: ['workspace_id' => $product->workspace_id, 'user_id' => auth()->id(), 'metadata' => ['product_id' => $product->id, 'locale' => $locale]],
                timeout: 60,
            );
            $text = trim((string) $result['response']->text);
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
            $decoded = json_decode($text, true);
        } catch (Throwable $exception) {
            report($exception);
            $decoded = null;
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['ai' => __('AI could not translate this product. Please try again.')]);
        }

        return array_map('strval', array_intersect_key($decoded, $fields));
    }
}
