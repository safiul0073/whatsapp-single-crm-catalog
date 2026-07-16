<?php

namespace App\Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadUserMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:16384', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4']];
    }
}
