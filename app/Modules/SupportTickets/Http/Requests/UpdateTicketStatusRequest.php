<?php

namespace App\Modules\SupportTickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('Please select a status.'),
            'status.in' => __('Invalid status selected.'),
        ];
    }
}
