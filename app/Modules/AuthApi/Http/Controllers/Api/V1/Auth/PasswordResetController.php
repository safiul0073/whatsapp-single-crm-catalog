<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    use ApiResponse;

    #[OA\Post(path: '/api/v1/auth/forgot-password')]
    #[OA\Response(response: 200, description: 'Reset link sent')]
    #[OA\Response(response: 422, description: 'Unable to send reset link')]
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('users')->sendResetLink([
            'email' => $request->string('email')->lower()->value(),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->errorResponse(__($status), 422);
        }

        return $this->successResponse([], __($status));
    }

    #[OA\Post(path: '/api/v1/auth/reset-password')]
    #[OA\Response(response: 200, description: 'Password reset successful')]
    #[OA\Response(response: 422, description: 'Unable to reset password')]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('users')->reset(
            [
                'email' => $request->string('email')->lower()->value(),
                'password' => $request->string('password')->value(),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->string('token')->value(),
            ],
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->errorResponse(__($status), 422);
        }

        return $this->successResponse([], __($status));
    }
}
