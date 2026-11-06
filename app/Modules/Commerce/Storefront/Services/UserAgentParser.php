<?php

namespace App\Modules\Commerce\Storefront\Services;

/**
 * Lightweight user-agent classification for visitor analytics (device type, platform, browser).
 */
class UserAgentParser
{
    /** @var array<string, string> Display names for stored keys. */
    public const LABELS = [
        'ios' => 'iOS', 'android' => 'Android', 'windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'chromeos' => 'ChromeOS',
        'chrome' => 'Chrome', 'safari' => 'Safari', 'edge' => 'Edge', 'firefox' => 'Firefox', 'samsung' => 'Samsung Internet', 'opera' => 'Opera',
        'facebook' => 'Facebook app', 'instagram' => 'Instagram app',
        'mobile' => 'Mobile', 'desktop' => 'Desktop', 'tablet' => 'Tablet', 'bot' => 'Bot', 'other' => 'Other', 'unknown' => 'Unknown',
    ];

    /** @var array<string, string> Phosphor icons for platforms and device types. */
    public const ICONS = [
        'ios' => 'ph-apple-logo', 'macos' => 'ph-apple-logo', 'android' => 'ph-android-logo', 'windows' => 'ph-windows-logo', 'linux' => 'ph-linux-logo',
        'chromeos' => 'ph-google-chrome-logo', 'mobile' => 'ph-device-mobile', 'tablet' => 'ph-device-tablet', 'desktop' => 'ph-desktop', 'bot' => 'ph-robot',
    ];

    public static function label(?string $key): string
    {
        return __(self::LABELS[$key ?: 'unknown'] ?? ucfirst((string) $key));
    }

    public function device(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $agent)) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet/i', $agent) || (preg_match('/android/i', $agent) && ! preg_match('/mobi/i', $agent))) {
            return 'tablet';
        }

        return preg_match('/mobi|android|iphone/i', $agent) ? 'mobile' : 'desktop';
    }

    public function os(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/iphone|ipad|ipod|ios/i', $agent) => 'ios',
            (bool) preg_match('/android/i', $agent) => 'android',
            (bool) preg_match('/windows/i', $agent) => 'windows',
            (bool) preg_match('/cros/i', $agent) => 'chromeos',
            (bool) preg_match('/macintosh|mac os x/i', $agent) => 'macos',
            (bool) preg_match('/linux|x11/i', $agent) => 'linux',
            default => 'other',
        };
    }

    public function browser(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/FBAN|FBAV|FB_IAB/i', $agent) => 'facebook',
            (bool) preg_match('/Instagram/i', $agent) => 'instagram',
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $agent) => 'edge',
            (bool) preg_match('/OPR\/|Opera/i', $agent) => 'opera',
            (bool) preg_match('/SamsungBrowser/i', $agent) => 'samsung',
            (bool) preg_match('/Firefox|FxiOS/i', $agent) => 'firefox',
            (bool) preg_match('/Chrome|CriOS|Chromium/i', $agent) => 'chrome',
            (bool) preg_match('/Safari/i', $agent) => 'safari',
            default => 'other',
        };
    }
}
