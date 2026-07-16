<?php

namespace App\Modules\Blogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function sharedRules(): array
    {
        return [
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'author_name' => ['required', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'featured_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'read_time_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'blog_id' => ['nullable', 'integer'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $url = parent::getRedirectUrl();

        if ($this->has('blog_id') && $this->filled('blog_id')) {
            session()->flash('open_modal', 'editBlogModal-'.$this->input('blog_id'));
        } else {
            session()->flash('open_modal', 'addBlogModal');
        }

        return $url;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Please enter a blog title.',
            'author_name.required' => 'Please enter an author name.',
            'read_time_minutes.required' => 'Please enter the reading time.',
            'sort_order.required' => 'Please enter a sort order.',
            'status.required' => 'Please choose a publishing status.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
            'status' => $this->input('status', 'published'),
            'blog_category_id' => $this->input('blog_category_id') ?: null,
        ]);
    }
}
