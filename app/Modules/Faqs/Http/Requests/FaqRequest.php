<?php

namespace App\Modules\Faqs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function sharedRules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'active' => ['nullable', 'boolean'],
            'faq_id' => ['nullable', 'integer'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $url = parent::getRedirectUrl();

        if ($this->has('faq_id') && $this->filled('faq_id')) {
            session()->flash('open_modal', 'editFaqModal-'.$this->input('faq_id'));
        } else {
            session()->flash('open_modal', 'addFaqModal');
        }

        return $url;
    }

    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question.',
            'answer.required' => 'Please enter an answer.',
            'sort_order.required' => 'Please enter a sort order.',
            'status.required' => 'Please choose a publishing status.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
            'status' => $this->input('status', 'published'),
        ]);
    }
}
