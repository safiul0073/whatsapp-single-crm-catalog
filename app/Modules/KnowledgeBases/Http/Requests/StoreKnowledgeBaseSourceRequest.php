<?php

namespace App\Modules\KnowledgeBases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeBaseSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['url', 'file', 'text'])],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'required_if:type,url', 'url', 'max:2048'],
            'content' => ['nullable', 'required_if:type,text', 'string', 'max:200000'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:20480', 'mimes:pdf,docx,txt,md,csv,json,html,htm'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required_if' => 'Add the source URL.',
            'content.required_if' => 'Add the source text.',
            'file.required_if' => 'Upload a supported source file.',
        ];
    }
}
