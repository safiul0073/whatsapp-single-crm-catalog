<?php

namespace App\Modules\Shared\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /**
     * Get all active sessions for a given user.
     *
     * @return Collection<int, object>
     */
    public function getActiveSessions(int $userId): Collection
    {
        $currentSessionId = session()->getId();

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                $parsed = $this->parseUserAgent($session->user_agent ?? '');

                return (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'device' => $parsed['device'],
                    'browser' => $parsed['browser'],
                    'platform' => $parsed['platform'],
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                    'is_current' => $session->id === $currentSessionId,
                ];
            });
    }

    /**
     * Revoke a specific session (verify it belongs to the user).
     */
    public function revokeSession(string $sessionId, int $userId): bool
    {
        return DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /**
     * Revoke all sessions for a user except the current one.
     */
    public function revokeAllOtherSessions(int $userId, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
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
