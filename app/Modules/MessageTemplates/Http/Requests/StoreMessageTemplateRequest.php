<?php

namespace App\Modules\MessageTemplates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:255'],
            'provider' => ['nullable', 'in:whatsapp,telegram'],
            'language' => ['required', 'string', 'max:16'],
            'category' => ['nullable', 'in:marketing,utility,authentication'],
            'body' => ['required', 'string', 'max:1024'],
            'body_examples' => ['nullable', 'array'],
            'body_examples.*' => ['nullable', 'string', 'max:255'],
            'header' => ['nullable', 'array'],
            'header.type' => ['nullable', 'in:none,text,image,video,document'],
            'header.text' => ['nullable', 'string', 'max:60'],
            'header.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'header.handle' => ['nullable', 'string', 'max:1000'],
            'header.example' => ['nullable', 'string', 'max:60'],
            'header_media_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,pdf', 'max:16384'],
            'footer' => ['nullable', 'array'],
            'footer.text' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['required_with:buttons', 'in:quick_reply,url,phone_number,callback'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
            'buttons.*.callback_data' => ['nullable', 'string', 'max:64'],
            'buttons.*.example' => ['nullable', 'string', 'max:255'],
            'submit_to_meta' => ['nullable', 'boolean'],
            'provider_account_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = $this->input('provider', 'whatsapp');
            $buttons = collect($this->input('buttons', []));

            if ($provider === 'whatsapp' && blank($this->input('category'))) {
                $validator->errors()->add('category', 'Choose a WhatsApp template category.');
            }

            if ($provider === 'whatsapp' && $buttons->where('type', 'url')->count() > 2) {
                $validator->errors()->add('buttons', 'A template can have at most 2 website buttons.');
            }

            if ($provider === 'whatsapp' && $buttons->where('type', 'phone_number')->count() > 1) {
                $validator->errors()->add('buttons', 'A template can have at most 1 phone call button.');
            }

            $buttons->each(function (array $button, int $index) use ($provider, $validator): void {
                if ($provider === 'telegram' && ! in_array($button['type'] ?? null, ['url', 'callback'], true)) {
                    $validator->errors()->add("buttons.{$index}.type", 'Telegram buttons must be URL or callback buttons.');
                }

                if ($provider === 'whatsapp' && ! in_array($button['type'] ?? null, ['quick_reply', 'url', 'phone_number'], true)) {
                    $validator->errors()->add("buttons.{$index}.type", 'WhatsApp buttons must be quick replies, website links, or phone calls.');
                }

                if (($button['type'] ?? null) === 'url' && blank($button['url'] ?? null)) {
                    $validator->errors()->add("buttons.{$index}.url", 'A website URL is required for URL buttons.');
                }

                if (($button['type'] ?? null) === 'url'
                    && filled($button['url'] ?? null)
                    && ! str_contains((string) $button['url'], '{{')
                    && filter_var($button['url'], FILTER_VALIDATE_URL) === false) {
                    $validator->errors()->add("buttons.{$index}.url", 'A website URL must be valid.');
                }

                if (($button['type'] ?? null) === 'phone_number' && blank($button['phone_number'] ?? null)) {
                    $validator->errors()->add("buttons.{$index}.phone_number", 'A phone number is required for call buttons.');
                }
            });

            if ($provider === 'telegram') {
                return;
            }

            $headerType = $this->input('header.type', 'none');

            if ($headerType === 'text' && blank($this->input('header.text'))) {
                $validator->errors()->add('header.text', 'Header text is required when the text header is selected.');
            }

            if (in_array($headerType, ['image', 'video', 'document'], true)
                && blank($this->input('header.media_id'))
                && blank($this->input('header.handle'))
                && ! $this->hasFile('header_media_file')) {
                $validator->errors()->add('header_media_file', 'Upload a media example for the selected header type.');
            }

            if ($this->hasFile('header_media_file')) {
                $mimeType = (string) $this->file('header_media_file')->getMimeType();

                if ($headerType === 'image' && ! str_starts_with($mimeType, 'image/')) {
                    $validator->errors()->add('header_media_file', 'Upload an image file for an image header.');
                }

                if ($headerType === 'video' && $mimeType !== 'video/mp4') {
                    $validator->errors()->add('header_media_file', 'Upload an MP4 file for a video header.');
                }

                if ($headerType === 'document' && $mimeType !== 'application/pdf') {
                    $validator->errors()->add('header_media_file', 'Upload a PDF file for a document header.');
                }
            }

            $body = (string) $this->input('body', '');

            if ($this->hasLeadingOrTrailingVariable($body)) {
                $validator->errors()->add('body', 'Variables cannot be at the start or end of the template body. Add text before the first variable and after the last variable.');
            }
        });
    }

    protected function hasLeadingOrTrailingVariable(string $body): bool
    {
        return preg_match('/^\s*\{\{\s*[^}]+\s*\}\}/', $body) === 1
            || preg_match('/\{\{\s*[^}]+\s*\}\}\s*$/', $body) === 1;
    }
}
