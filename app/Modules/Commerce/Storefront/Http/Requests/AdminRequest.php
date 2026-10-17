<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['config', 'sections', 'items', 'slides', 'data'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => json_decode($this->input($key), true)]);
            }
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'settings' => ['config' => 'required|array', 'config.name' => 'required|string|max:150', 'config.primary' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'config.hover' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'config.footer_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'config.password_login' => 'boolean', 'config.logo' => 'nullable|url:http,https', 'config.announcement' => 'nullable|string|max:250', 'config.contact_email' => 'nullable|email', 'config.contact_phone' => 'nullable|string|max:30', 'config.footer_text' => 'nullable|string|max:500'],
            'savePage' => ['slug' => 'required|regex:/^[a-z0-9][a-z0-9-]*$/D|max:120', 'title' => 'required|string|max:200', 'sections' => 'present|array|max:50', 'sections.*' => 'array', 'seo' => 'nullable|array', 'seo.title' => 'nullable|string|max:200', 'seo.description' => 'nullable|string|max:300'],
            'slider' => ['name' => 'required|string|max:150', 'slides' => 'present|array|max:20', 'slides.*.title' => 'required|string|max:200', 'slides.*.subtitle' => 'nullable|string|max:1000', 'slides.*.image' => 'required|url:http,https', 'slides.*.mobile_image' => 'nullable|url:http,https', 'slides.*.video' => 'nullable|url:http,https', 'slides.*.button_text' => 'nullable|string|max:80', 'slides.*.button_link' => ['nullable', 'string', 'regex:#^(?:/(?!/)|https://)#'], 'slides.*.active' => 'boolean', 'slides.*.starts_at' => 'nullable|date', 'slides.*.ends_at' => 'nullable|date'],
            'menu' => ['slot' => 'required|in:header,footer,mobile', 'items' => 'present|array|max:50', 'items.*.label' => 'required|string|max:100', 'items.*.href' => ['required', 'string', 'regex:#^(?:/(?!/)|https://)#']],
            'addDomain' => ['host' => 'required|string|max:253'],
            'coupon' => ['code' => 'required|regex:/^[A-Z0-9_-]+$/D|max:100', 'type' => 'required|in:fixed,percentage', 'value' => ['required', 'numeric', 'min:0', $this->input('type') === 'percentage' ? 'max:100' : 'max:100000000'], 'min_order' => 'nullable|numeric|min:0', 'max_discount' => 'nullable|numeric|min:0', 'usage_limit' => 'nullable|integer|min:1', 'per_customer_limit' => 'nullable|integer|min:1', 'starts_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:starts_at', 'active' => 'boolean', 'product_ids' => 'nullable|array', 'product_ids.*' => 'integer', 'category_ids' => 'nullable|array', 'category_ids.*' => 'integer'],
            'content' => ['type' => 'required|in:blog,faq,collection', 'slug' => 'required|regex:/^[a-z0-9][a-z0-9-]*$/D|max:120', 'data' => 'required|array', 'data.title' => 'nullable|string|max:200', 'data.excerpt' => 'nullable|string|max:500', 'data.content' => 'nullable|string|max:100000', 'data.question' => 'nullable|string|max:300', 'data.answer' => 'nullable|string|max:30000', 'data.product_ids' => 'nullable|array|max:100', 'data.product_ids.*' => 'integer', 'published' => 'boolean'],
            'renameDomain' => ['handle' => 'required|regex:/^[a-z0-9][a-z0-9-]{2,40}$/D'],
            'review' => ['status' => 'required|in:approved,rejected'],
            default => [],
        };
    }
}
