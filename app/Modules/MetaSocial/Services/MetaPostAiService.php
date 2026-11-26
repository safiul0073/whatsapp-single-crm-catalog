<?php

namespace App\Modules\MetaSocial\Services;

use App\Models\User;
use App\Modules\AiSettings\Services\AiTextGenerator;
use App\Modules\Commerce\AiTools\ChatbotProductPresenter;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Jobs\GenerateImageVariants;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\ImageVariantService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;
use Throwable;

/**
 * AI helpers for the Page / Instagram post composer: product assets, captions and images.
 */
class MetaPostAiService
{
    public const TONES = ['friendly', 'promotional', 'luxury', 'short'];

    public const LANGUAGES = ['en', 'bn'];

    public const IMAGE_STYLES = ['studio', 'lifestyle', 'sale'];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected AiTextGenerator $text,
    ) {}

    public function textAvailable(): bool
    {
        return $this->text->isAvailable();
    }

    public function imageAvailable(): bool
    {
        return filled(config('ai.default_for_images'));
    }

    public function product(?User $user, int $productId): Product
    {
        $product = Product::query()
            ->where('workspace_id', $this->workspaces->current($user)->id)
            ->with(['colors.swatchMedia', 'gallery.media', 'primaryMedia', 'variants'])
            ->find($productId);

        if (! $product) {
            throw ValidationException::withMessages(['product_id' => __('Choose a product from your store.')]);
        }

        return $product;
    }

    /**
     * Everything the composer shows for a product: text facts, link and every image.
     *
     * @return array{id: int, name: string, short_description: ?string, prices: list<string>, url: string, images: list<array{media_id: int, url: string}>}
     */
    public function assets(?User $user, int $productId): array
    {
        $product = $this->product($user, $productId);
        $presenter = $this->presenter($user);
        $showcase = $presenter->showcase($product);

        $media = collect([$product->primaryMedia])
            ->merge($product->gallery->sortBy('position')->map(fn (ProductMedia $item): ?Media => $item->media))
            ->merge($product->colors->map(fn ($color): ?Media => $color->swatchMedia))
            ->filter(fn (?Media $item): bool => $item instanceof Media && $item->isImage())
            ->unique('id')
            ->values();

        return [
            'id' => $product->id,
            'name' => (string) $product->name,
            'short_description' => $this->plain($product->short_description) ?: null,
            'prices' => $presenter->priceLines($product),
            'url' => (string) $showcase['url'],
            'images' => $media->map(fn (Media $item): array => ['media_id' => $item->id, 'url' => $this->absolute((string) $item->url)])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function caption(?User $user, array $data): string
    {
        if (! $this->textAvailable()) {
            throw ValidationException::withMessages(['ai' => __('AI is not set up yet. Ask the admin to enable a text provider in AI Settings.')]);
        }

        $facts = [];

        if (filled($data['product_id'] ?? null)) {
            $assets = $this->assets($user, (int) $data['product_id']);
            $product = $this->product($user, (int) $data['product_id']);
            $facts = [
                'Product: '.$assets['name'],
                'Short description: '.($assets['short_description'] ?? '-'),
                'Details: '.Str::limit($this->plain($product->description), 800),
                'Prices: '.(implode('; ', $assets['prices']) ?: '-'),
                'Shop link: '.$assets['url'],
            ];
        }

        if (filled($data['notes'] ?? null)) {
            $facts[] = 'Owner notes: '.$data['notes'];
        }

        if ($facts === []) {
            throw ValidationException::withMessages(['ai' => __('Choose a product or write a few words about the post first.')]);
        }

        $tone = [
            'friendly' => 'warm and friendly',
            'promotional' => 'exciting, sale-focused with a clear call to action',
            'luxury' => 'elegant and premium',
            'short' => 'very short and punchy (max 2 lines)',
        ][$data['tone'] ?? 'friendly'] ?? 'warm and friendly';

        $instructions = implode("\n", [
            'You write social media posts for an online fashion store, for a Facebook Page and Instagram.',
            'Write in '.(($data['language'] ?? 'en') === 'bn' ? 'Bangla' : 'English').'. Tone: '.$tone.'.',
            ! empty($data['emojis']) ? 'Use a few fitting emojis.' : 'Do not use emojis.',
            ! empty($data['hashtags']) ? 'End with 3 to 6 relevant hashtags.' : 'Do not add hashtags.',
            'Mention the price when given and end with a short call to action to order.',
            'Do not include the link; it is attached separately. Never invent facts, discounts or prices.',
            'Return only the post text, no quotes or explanations. Max 1200 characters.',
        ]);

        try {
            $result = $this->text->prompt(
                feature: 'meta_post_caption',
                instructions: $instructions,
                prompt: implode("\n", $facts),
                context: ['workspace_id' => $this->workspaces->current($user)->id, 'user_id' => $user?->id, 'credits' => 1],
                timeout: 30,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Meta post caption generation failed.', ['exception' => $exception->getMessage()]);

            throw ValidationException::withMessages(['ai' => __('AI could not write the post right now. Please try again.')]);
        }

        return Str::limit(trim((string) $result['response']->text, " \n\r\t\"'"), 5000, '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function image(?User $user, array $data): Media
    {
        if (! $this->imageAvailable()) {
            throw ValidationException::withMessages(['ai' => __('AI images are not set up yet. Ask the admin to choose an image provider in AI Settings.')]);
        }

        $style = [
            'studio' => 'Clean e-commerce studio product photo, soft light, plain light background.',
            'lifestyle' => 'Lifestyle photo, the product worn or used in a stylish real-life setting, natural light.',
            'sale' => 'Eye-catching promotional social media banner with bold colours and space for text, no written words.',
        ][$data['style'] ?? 'studio'] ?? '';

        $prompt = trim($style.' '.$data['prompt'].' Square format for Facebook and Instagram.');
        $pending = Image::of($prompt)->square()->timeout(90);

        if (filled($data['product_id'] ?? null)) {
            $reference = $this->product($user, (int) $data['product_id'])->primaryMedia;

            if ($reference?->isImage() && Storage::disk($reference->disk)->exists($reference->path)) {
                $pending->attachments([ImageFile::fromStorage($reference->path, $reference->disk)]);
            }
        }

        try {
            $generated = $pending->generate()->firstImage();
        } catch (Throwable $exception) {
            Log::warning('Meta post image generation failed.', ['exception' => $exception->getMessage()]);

            throw ValidationException::withMessages(['ai' => __('AI could not create the image right now. Please try again.')]);
        }

        $mime = $generated->mime ?: 'image/png';
        $extension = Str::after($mime, '/') === 'jpeg' ? 'jpg' : (Str::after($mime, '/') ?: 'png');
        $fileName = Str::ulid().'.'.$extension;
        $path = 'media/'.now()->format('Y/m').'/'.$fileName;
        $contents = base64_decode($generated->image, true) ?: '';

        Storage::disk('public')->put($path, $contents);

        $media = Media::query()->create([
            'name' => 'AI post image',
            'file_name' => $fileName,
            'original_name' => 'ai-post-image.'.$extension,
            'mime_type' => $mime,
            'extension' => $extension,
            'type' => 'image',
            'size' => strlen($contents),
            'disk' => 'public',
            'path' => $path,
            'uploaded_by' => $user?->id,
        ]);

        if (app(ImageVariantService::class)->supports($media)) {
            GenerateImageVariants::dispatch($media->id)->afterCommit();
        }

        return $media;
    }

    public function mediaUrl(Media $media): string
    {
        return $this->absolute((string) $media->url);
    }

    protected function presenter(?User $user): ChatbotProductPresenter
    {
        return app(ChatbotProductPresenter::class, ['workspaceId' => (int) $this->workspaces->current($user)->id]);
    }

    protected function plain(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))) ?? '');
    }

    protected function absolute(string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }
}
