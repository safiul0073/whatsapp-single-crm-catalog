<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Feature highlight icons: the owner's preset list (Storefront settings) and the
 * full Phosphor icon set for the "More icons…" picker.
 */
class FeatureIconCatalog
{
    public const ICON_RULE = 'regex:/^ph-[a-z0-9-]+$/';

    /** @var array<int, array{icon: string, label: string}> */
    public const DEFAULTS = [
        ['icon' => 'ph-t-shirt', 'label' => 'T-Shirt / Fleece'],
        ['icon' => 'ph-arrows-out-line-vertical', 'label' => 'Zipper / Set'],
        ['icon' => 'ph-users-three', 'label' => 'Users / Kids'],
        ['icon' => 'ph-palette', 'label' => 'Palette / Colors'],
        ['icon' => 'ph-ruler', 'label' => 'Ruler / True Size'],
        ['icon' => 'ph-seal-check', 'label' => 'Quality Seal'],
        ['icon' => 'ph-buildings', 'label' => 'Factory Direct'],
        ['icon' => 'ph-globe', 'label' => 'Worldwide'],
        ['icon' => 'ph-lock-key', 'label' => 'Secure'],
    ];

    /**
     * @return array<int, array{icon: string, label: string}>
     */
    public function presets(int $workspaceId): array
    {
        $config = (array) (StorefrontSetting::query()->where('workspace_id', $workspaceId)->value('config') ?? []);
        $saved = $config['feature_icons'] ?? null;

        return is_array($saved) ? $this->clean($saved) : self::DEFAULTS;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{icon: string, label: string}>
     */
    public function clean(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $icon = trim((string) ($item['icon'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($icon !== '' && $label !== '' && preg_match('/^ph-[a-z0-9-]+$/', $icon) === 1) {
                $out[] = ['icon' => $icon, 'label' => mb_substr($label, 0, 40)];
            }
        }

        return array_values($out);
    }

    /**
     * Every Phosphor icon class available in the bundled font, e.g. "ph-ruler".
     *
     * @return array<int, string>
     */
    public function allIcons(): array
    {
        $path = public_path('vendor/phosphor/regular/style.css');
        if (! is_file($path)) {
            return array_column(self::DEFAULTS, 'icon');
        }

        return Cache::rememberForever('commerce.phosphor-icons.'.filemtime($path), function () use ($path): array {
            preg_match_all('/\.ph\.(ph-[a-z0-9-]+):before/', (string) file_get_contents($path), $matches);

            return array_values(array_unique($matches[1]));
        });
    }
}
