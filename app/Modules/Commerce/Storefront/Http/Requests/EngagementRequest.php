<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'review' => ['rating' => 'required|integer|min:1|max:5', 'body' => 'required|string|max:3000'],
            'contact' => ['name' => 'required|string|max:150', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:50', 'subject' => 'nullable|string|max:200', 'order_ref' => 'nullable|string|max:100', 'message' => 'required|string|max:5000'],
            'newsletter' => ['email' => 'required|email|max:255', 'consent' => 'accepted'],
            default => []
        };
    }
}
