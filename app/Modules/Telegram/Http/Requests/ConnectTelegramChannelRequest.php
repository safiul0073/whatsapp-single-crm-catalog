<?php

namespace App\Modules\Telegram\Http\Requests;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Foundation\Http\FormRequest;

class ConnectTelegramChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channel = $this->route('channel');
        $hasSavedToken = $channel instanceof ChannelAccount
            && $channel->provider === 'telegram'
            && filled($channel->credential('access_token'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'provider_account_id' => ['required', 'string', 'max:255'],
            'provider_display_id' => ['nullable', 'string', 'max:255'],
            'access_token' => [$hasSavedToken ? 'nullable' : 'required', 'string', 'max:255'],
            'supports_channels' => ['nullable', 'boolean'],
            'default_channel_username' => ['nullable', 'string', 'max:255'],
        ];
    }
}
