<?php

namespace App\Modules\Shared\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
     * Get all active sessions for an admin (matched through the session payload).
     *
     * @return Collection<int, object>
     */
    public function getActiveAdminSessions(int $adminId): Collection
    {
        $currentSessionId = session()->getId();

        return $this->adminSessionRows($adminId)
            ->sortByDesc('last_activity')
            ->values()
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
     * Revoke a specific session only if it belongs to the admin.
     */
    public function revokeAdminSession(string $sessionId, int $adminId): bool
    {
        $owned = $this->adminSessionRows($adminId)->contains('id', $sessionId);

        return $owned && DB::table('sessions')->where('id', $sessionId)->delete() > 0;
    }

    /**
     * Revoke every session of an admin except the given one.
     */
    public function revokeAllOtherAdminSessions(int $adminId, string $currentSessionId): int
    {
        return $this->deleteSessionIds(
            $this->adminSessionRows($adminId)->pluck('id')->reject(fn ($id): bool => $id === $currentSessionId)
        );
    }

    /**
     * Revoke every session of an admin on every browser/device.
     */
    public function revokeAllForAdmin(int $adminId): int
    {
        return $this->deleteSessionIds($this->adminSessionRows($adminId)->pluck('id'));
    }

    /**
     * Revoke every session of a web-guard user on every browser/device.
     */
    public function revokeAllForUser(int $userId): int
    {
        return DB::table('sessions')->where('user_id', $userId)->delete();
    }

    /**
     * Session rows whose payload is authenticated as the given admin.
     *
     * @return Collection<int, object>
     */
    protected function adminSessionRows(int $adminId): Collection
    {
        $key = Auth::guard('admin')->getName();
        $rows = collect();

        DB::table('sessions')->orderBy('id')->chunk(500, function ($chunk) use ($key, $adminId, &$rows): void {
            foreach ($chunk as $row) {
                $data = @unserialize((string) base64_decode((string) $row->payload, true), ['allowed_classes' => false]);

                if (is_array($data) && isset($data[$key]) && (int) $data[$key] === $adminId) {
                    $rows->push($row);
                }
            }
        });

        return $rows;
    }

    /**
     * @param  Collection<int, string>  $ids
     */
    protected function deleteSessionIds(Collection $ids): int
    {
        $deleted = 0;

        foreach ($ids->chunk(500) as $part) {
            $deleted += DB::table('sessions')->whereIn('id', $part->all())->delete();
        }

        return $deleted;
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
