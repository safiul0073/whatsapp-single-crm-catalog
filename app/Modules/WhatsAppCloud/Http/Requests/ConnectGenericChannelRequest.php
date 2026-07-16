<?php

namespace App\Modules\WhatsAppCloud\Http\Requests;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectGenericChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $providers = array_keys(config('marketing-channels.providers', []));
        $provider = (string) $this->input('provider');
        $providerConfig = config("marketing-channels.providers.{$provider}", []);
        $providerAccountRequired = ! in_array($provider, ['email', 'sms'], true);

        $rules = [
            'provider' => ['required', 'string', Rule::in($providers)],
            'name' => ['required', 'string', 'max:255'],
            'provider_account_id' => [$providerAccountRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'provider_display_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:5000'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
        ];

        foreach (($providerConfig['fields'] ?? []) as $field) {
            $name = $field['name'] ?? null;

            if (! $name || isset($rules[$name]) || in_array($name, ['provider', 'webhook_verify_token'], true)) {
                continue;
            }

            $fieldRules = ['nullable', 'string', 'max:5000'];

            if (($field['type'] ?? null) === 'select') {
                $fieldRules = ['nullable', 'string', Rule::in(array_keys($field['options'] ?? []))];
            }

            if (
                ($field['required'] ?? false)
                && $this->fieldConditionApplies($field)
                && ! (($field['secret'] ?? false) && $this->hasSavedSecret($provider, $field))
            ) {
                array_shift($fieldRules);
                array_unshift($fieldRules, 'required');
            }

            $rules[$name] = $fieldRules;
        }

        if ($provider === 'email') {
            $rules['provider_display_id'] = ['required', 'email', 'max:255'];
        }

        if ($provider === 'sms') {
            $rules['provider_display_id'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provider = (string) $this->input('provider');

            if ($provider === 'whatsapp') {
                $validator->errors()->add('provider', __('Use the WhatsApp setup form to connect WhatsApp.'));
            }

            if (config("marketing-channels.providers.{$provider}.connect_mode") === 'internal') {
                $validator->errors()->add('provider', __('This channel is managed automatically and cannot be connected manually.'));
            }
        });
    }

    protected function fieldConditionApplies(array $field): bool
    {
        $showWhen = $field['show_when'] ?? null;

        if (! is_array($showWhen) || $showWhen === []) {
            return true;
        }

        foreach ($showWhen as $fieldName => $value) {
            if ((string) $this->input($fieldName) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    protected function hasSavedSecret(string $provider, array $field): bool
    {
        $name = $field['name'] ?? null;

        if (! $name) {
            return false;
        }

        $workspace = app(WorkspaceResolver::class)->current($this->user());

        if (! $workspace) {
            return false;
        }

        $channel = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->first();

        if (! $channel || blank($this->input($name))) {
            $selector = config("marketing-channels.providers.{$provider}.credential_provider_field");

            return ! $selector
                ? filled($channel?->credentials[$name] ?? null)
                : (($channel?->credentials[$selector] ?? null) === $this->input($selector)
                    && filled($channel?->credentials[$name] ?? null));
        }

        return false;
    }
}
