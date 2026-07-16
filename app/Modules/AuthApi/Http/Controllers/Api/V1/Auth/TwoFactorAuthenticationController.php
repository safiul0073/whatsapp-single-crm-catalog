<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\ConfirmTwoFactorSetupRequest;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\DisableTwoFactorRequest;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\RecoveryCodeRequest;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\SetupOtpTwoFactorRequest;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\TwoFactorChallengeRequest;
use App\Modules\AuthApi\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Modules\AuthApi\Services\ApiSessionService;
use App\Modules\AuthApi\Services\AuthChallengeService;
use App\Modules\AuthApi\Services\OtpDeliveryService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class TwoFactorAuthenticationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OtpDeliveryService $otpDeliveryService,
        protected AuthChallengeService $authChallengeService,
        protected ApiSessionService $apiSessionService,
    ) {}

    #[OA\Post(path: '/api/v1/auth/2fa/setup')]
    #[OA\Response(response: 200, description: 'Two-factor setup initialized')]
    public function setup(SetupOtpTwoFactorRequest $request): JsonResponse
    {
        $delivery = $this->otpDeliveryService->destinationFor(
            $request,
            $request->user(),
            $request->string('channel')->value(),
        );

        $this->otpDeliveryService->send(
            channel: $delivery['channel'],
            destination: $delivery['destination'],
            purpose: 'two-factor setup',
        );

        return $this->successResponse([
            'channel' => $delivery['channel'],
            'destination' => $this->otpDeliveryService->mask($delivery['channel'], $delivery['destination']),
            ...$this->authChallengeService->issue(
                $request->user(),
                '2fa-setup',
                $delivery['channel'],
                $delivery['destination'],
            ),
        ], 'Two-factor setup initialized.');
    }

    #[OA\Post(path: '/api/v1/auth/2fa/confirm')]
    #[OA\Response(response: 200, description: 'Two-factor enabled')]
    #[OA\Response(response: 422, description: 'Invalid confirmation code')]
    public function confirmSetup(ConfirmTwoFactorSetupRequest $request): JsonResponse
    {
        $payload = $this->authChallengeService->consume($request->string('challenge_token')->value());

        if (! is_array($payload)) {
            return $this->errorResponse('The setup session expired.', 422);
        }

        $this->otpDeliveryService->verify(
            $payload['channel'],
            $payload['destination'],
            $request->string('code')->value(),
        );

        $request->user()->forceFill([
            'otp_two_factor_enabled' => true,
            'otp_two_factor_channel' => $payload['channel'],
        ])->save();

        return $this->successResponse([
            'user' => new AuthenticatedUserResource($request->user()->fresh()->loadMissing('roles')),
        ], 'Two-factor authentication enabled.');
    }

    #[OA\Post(path: '/api/v1/auth/2fa/disable')]
    #[OA\Response(response: 200, description: 'Two-factor disabled')]
    #[OA\Response(response: 422, description: 'Invalid password')]
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('password')->value(), $user->password)) {
            return $this->errorResponse('The provided password is incorrect.', 422);
        }

        $user->forceFill([
            'otp_two_factor_enabled' => false,
            'otp_two_factor_channel' => null,
        ])->save();

        return $this->successResponse([
            'user' => new AuthenticatedUserResource($user->fresh()->loadMissing('roles')),
        ], 'Two-factor authentication disabled.');
    }

    #[OA\Post(path: '/api/v1/auth/2fa/verify')]
    #[OA\Response(response: 200, description: 'Two-factor verification successful')]
    #[OA\Response(response: 422, description: 'Invalid challenge or code')]
    public function verifyChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $payload = $this->authChallengeService->consume($request->string('challenge_token')->value());

        if (! is_array($payload)) {
            return $this->errorResponse('The two-factor challenge has expired.', 422);
        }

        $user = $request->user() ?? User::query()->find($payload['user_id'] ?? null);

        if (! $user || ! $user->hasOtpTwoFactorEnabled()) {
            return $this->errorResponse('Two-factor authentication is not available for this account.', 422);
        }

        $this->otpDeliveryService->verify(
            $payload['channel'],
            $payload['destination'],
            $request->string('otp')->value(),
        );

        $token = $this->apiSessionService->createToken(
            $user,
            $request,
            $payload['device_name'] ?? 'api-device',
        );

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user->loadMissing('roles')),
        ], 'Two-factor verification successful.');
    }

    #[OA\Post(path: '/api/v1/auth/2fa/recovery')]
    #[OA\Response(response: 200, description: 'Recovery code accepted')]
    #[OA\Response(response: 422, description: 'Invalid challenge or recovery code')]
    public function verifyRecoveryCode(RecoveryCodeRequest $request): JsonResponse
    {
        return $this->errorResponse('Recovery codes are not available for delivery-based two-factor authentication.', 422);
    }
}
