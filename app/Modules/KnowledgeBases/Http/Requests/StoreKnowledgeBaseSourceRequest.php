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
            'type' => ['required', Rule::in(['url', 'file', 'text', 'faq'])],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'required_if:type,url', 'url', 'max:2048'],
            'content' => ['nullable', 'required_if:type,text', 'string', 'max:200000'],
            'faq' => ['nullable', 'required_if:type,faq', 'array', 'max:100'],
            'faq.*.question' => ['nullable', 'required_with:faq.*.answer', 'string', 'max:500'],
            'faq.*.answer' => ['nullable', 'required_with:faq.*.question', 'string', 'max:5000'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:20480', 'mimes:pdf,docx,txt,md,csv,json,html,htm'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required_if' => 'Add the web page address.',
            'content.required_if' => 'Write the text to add.',
            'faq.required_if' => 'Add at least one question and answer.',
            'faq.*.question.required_with' => 'Each answer needs a question.',
            'faq.*.answer.required_with' => 'Each question needs an answer.',
            'file.required_if' => 'Choose a file to upload (PDF, Word, text, CSV).',
        ];
    }
}
