<?php

namespace App\Modules\AuthApi\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthChallengeService
{
    public function issue(User $user, string $deviceName, string $channel, string $destination): array
    {
        $challengeToken = Str::uuid()->toString();

        Cache::put(
            $this->cacheKey($challengeToken),
            [
                'user_id' => $user->getKey(),
                'device_name' => $deviceName,
                'channel' => $channel,
                'destination' => $destination,
            ],
            now()->addMinutes(5),
        );

        return [
            'challenge_token' => $challengeToken,
            'expires_in' => 300,
        ];
    }

    public function consume(string $challengeToken): ?array
    {
        $payload = Cache::pull($this->cacheKey($challengeToken));

        return is_array($payload) ? $payload : null;
    }

    public function peek(string $challengeToken): ?array
    {
        $payload = Cache::get($this->cacheKey($challengeToken));

        return is_array($payload) ? $payload : null;
    }

    protected function cacheKey(string $challengeToken): string
    {
        return 'auth-api:2fa-challenge:'.$challengeToken;
    }
}
