<?php

use App\Models\User;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsService;
use Database\Seeders\WaProLandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the WaPro marketing feature pricing faq and contact pages', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);
    $this->get(route('features'))
        ->assertOk()
        ->assertSee('One platform for everything you do on WhatsApp');

    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('Simple pricing that scales with your volume')
        ->assertSee('Choose Growth');

    $this->get(route('faqs'))
        ->assertOk()
        ->assertSee('Frequently asked questions')
        ->assertSee('faq-item is-open', false)
        ->assertSee('aria-expanded="true"', false);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Talk to the WaPro team')
        ->assertSee('ph ph-envelope-simple', false)
        ->assertSee('ph ph-phone', false)
        ->assertSee('ph ph-map-pin', false)
        ->assertSee('name="first_name"', false)
        ->assertSee(route('contact.submit'), false);
});

it('renders dynamic legal footer pages from frontend sections', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $legalPages = [
        'privacy-policy' => ['Privacy Policy', 'How WaPro collects, uses, and protects personal information'],
        'terms-and-conditions' => ['Terms & Conditions', 'The basic terms that govern access to and use of WaPro'],
        'confidentiality-privacy' => ['Confidentiality & Privacy', 'How WaPro treats customer workspace information'],
        'legal-information' => ['Legal Information', 'General company, compliance, and legal contact information'],
        'cookie-policy' => ['Cookie Policy', 'How WaPro uses cookies and similar technologies'],
    ];

    foreach ($legalPages as $slug => [$heading, $bodyText]) {
        $this->get(route('frontend.page', $slug))
            ->assertOk()
            ->assertSee($heading)
            ->assertSee($bodyText)
            ->assertDontSee('<strong>', false);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/privacy-policy', false)
        ->assertSee('/terms-and-conditions', false)
        ->assertSee('/confidentiality-privacy', false)
        ->assertSee('/legal-information', false)
        ->assertSee('/cookie-policy', false)
        ->assertSee('Cookie Policy');
});

it('shows the cookie consent popup on frontend pages when enabled', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-cookie-consent', false)
        ->assertSee('We use cookies')
        ->assertSee('/cookie-policy', false);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('data-cookie-consent', false);
});

it('renders validator-safe classic frontend markup for common public pages', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $homeHtml = $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('frontend.theme-css', ['theme' => 'classic']), false)
        ->assertSee('href="tel:+14065550120"', false)
        ->getContent();

    expect($homeHtml)
        ->not->toContain('aria-label="Brand statement marquee"')
        ->not->toContain('frameborder=')
        ->not->toContain('src=""')
        ->not->toContain('<style')
        ->not->toMatch('/<input[^>]+type="hidden"[^>]+autocomplete=/i')
        ->not->toMatch('/href="tel:[^"]*\s[^"]*"/i');

    $contactHtml = $this->get(route('contact'))
        ->assertOk()
        ->assertSee('href="tel:', false)
        ->getContent();

    expect($contactHtml)
        ->not->toMatch('/<input[^>]+type="hidden"[^>]+autocomplete=/i')
        ->not->toMatch('/href="tel:[^"]*\s[^"]*"/i');

    $this->get(route('frontend.theme-css', ['theme' => 'classic']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertSee('--color-primary:', false)
        ->assertSee('--color-brand-navy-ink:', false);
});

it('renders dynamic use case mockups from section data', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Campaign · Spring Sale')
        ->assertSee('Auto-reply bot')
        ->assertSee('Messages delivered')
        ->assertDontSee('Campaign online');
});

