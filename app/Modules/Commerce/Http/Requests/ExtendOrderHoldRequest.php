<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendOrderHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required_without:until', 'nullable', 'integer', 'min:1', 'max:168'],
            'until' => ['required_without:hours', 'nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays(30)->toDateTimeString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.max' => 'A hold can be extended by at most 168 hours (7 days) at a time.',
            'until.after' => 'Choose a time in the future.',
            'until.before_or_equal' => 'A hold can run for at most 30 days.',
        ];
    }
}
