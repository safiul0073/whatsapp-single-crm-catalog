<?php

namespace App\Modules\Blogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BlogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        $url = parent::getRedirectUrl();

        if ($this->has('blog_category_id') && $this->filled('blog_category_id')) {
            session()->flash('open_modal', 'editBlogCategoryModal-'.$this->input('blog_category_id'));
        } else {
            session()->flash('open_modal', 'addBlogCategoryModal');
        }

        return $url;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a category name.',
            'sort_order.required' => 'Please enter a sort order.',
        ];
    }
}
