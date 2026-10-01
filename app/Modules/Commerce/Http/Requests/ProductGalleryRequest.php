<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductGalleryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['media', 'colors'] as $field) {
            if ($this->exists($field) && $this->input($field) === null) {
                $this->merge([$field => []]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'media' => ['present', 'array', 'max:40'],
            'media.*.id' => ['required', 'integer', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)],
            'media.*.color_id' => ['nullable', 'regex:/^(?:[1-9][0-9]*|idx_[0-9]+)$/'],
            'media.*.alt_text' => ['nullable', 'string', 'max:255'],
            'media.*.is_primary' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['nullable', 'integer', Rule::exists('commerce_product_colors', 'id')->where('product_id', $this->route('product')?->id)],
            'colors.*.name' => ['nullable', 'string', 'max:100'],
            'colors.*.hex_code' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.*.color_family' => ['nullable', 'string', 'max:50'],
            'colors.*.swatch_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)->where('type', 'image')],
            'next_step' => ['sometimes', 'integer', 'between:1,9'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $items = collect($this->input('media', []));
            $uniqueIds = $items->pluck('id')->unique();
            $media = Media::query()->whereIn('id', $uniqueIds)->get()->keyBy('id');
            $images = $media->where('type', 'image');
            $videos = $media->where('type', 'video');
            $primaryIds = $items->where('is_primary', true)->pluck('id');

            foreach ($items as $index => $item) {
                $colorId = $item['color_id'] ?? null;
                if (blank($colorId)) {
                    continue;
                }
                $isNewColor = preg_match('/^idx_(\d+)$/', (string) $colorId, $match)
                    && isset($this->input('colors', [])[(int) $match[1]]);
                if (! $isNewColor && ! $this->route('product')?->colors()->whereKey($colorId)->exists()) {
                    $validator->errors()->add("media.{$index}.color_id", 'Choose a color belonging to this product.');
                }
            }
            if ($primaryIds->count() > 1 || $primaryIds->contains(fn ($id): bool => $media->get($id)?->type !== 'image')) {
                $validator->errors()->add('media', 'Choose at most one primary image.');
            }

            if ($images->count() > 40) {
                $validator->errors()->add('media', 'A product can contain at most 40 images.');
            }
            if ($videos->count() > 1) {
                $validator->errors()->add('media', 'A product can contain at most one video.');
            }
            if ($media->count() !== $uniqueIds->count() || $media->contains(fn (Media $item): bool => ! in_array($item->type, ['image', 'video'], true))) {
                $validator->errors()->add('media', 'Choose only images or an MP4 video from your Media Library.');
            }
            if ($videos->contains(fn (Media $item): bool => $item->mime_type !== 'video/mp4' || $item->size > 16 * 1024 * 1024)) {
                $validator->errors()->add('media', 'Product video must be an MP4 no larger than 16 MB.');
            }
        }];
    }
}
