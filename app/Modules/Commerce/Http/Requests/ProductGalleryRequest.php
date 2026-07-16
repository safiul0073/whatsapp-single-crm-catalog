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
            'media' => ['required', 'array', 'min:1', 'max:11'],
            'media.*.id' => ['required', 'integer', 'distinct', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)],
            'media.*.alt_text' => ['nullable', 'string', 'max:255'],
            'media.*.is_primary' => ['required', 'boolean'],
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

            if ($images->count() > 10) {
                $validator->errors()->add('media', 'A product can contain at most 10 images.');
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
            if ($primaryIds->count() !== 1 || ! $images->has($primaryIds->first())) {
                $validator->errors()->add('media', 'Choose exactly one image as the primary catalog image.');
            }
        }];
    }
}
