<?php

namespace App\Modules\AuthApi\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all(), []),
            'two_factor' => [
                'enabled' => $this->hasOtpTwoFactorEnabled(),
                'channel' => $this->otp_two_factor_channel,
            ],
        ];
    }
}
