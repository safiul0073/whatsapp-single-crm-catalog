<?php

namespace App\Modules\SocialWidgets\Support;

use App\Modules\SocialWidgets\Enums\SocialWidgetTheme;

class SocialWidgetSettings
{
    /**
     * Mirrors every control of the 4-step editor; stored settings are merged over this tree.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'source' => ['username' => null],
            'title' => ['text' => 'Follow us', 'show' => false],
            'header' => [
                'show' => true,
                'elements' => [
                    'profilePicture' => true,
                    'fullName' => true,
                    'username' => true,
                    'verifiedBadge' => true,
                    'stats' => true,
                    'followButton' => true,
                ],
                'gap' => 12,
            ],
            'columns' => ['mode' => 'auto', 'count' => 3, 'gap' => 3, 'width' => 1200],
            'post' => ['caption' => true, 'likes' => true, 'comments' => true, 'limit' => 12],
            'click' => [
                'action' => 'popup',
                'popup' => [
                    'header' => false,
                    'caption' => true,
                    'comments' => true,
                    'followButton' => true,
                    'counts' => true,
                    'shareButton' => false,
                ],
                'swipe' => true,
            ],
            'style' => [
                'theme' => SocialWidgetTheme::Modern->value,
                'radius' => 12,
                'borderWidth' => 1,
                'font' => 'sans',
                'loadMoreBorderWidth' => 0,
                'loadMoreRadius' => 0,
                'colors' => SocialWidgetTheme::Modern->colors(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function merge(?array $stored): array
    {
        return array_replace_recursive(self::defaults(), $stored ?? []);
    }
}
