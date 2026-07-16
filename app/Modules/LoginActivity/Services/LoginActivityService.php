<?php

namespace App\Modules\LoginActivity\Services;

use App\Modules\LoginActivity\Models\LoginActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LoginActivityService
{
    /**
     * Record a login event.
     */
    public function recordLogin(string $userType, int $userId, Request $request): LoginActivity
    {
        return $this->record('login', $userType, $userId, $request);
    }

    /**
     * Record a logout event.
     */
    public function recordLogout(string $userType, int $userId, Request $request): LoginActivity
    {
        return $this->record('logout', $userType, $userId, $request);
    }

    /**
     * Record a failed login attempt.
     */
    public function recordFailed(string $userType, Request $request, ?array $metadata = null): LoginActivity
    {
        return $this->record('failed', $userType, null, $request, $metadata);
    }

    /**
     * Record a lockout event.
     */
    public function recordLockout(string $userType, Request $request): LoginActivity
    {
        return $this->record('lockout', $userType, null, $request, [
            'email' => $request->input('email'),
        ]);
    }

    /**
     * Record the start of a user impersonation.
     *
     * @param  array{impersonated_user_id: int, impersonated_user_name: string, impersonated_user_email: string}  $metadata
     */
    public function recordImpersonateStart(string $userType, int $adminId, Request $request, array $metadata): LoginActivity
    {
        return $this->record('impersonate_start', $userType, $adminId, $request, $metadata);
    }

    /**
     * Record the end of a user impersonation.
     *
     * @param  array{impersonated_user_id: int|null, impersonated_user_name: string|null}  $metadata
     */
    public function recordImpersonateStop(string $userType, int $adminId, Request $request, array $metadata): LoginActivity
    {
        return $this->record('impersonate_stop', $userType, $adminId, $request, $metadata);
    }

    /**
     * Get recent login activities for a specific user.
     */
    public function getRecentForUser(string $userType, int $userId, int $limit = 10): Collection
    {
        return LoginActivity::forUser($userType, $userId)
            ->recent($limit)
            ->get();
    }

    /**
     * Get paginated login activities with filters.
     */
    public function listPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LoginActivity::with('user');

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', 'like', '%'.$filters['ip_address'].'%');
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('device', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Record a login activity event.
     */
    protected function record(string $event, string $userType, ?int $userId, Request $request, ?array $metadata = null): LoginActivity
    {
        $userAgent = $request->userAgent() ?? '';
        $parsed = $this->parseUserAgent($userAgent);

        return LoginActivity::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Parse user agent string for device, browser, and platform.
     *
     * @return array{device: string, browser: string, platform: string}
     */
    protected function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $platform = 'Unknown';
        $device = 'Desktop';

        // Detect platform
        if (preg_match('/windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $platform = 'iOS';
        }

        // Detect browser
        if (preg_match('/edg\//i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/opr\//i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/chrome/i', $userAgent) && ! preg_match('/edg/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent) && ! preg_match('/chrome/i', $userAgent)) {
            $browser = 'Safari';
        }

        // Detect device type
        if (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
            $device = 'Mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            $device = 'Tablet';
        }

        return compact('device', 'browser', 'platform');
    }
}
