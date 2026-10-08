<?php

namespace App\Modules\SocialWidgets\Enums;

enum SocialWidgetLayout: string
{
    case Default = 'default';
    case Grid = 'grid';
    case Slider = 'slider';
    case Masonry = 'masonry';
    case Highlight = 'highlight';
    case Bento = 'bento';
    case Polaroid = 'polaroid';
    case Filmstrip = 'filmstrip';
    case Shape = 'shape';
    case Neon = 'neon';
    case TikTok = 'tiktok';

    public static function recommendedFor(string $provider): self
    {
        return self::Default;
    }

    public function number(): string
    {
        return str_pad((string) (array_search($this, self::cases(), true) + 1), 2, '0', STR_PAD_LEFT);
    }

    public function label(): string
    {
        return match ($this) {
            self::TikTok => 'TikTok',
            default => ucfirst($this->value),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => 'Native-style layout that matches each platform\'s own UI',
            self::Grid => 'Display posts in a grid layout',
            self::Slider => 'Display posts in a slider layout',
            self::Masonry => 'Dynamic pinterest-style layout',
            self::Highlight => 'Spotlight featured content',
            self::Bento => 'Modern asymmetric grid layout',
            self::Polaroid => 'Nostalgic, messy pile of memories',
            self::Filmstrip => 'Continuous scrolling movie film strip',
            self::Shape => 'Geometric shaped grid layout',
            self::Neon => 'Futuristic neon glow aesthetic',
            self::TikTok => 'TikTok-style profile page with vertical video posts',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Default => 'ph-sparkle',
            self::Grid => 'ph-grid-nine',
            self::Slider => 'ph-arrows-horizontal',
            self::Masonry => 'ph-stack',
            self::Highlight => 'ph-star',
            self::Bento => 'ph-squares-four',
            self::Polaroid => 'ph-camera',
            self::Filmstrip => 'ph-film-strip',
            self::Shape => 'ph-shapes',
            self::Neon => 'ph-lightning',
            self::TikTok => 'ph-device-mobile',
        };
    }
}
