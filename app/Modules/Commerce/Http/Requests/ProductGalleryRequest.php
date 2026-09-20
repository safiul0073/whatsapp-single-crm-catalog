<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'array', 'min:1', 'max:40'],
            'media.*.id' => ['required', 'integer', 'distinct', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)],
            'media.*.color_id' => ['nullable'],
            'media.*.alt_text' => ['nullable', 'string', 'max:255'],
            'media.*.is_primary' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['nullable', 'integer'],
            'colors.*.swatch_media_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $items = collect($this->input('media', []));
            $media = Media::query()->whereIn('id', $items->pluck('id'))->get()->keyBy('id');
            $images = $media->where('type', 'image');
            $videos = $media->where('type', 'video');
            $primaryIds = $items->where('is_primary', true)->pluck('id');

            if ($images->count() > 40) {
                $validator->errors()->add('media', 'A product can contain at most 40 images.');
            }
            if ($videos->count() > 1) {
                $validator->errors()->add('media', 'A product can contain at most one video.');
            }
            if ($media->count() !== $items->count() || $media->contains(fn (Media $item): bool => ! in_array($item->type, ['image', 'video'], true))) {
                $validator->errors()->add('media', 'Choose only images or an MP4 video from your Media Library.');
            }
            if ($videos->contains(fn (Media $item): bool => $item->mime_type !== 'video/mp4' || $item->size > 16 * 1024 * 1024)) {
                $validator->errors()->add('media', 'Product video must be an MP4 no larger than 16 MB.');
            }
        }];
    }
}
