<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Modules\AuthApi\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Modules\AuthApi\Services\ApiSessionService;
use App\Modules\AuthApi\Services\AuthChallengeService;
use App\Modules\AuthApi\Services\OtpDeliveryService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApiSessionService $apiSessionService,
        protected AuthChallengeService $authChallengeService,
        protected OtpDeliveryService $otpDeliveryService,
        protected LoginActivityService $loginActivityService,
    ) {}

    #[OA\Post(path: '/api/v1/auth/login')]
    #[OA\Response(response: 200, description: 'Login successful')]
    #[OA\Response(response: 422, description: 'Invalid credentials')]
    #[OA\Response(response: 429, description: 'Too many attempts')]
    public function store(LoginRequest $request): JsonResponse
    {
        $throttleKey = $this->throttleKey($request->input('email'), $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->loginActivityService->recordLockout(User::class, $request);

            return $this->errorResponse(
                'Too many login attempts. Please try again later.',
                429,
            );
        }

        $user = User::query()->where('email', $request->string('email')->lower()->value())->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            RateLimiter::hit($throttleKey);
            $this->loginActivityService->recordFailed(User::class, $request, [
                'email' => $request->input('email'),
            ]);

            return $this->errorResponse('Invalid credentials.', 422);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Your account is inactive.', 403);
        }

        RateLimiter::clear($throttleKey);

        $deviceName = $request->input('device_name', $request->userAgent() ?: 'api-device');

        if ($user->hasOtpTwoFactorEnabled()) {
            $delivery = $this->otpDeliveryService->destinationFor(
                $request,
                $user,
                $user->otp_two_factor_channel,
            );

            $this->otpDeliveryService->send(
                channel: $delivery['channel'],
                destination: $delivery['destination'],
            );

            return $this->successResponse([
                'requires_two_factor' => true,
                'channel' => $delivery['channel'],
                'destination' => $this->otpDeliveryService->mask($delivery['channel'], $delivery['destination']),
                ...$this->authChallengeService->issue($user, $deviceName, $delivery['channel'], $delivery['destination']),
            ], 'Two-factor authentication required.');
        }

        $token = $this->apiSessionService->createToken($user, $request, $deviceName);

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user->loadMissing('roles')),
        ], 'Login successful.');
    }

    protected function throttleKey(string $email, ?string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ipAddress);
    }
}
