<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendSection;
use InvalidArgumentException;

class ThemeRenderService
{
    public function __construct(
        protected ThemeRegistry $themes,
        protected ThemeSettingsService $themeSettings,
        protected FrontendTranslationService $translations
    ) {}

    public function layoutView(string $themeKey, ?string $layoutKey): string
    {
        $theme = $this->themes->get($themeKey);

        if (! $theme) {
            throw new InvalidArgumentException("Unknown theme [{$themeKey}]");
        }

        $layoutKey = $layoutKey ?: $this->themes->defaultLayoutKey($themeKey);
        $layout = $theme['page_layouts'][$layoutKey] ?? null;

        if ($layout) {
            $candidate = $theme['view_namespace'].'.'.$layout['view'];

            if (view()->exists($candidate)) {
                return $candidate;
            }
        }

        $defaultLayoutKey = $this->themes->defaultLayoutKey($themeKey);
        $defaultLayout = $theme['page_layouts'][$defaultLayoutKey] ?? null;
        $fallback = $defaultLayout ? $theme['view_namespace'].'.'.$defaultLayout['view'] : 'frontend.shared.layouts.page';

        return view()->exists($fallback) ? $fallback : 'frontend.shared.layouts.page';
    }

    public function sectionView(string $themeKey, FrontendSection $section): array
    {
        $theme = $this->themes->get($themeKey);
        $supported = $this->themes->supportsSection($themeKey, $section->type);

        if ($theme && $supported) {
            $themeSpecific = $theme['view_namespace'].'.sections.'.$section->type;

            if (view()->exists($themeSpecific)) {
                return ['view' => $themeSpecific, 'supported' => true];
            }

            $sharedView = 'frontend.shared.sections.'.$section->type;

            if (view()->exists($sharedView)) {
                return ['view' => $sharedView, 'supported' => true];
            }
        }

        return [
            'view' => $theme['fallback_renderer'] ?? 'frontend.shared.sections.unsupported',
            'supported' => false,
        ];
    }

    public function themeVariables(string $themeKey): array
    {
        $s = fn (string $key, mixed $default = '') => (string) $this->themeSettings->getThemeSetting($themeKey, $key, $default);

        $vars = [
            // Branding
            'logo_text' => $s('logo_text', $this->themes->get($themeKey)['label'] ?? ucfirst($themeKey)),
            'primary_color' => $s('primary_color', '#111827'),
            'accent_color' => $s('accent_color', '#1f2937'),
            'show_hero_kicker' => (bool) $this->themeSettings->getThemeSetting($themeKey, 'show_hero_kicker', true),
            'uppercase_headings' => (bool) $this->themeSettings->getThemeSetting($themeKey, 'uppercase_headings', false),

            // Footer — identity
            'footer_tagline' => $s('footer_tagline', 'A senior product team for founders and operators. We design, build, and ship modern SaaS, web, and mobile products — from discovery to launch.'),
            'footer_email' => $s('footer_email', 'hello@example.com'),
            'footer_address' => $s('footer_address', 'Remote-first · HQ in San Francisco · CA, United States'),
            // Footer — newsletter
            'footer_newsletter_heading' => $s('footer_newsletter_heading', 'Field notes from real product builds'),
            'footer_newsletter_subheading' => $s('footer_newsletter_subheading', 'Stay in the loop'),
            'footer_newsletter_disclaimer' => $s('footer_newsletter_disclaimer', 'Engineering write-ups, post-mortems, and the occasional launch. No spam.'),

            // Footer — social
            'footer_social_linkedin' => $s('footer_social_linkedin', ''),
            'footer_social_twitter' => $s('footer_social_twitter', ''),
            'footer_social_github' => $s('footer_social_github', ''),
            'footer_social_dribbble' => $s('footer_social_dribbble', ''),

            // Footer — CTA banner
            'footer_cta_badge' => $s('footer_cta_badge', 'Now booking · Q3 builds'),
            'footer_cta_heading' => $s('footer_cta_heading', "Have an idea worth shipping? Let's scope it together."),
            'footer_cta_subheading' => $s('footer_cta_subheading', 'Free 30-minute scope call with the team that would actually build it. Reply within 48 hours.'),
            'footer_cta_primary_text' => $s('footer_cta_primary_text', 'Start a project'),
            'footer_cta_primary_link' => $s('footer_cta_primary_link', '/contact'),
            'footer_cta_ghost_text' => $s('footer_cta_ghost_text', 'Or talk to sales'),
            'footer_cta_ghost_link' => $s('footer_cta_ghost_link', '#contact'),

            // Footer — feedback banner
            'footer_feedback_heading' => $s('footer_feedback_heading', 'We Value Your Feedback'),
            'footer_feedback_subheading' => $s('footer_feedback_subheading', 'Share your thoughts with us to help improve your experience!'),
            'footer_feedback_link' => $s('footer_feedback_link', '#feedback'),

            // Footer — bottom bar
            'footer_copyright' => $s('footer_copyright', 'Classic theme. Crafted with care.'),
            'footer_link_terms' => $s('footer_link_terms', '#'),
            'footer_link_privacy' => $s('footer_link_privacy', '#'),
            'footer_link_cookies' => $s('footer_link_cookies', '#'),

            // Navigation
            'marketplace_url' => $s('marketplace_url', 'https://themeforest.net/user/pixelaxis'),

            // Marketing
            'show_auth_links' => (bool) $this->themeSettings->getThemeSetting($themeKey, 'show_auth_links', true),
            'sign_in_text' => $s('sign_in_text', 'Sign in'),
            'sign_up_text' => $s('sign_up_text', 'Sign up'),
            'footer_phone' => $s('footer_phone', '+1 (406) 555-0120'),
            'footer_social_facebook' => $s('footer_social_facebook', '#'),
            'footer_social_x' => $s('footer_social_x', '#'),
            'footer_social_instagram' => $s('footer_social_instagram', '#'),
        ];

        foreach ($vars as $key => $value) {
            if (is_string($value)) {
                $vars[$key] = $this->translations->translateText($value, $key);
            }
        }

        return $vars;
    }
}
