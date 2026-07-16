<?php

namespace App\Modules\Sms\Http\Requests;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectSmsChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'provider_display_id' => ['required', 'string', 'max:20'],
            'sms_provider' => ['required', Rule::in(array_keys($this->providers()))],
        ];

        $provider = $this->input('sms_provider');
        $fields = $this->providers()[$provider]['fields'] ?? [];

        foreach ($fields as $name => $field) {
            $rules[$name] = ($field['secret'] ?? false) && $this->hasSavedSecret($provider, $name)
                ? ($field['saved_rules'] ?? ['nullable', 'string', 'max:255'])
                : ($field['rules'] ?? ['nullable', 'string', 'max:255']);
        }

        return $rules;
    }

    protected function providers(): array
    {
        return config('sms.providers', []);
    }

    protected function hasSavedSecret(string $provider, string $key): bool
    {
        $channel = $this->route('channel');

        if (! $channel instanceof ChannelAccount || $channel->provider !== 'sms') {
            return false;
        }

        $credentials = $channel->credentials ?? [];

        return ($credentials['sms_provider'] ?? null) === $provider
            && filled($credentials[$key] ?? null)
            && blank($this->input($key));
    }
}
