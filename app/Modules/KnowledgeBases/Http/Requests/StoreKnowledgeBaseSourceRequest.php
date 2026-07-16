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
            'type' => ['required', Rule::in(['url', 'sitemap', 'file', 'text', 'qa'])],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'required_if:type,url,sitemap', 'url', 'max:2048'],
            'content' => ['nullable', 'required_if:type,text', 'string', 'max:200000'],
            'question' => ['nullable', 'required_if:type,qa', 'string', 'max:2000'],
            'answer' => ['nullable', 'required_if:type,qa', 'string', 'max:200000'],
            'crawl_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:20480', 'mimes:pdf,docx,txt,md,csv,json,html,htm'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required_if' => 'Add the source URL.',
            'content.required_if' => 'Add the source text.',
            'question.required_if' => 'Add the question.',
            'answer.required_if' => 'Add the answer.',
            'file.required_if' => 'Upload a supported source file.',
        ];
    }
}
