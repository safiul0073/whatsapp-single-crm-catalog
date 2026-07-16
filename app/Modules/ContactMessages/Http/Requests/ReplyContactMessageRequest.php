<?php

namespace App\Modules\ContactMessages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplyContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'template_variables' => array_merge(
                is_array($this->input('template_variables')) ? $this->input('template_variables') : [],
                $this->parseTemplateVariables((string) $this->input('template_variables_text', ''))
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'reply_type' => ['required', Rule::in(['custom', 'template'])],
            'template_id' => ['nullable', 'required_if:reply_type,template', 'integer', 'exists:notification_templates,id'],
            'subject' => ['nullable', 'required_if:reply_type,custom', 'string', 'max:255'],
            'body' => ['nullable', 'required_if:reply_type,custom', 'string', 'max:10000'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['nullable', 'string', 'max:5000'],
            'template_variables_text' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required_if' => __('Please select an email template.'),
            'subject.required_if' => __('Please enter an email subject.'),
            'body.required_if' => __('Please enter a reply message.'),
        ];
    }

    /**
     * Parse one key=value pair per line into template variables.
     *
     * @return array<string, string>
     */
    protected function parseTemplateVariables(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

                $key = trim($key);

                return $key === '' ? [] : [$key => trim($value)];
            })
            ->all();
    }
}