it('renders use case mockups from admin editable flat section data', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    FrontendSection::query()
        ->where('slug', 'use-cases')
        ->firstOrFail()
        ->update([
            'data' => [
                'eyebrow' => 'Use cases',
                'heading' => 'Editor managed use cases',
                'cases' => [
                    [
                        'eyebrow' => 'Growth',
                        'title' => 'Edited Campaign',
                        'description' => 'Edited campaign description.',
                        'bullets' => "First edited bullet\nSecond edited bullet",
                        'layout_direction' => 'text_left',
                        'link_text' => 'Learn more',
                        'link_url' => route('frontend.page', 'features'),
                        'visual_type' => 'campaign',
                        'campaign_name' => 'Campaign · Admin Sale',
                        'status' => 'Queued',
                        'stat_1_value' => '12k',
                        'stat_1_label' => 'Sent',
                        'stat_2_value' => '71%',
                        'stat_2_label' => 'Read',
                        'stat_3_value' => '9%',
                        'stat_3_label' => 'Replies',
                    ],
                    [
                        'eyebrow' => 'Support',
                        'title' => 'Edited Bot',
                        'description' => 'Edited bot description.',
                        'bullets' => "Bot bullet\nRouting bullet",
                        'layout_direction' => 'text_right',
                        'link_text' => 'Learn more',
                        'link_url' => route('frontend.page', 'features'),
                        'visual_type' => 'chatbot',
                        'bot_name' => 'Admin reply bot',
                        'status' => 'ready',
                        'messages' => "Need help choosing?\nI can qualify and route this.\nDone.",
                    ],
                    [
                        'eyebrow' => 'Operations',
                        'title' => 'Edited Reports',
                        'description' => 'Edited reporting description.',
                        'bullets' => "Report bullet\nExport bullet",
                        'layout_direction' => 'text_left',
                        'link_text' => 'Learn more',
                        'link_url' => route('frontend.page', 'features'),
                        'visual_type' => 'performance',
                        'delivered' => '88.8k',
                        'change' => '+41%',
                    ],
                ],
            ],
        ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Campaign · Admin Sale')
        ->assertSee('Admin reply bot')
        ->assertSee('88.8k')
        ->assertSee('+41%');
});

it('exposes admin schemas for dynamic classic section content', function (): void {
    $sectionFields = collect(config('frontend-sections'))
        ->map(fn (array $definition): array => array_keys($definition['fields'] ?? []));

    expect($sectionFields['home_services'])
        ->toContain('eyebrow_text', 'heading_line_one', 'heading_highlight', 'cta_text', 'cta_link')
        ->and($sectionFields['marketing_hero'])
        ->toContain('images')
        ->and($sectionFields['marketing_spotlight'])
        ->toContain('stat_sent', 'stat_badge', 'stat_read', 'stat_ctr', 'stat_failed', 'recipients')
        ->and($sectionFields['marketing_contact_info'])
        ->toContain('form_heading', 'submit_text', 'fields', 'interest_options');
});

it('renders spotlight stats from admin editable flat section data', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    FrontendSection::query()
        ->where('slug', 'feature-spotlight')
        ->firstOrFail()
        ->update([
            'data' => [
                'eyebrow' => 'Visibility',
                'heading' => 'Admin edited spotlight',
                'subheading' => 'Edited metrics from admin fields.',
                'cta_text' => 'See reports',
                'cta_url' => route('frontend.page', 'features'),
                'stat_sent' => '222.2k',
                'stat_badge' => '+93%',
                'stat_read' => '91%',
                'stat_ctr' => '18.2%',
                'stat_failed' => '0.01%',
                'recipients' => [
                    ['name' => 'Admin Recipient', 'status' => 'Replied'],
                ],
                'steps' => [
                    ['title' => 'Edited tracking', 'description' => 'Edited tracking copy.'],
                ],
            ],
        ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('222.2k')
        ->assertSee('+93%')
        ->assertSee('Admin Recipient')
        ->assertSee('Edited tracking');
});

it('does not show the cookie consent popup when disabled', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    Setting::query()->updateOrCreate(
        ['key' => 'cookie_popup_enabled'],
        ['value' => '0']
    );
    app(SettingsService::class)->clearCache();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-cookie-consent', false)
        ->assertSee('/cookie-policy', false);
});

it('shows dashboard instead of auth links on the landing page for signed in users', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertSee('data-auth-nav-link', false);

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('user.dashboard'), false)
        ->assertSee('Open Workspace')
        ->assertDontSee('data-auth-nav-link', false);
});
