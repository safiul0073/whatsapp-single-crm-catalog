<?php

namespace App\Modules\AuthApi\Services;

use App\Enums\NotificationTemplateSlug;
use App\Events\UserAutoNotification;
use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\AuthApi\Models\SocialAccount;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Spatie\Permission\Models\Role;

class SocialAccountService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function resolveOrCreate(string $provider, ProviderUser $providerUser): User
    {
        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUser->getId())
            ->first();

        if ($socialAccount?->user) {
            $this->syncAccount($socialAccount, $providerUser);
            $this->markEmailVerifiedFromProvider($socialAccount->user, $providerUser);

            return $socialAccount->user;
        }

        $email = $providerUser->getEmail();

        $user = $email
            ? User::query()->where('email', $email)->first()
            : null;

        if (! $user) {
            Role::findOrCreate('user', 'web');

            $user = User::query()->create([
                'name' => $providerUser->getName() ?: $providerUser->getNickname() ?: ucfirst($provider).' User',
                'email' => $email ?? Str::uuid().'@'.$provider.'.local',
                'password' => Str::password(32),
                'is_active' => true,
                'avatar' => $providerUser->getAvatar(),
            ]);

            $this->markEmailVerifiedFromProvider($user, $providerUser);

            $user->syncRoles(['user']);

            event(new Registered($user));
            event(new UserAutoNotification($user, NotificationTemplateSlug::WELCOME));

            $this->auditLogService->logCustom('register.social', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => $provider,
            ]);
        }

        $this->markEmailVerifiedFromProvider($user, $providerUser);

        $account = SocialAccount::query()->firstOrNew([
            'provider' => $provider,
            'provider_user_id' => $providerUser->getId(),
        ]);

        $account->user()->associate($user);
        $this->syncAccount($account, $providerUser);

        return $user;
    }

    protected function syncAccount(SocialAccount $socialAccount, ProviderUser $providerUser): void
    {
        $socialAccount->fill([
            'provider_email' => $providerUser->getEmail(),
            'provider_avatar' => $providerUser->getAvatar(),
            'access_token' => $providerUser->token ?? null,
            'refresh_token' => $providerUser->refreshToken ?? null,
            'token_expires_at' => isset($providerUser->expiresIn)
                ? now()->addSeconds((int) $providerUser->expiresIn)
                : null,
        ]);

        $socialAccount->save();
    }

    protected function markEmailVerifiedFromProvider(User $user, ProviderUser $providerUser): void
    {
        $providerEmail = $providerUser->getEmail();

        if (! $providerEmail || strcasecmp((string) $user->email, (string) $providerEmail) !== 0 || $user->email_verified_at) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }
}
