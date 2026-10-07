<?php

namespace App\Modules\SocialWidgets\Enums;

enum SocialWidgetTheme: string
{
    case Modern = 'modern';
    case Polaroid = 'polaroid';
    case Brutalist = 'brutalist';
    case Minimal = 'minimal';
    case DarkMode = 'dark-mode';
    case Neon = 'neon';
    case Glass = 'glass';
    case Retro = 'retro';
    case Soft = 'soft';
    case Corporate = 'corporate';
    case Elegant = 'elegant';
    case Forest = 'forest';
    case Ocean = 'ocean';
    case Sunset = 'sunset';

    public function label(): string
    {
        return match ($this) {
            self::DarkMode => 'Dark Mode',
            default => ucfirst($this->value),
        };
    }

    /**
     * Colour set applied to every colour control when the preset is chosen.
     *
     * @return array<string, string>
     */
    public function colors(): array
    {
        [$canvas, $surface, $text, $accent, $border] = match ($this) {
            self::Modern => ['#f3f4f6', '#ffffff', '#111827', '#3b82f6', '#e5e7eb'],
            self::Polaroid => ['#fdf6f0', '#ffffff', '#1f2937', '#e11d48', '#f3e8e2'],
            self::Brutalist => ['#ffffff', '#ffffff', '#000000', '#000000', '#000000'],
            self::Minimal => ['#ffffff', '#ffffff', '#111111', '#111111', '#f1f1f1'],
            self::DarkMode => ['#0b0f19', '#111827', '#f9fafb', '#60a5fa', '#1f2937'],
            self::Neon => ['#000000', '#0a0a0a', '#e0f2fe', '#e879f9', '#22d3ee'],
            self::Glass => ['#e0e7ff', '#ffffffb3', '#1e293b', '#6366f1', '#ffffff66'],
            self::Retro => ['#fef3c7', '#fffbeb', '#78350f', '#d97706', '#fcd34d'],
            self::Soft => ['#fdf2f8', '#ffffff', '#4a044e', '#ec4899', '#fbcfe8'],
            self::Corporate => ['#f8fafc', '#ffffff', '#0f172a', '#1d4ed8', '#e2e8f0'],
            self::Elegant => ['#1c1917', '#292524', '#fafaf9', '#ca8a04', '#44403c'],
            self::Forest => ['#ecfdf5', '#ffffff', '#064e3b', '#059669', '#a7f3d0'],
            self::Ocean => ['#ecfeff', '#ffffff', '#164e63', '#0891b2', '#a5f3fc'],
            self::Sunset => ['#fff7ed', '#ffffff', '#7c2d12', '#ea580c', '#fed7aa'],
        };

        return [
            'canvasBackground' => $canvas,
            'headerBackground' => $surface,
            'headerText' => $text,
            'headerButton' => $accent,
            'postBackground' => $surface,
            'postText' => $text,
            'postBorder' => $border,
            'popupBackground' => $surface,
            'popupText' => $text,
            'loadMoreBackground' => $accent,
            'loadMoreText' => '#ffffff',
            'loadMoreBorder' => $accent,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function swatch(): array
    {
        $colors = $this->colors();

        return [$colors['canvasBackground'], $colors['headerButton']];
    }
}
