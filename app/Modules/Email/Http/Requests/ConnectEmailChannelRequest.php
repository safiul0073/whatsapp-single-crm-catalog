<?php

namespace App\Modules\Email\Http\Requests;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectEmailChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'provider_display_id' => ['required', 'email', 'max:255'],
            'mail_mailer' => ['required', Rule::in(array_keys($this->providers()))],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ];

        $mailer = $this->input('mail_mailer');
        $fields = $this->providers()[$mailer]['fields'] ?? [];

        foreach ($fields as $name => $field) {
            $rules[$name] = ($field['secret'] ?? false) && $this->hasSavedSecret($mailer, $name)
                ? ($field['saved_rules'] ?? ['nullable', 'string', 'max:255'])
                : ($field['rules'] ?? ['nullable', 'string', 'max:255']);
        }

        return $rules;
    }

    protected function providers(): array
    {
        return config('email.providers', []);
    }

    protected function hasSavedSecret(string $mailer, string $key): bool
    {
        $channel = $this->route('channel');

        if (! $channel instanceof ChannelAccount || $channel->provider !== 'email') {
            return false;
        }

        $credentials = $channel->credentials ?? [];

        return ($credentials['mail_mailer'] ?? null) === $mailer
            && filled($credentials[$key] ?? null)
            && blank($this->input($key));
    }
}
