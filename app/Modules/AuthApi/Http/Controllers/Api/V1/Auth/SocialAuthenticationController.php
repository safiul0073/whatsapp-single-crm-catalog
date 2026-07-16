<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\MobileSocialLoginRequest;
use App\Modules\AuthApi\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Modules\AuthApi\Services\ApiSessionService;
use App\Modules\AuthApi\Services\SocialAccountService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialAuthenticationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SocialAccountService $socialAccountService,
        protected ApiSessionService $apiSessionService,
    ) {}

    #[OA\Get(path: '/api/v1/auth/social/{provider}/redirect')]
    #[OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'google'))]
    #[OA\Response(response: 302, description: 'Redirect to provider')]
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    #[OA\Get(path: '/api/v1/auth/social/{provider}/callback')]
    #[OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'google'))]
    #[OA\Response(response: 200, description: 'Social login successful')]
    public function callback(string $provider): JsonResponse
    {
        $this->ensureProviderIsSupported($provider);

        $providerUser = Socialite::driver($provider)->stateless()->user();
        $user = $this->socialAccountService->resolveOrCreate($provider, $providerUser);

        if (! $user->is_active) {
            return $this->errorResponse('Your account is inactive.', 403);
        }

        $token = $this->apiSessionService->createToken(
            $user,
            request(),
            request()->userAgent() ?: $provider.'-web',
            'login.api.social',
        );

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user->loadMissing('roles')),
        ], ucfirst($provider).' login successful.');
    }

    #[OA\Post(path: '/api/v1/auth/social/{provider}/mobile')]
    #[OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'google'))]
    #[OA\Response(response: 200, description: 'Mobile social login successful')]
    #[OA\Response(response: 422, description: 'Invalid provider token')]
    public function mobile(MobileSocialLoginRequest $request, string $provider): JsonResponse
    {
        $this->ensureProviderIsSupported($provider);

        $providerUser = Socialite::driver($provider)
            ->stateless()
            ->userFromToken($request->string('access_token')->value());

        $user = $this->socialAccountService->resolveOrCreate($provider, $providerUser);

        if (! $user->is_active) {
            return $this->errorResponse('Your account is inactive.', 403);
        }

        $token = $this->apiSessionService->createToken(
            $user,
            $request,
            $request->input('device_name', $provider.'-mobile'),
            'login.api.social.mobile',
        );

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user->loadMissing('roles')),
        ], ucfirst($provider).' mobile login successful.');
    }

    protected function ensureProviderIsSupported(string $provider): void
    {
        $driverConfig = config("services.{$provider}");

        if (! is_array($driverConfig) || empty($driverConfig['client_id']) || empty($driverConfig['client_secret'])) {
            throw new NotFoundHttpException("Unsupported social provider [{$provider}].");
        }
    }
}
