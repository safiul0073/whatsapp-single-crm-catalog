<?php

namespace App\Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadUserMediaRequest extends FormRequest
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];

    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v'];

    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx', 'zip'];

    /** Max upload size in KB per file kind. */
    public const MAX_KB = ['image' => 10240, 'video' => 204800, 'document' => 25600];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $extension = strtolower((string) $this->file('file')?->getClientOriginalExtension());
        $kind = match (true) {
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            in_array($extension, self::DOCUMENT_EXTENSIONS, true) => 'document',
            default => 'image',
        };

        return [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KB[$kind],
                'extensions:'.implode(',', [...self::IMAGE_EXTENSIONS, ...self::VIDEO_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS]),
                'mimes:'.implode(',', [...self::IMAGE_EXTENSIONS, ...self::VIDEO_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS, 'qt']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.uploaded' => __('The file is larger than the server upload limit.'),
            'file.max' => __('This file is too large. Images up to 10 MB, documents up to 25 MB, videos up to 200 MB.'),
            'file.extensions' => __('This file type is not supported.'),
            'file.mimes' => __('This file type is not supported.'),
        ];
    }
}
