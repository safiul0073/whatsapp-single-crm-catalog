<?php

namespace Database\Seeders;

use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\FrontendThemeSetting;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\MenuService;
use App\Modules\Frontend\Services\PageComposerService;
use Illuminate\Database\Seeder;

class WaProLandingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedThemeSettings();
        $sectionIdsBySlug = $this->seedSections();
        $this->seedPages($sectionIdsBySlug);
        $this->seedMenus();
    }

    private function seedThemeSettings(): void
    {
        $settings = [
            'active_theme' => 'classic',
            'theme.classic.primary_color' => '#25D366',
            'theme.classic.accent_color' => '#F59E0B',
            'theme.classic.logo_text' => 'WaPro',
            'theme.classic.footer_email' => 'hello@wapro.com',
            'theme.classic.footer_phone' => '+1 (406) 555-0120',
            'theme.classic.footer_address' => '123 Business Street, Suite 456, New York, NY 10001, USA',
            'theme.classic.footer_newsletter_heading' => 'Newsletter',
            'theme.classic.footer_newsletter_subheading' => 'Subscribe to our newsletter',
            'theme.classic.footer_copyright' => '2026 WaPro. All rights reserved.',
            'theme.classic.footer_link_terms' => '/legal-information',
            'theme.classic.footer_link_privacy' => '/confidentiality-privacy',
            'theme.classic.footer_link_cookies' => '/cookie-policy',
            'theme.classic.footer_social_facebook' => '#',
            'theme.classic.footer_social_x' => '#',
            'theme.classic.footer_social_instagram' => '',
            'theme.classic.show_auth_links' => 'true',
            'theme.classic.sign_in_text' => 'Sign in',
            'theme.classic.sign_up_text' => 'Sign up',
        ];

        foreach ($settings as $key => $value) {
            FrontendThemeSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function seedSections(): array
    {
        $definitions = [
            [
                'slug' => 'home-hero',
                'name' => 'Home Hero',
                'type' => 'marketing_hero',
                'description' => 'Hero section for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'WhatsApp Marketing Platform',
                    'heading_line_1' => 'WhatsApp',
                    'heading_line_2' => 'campaigns, done',
                    'heading_accent' => 'right.',
                    'subheading' => "Bulk sends. Chatbots. Real results.\nLive in minutes.",
                    'cta_primary_text' => 'Start for free',
                    'cta_primary_url' => '/login',
                    'cta_secondary_text' => 'Watch a tour',
                    'cta_secondary_url' => '/features',
                    'images' => [
                        ['url' => '/assets/wapro/images/hero-tedy-1.webp', 'alt' => 'A team collaborating on customer messaging'],
                        ['url' => '/assets/wapro/images/hero-tedy-2.webp', 'alt' => 'Two colleagues reviewing campaign results'],
                        ['url' => '/assets/wapro/images/hero-tedy-3.webp', 'alt' => 'A happy customer support team'],
                    ],
                ],
            ],
            [
                'slug' => 'brand-marquee',
                'name' => 'Brand Marquee',
                'type' => 'marketing_marquee',
                'description' => 'Brand statement marquee for the WaPro home page.',
                'data' => [
                    'items' => [
                        ['text' => 'Bulk campaigns', 'accent' => false],
                        ['text' => 'Auto replies', 'accent' => true],
                        ['text' => 'Smart chatbots', 'accent' => false],
                        ['text' => 'Real results', 'accent' => true],
                    ],
                ],
            ],
            [
                'slug' => 'product-modules',
                'name' => 'Product Modules',
                'type' => 'marketing_modules',
                'description' => 'Product modules accordion for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'Product modules',
                    'heading' => 'Everything you need to run WhatsApp at scale',
                    'subheading' => 'Each module supports a real operational job — from outreach and automation to contacts and reporting.',
                    'modules' => [
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>', 'label' => 'Bulk Campaigns', 'description' => 'Launch high-volume WhatsApp campaigns to segmented audiences with timing and delivery under control.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>', 'label' => 'Auto Reply', 'description' => 'Trigger automatic responses for inbound messages, off-hours support and lead capture by keyword.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 0 1 0 4h-1v3a2 2 0 0 1-2 2h-3v1a2 2 0 0 1-4 0v-1H7a2 2 0 0 1-2-2v-3H4a2 2 0 0 1 0-4h1V7a2 2 0 0 1 2-2h3V4a2 2 0 0 1 2-2z"/></svg>', 'label' => 'AI Smart Reply', 'description' => 'Generate fast, context-aware replies so agents handle conversations with less manual effort.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4m0 4a4 4 0 0 0-4 4v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a4 4 0 0 0-4-4zM9 14h.01M15 14h.01"/></svg>', 'label' => 'Chatbot', 'description' => 'Build conversational flows that qualify leads and move contacts to the next step automatically.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>', 'label' => 'Contacts', 'description' => 'Manage lists, segments and campaign targets from one structured database built for WhatsApp.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>', 'label' => 'Export Participants', 'description' => 'Extract participants from WhatsApp groups for outreach, qualification and audience building.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/></svg>', 'label' => 'Profile Info', 'description' => 'Review WhatsApp profile details quickly to enrich lead context and improve handoff quality.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                        ['icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>', 'label' => 'Reports', 'description' => 'Track campaign output, reply activity and workflow performance with operational reporting.', 'link_text' => 'Learn more', 'link_url' => '/features'],
                    ],
                ],
            ],
            [
                'slug' => 'feature-spotlight',
                'name' => 'Feature Spotlight',
                'type' => 'marketing_spotlight',
                'description' => 'Feature spotlight with sticky visuals for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'Visibility',
                    'heading' => 'Visual insights for data-driven campaigns',
                    'subheading' => 'See delivery, read and reply rates across every campaign and device.',
                    'cta_text' => 'See all features',
                    'cta_url' => '/features',
                    'stats' => ['sent' => '104.7k', 'change' => '+78%', 'read' => '86%', 'ctr' => '11.4%', 'failed' => '0.04%'],
                    'recipients' => [
                        ['name' => 'Aisha Rahman', 'status' => 'Replied'],
                        ['name' => 'Theo Sullivan', 'status' => 'Read'],
                        ['name' => 'Nadia B.', 'status' => 'Read'],
                        ['name' => 'Diego R.', 'status' => 'Delivered'],
                    ],
                    'steps' => [
                        ['title' => 'Track every recipient', 'description' => 'Per-recipient delivery, read and reply tracking across every campaign — see exactly who engaged, who slipped through, and which contacts need a timely follow-up. Your team can review each send status in one place, spot delivery issues earlier, and avoid wasting time on manual checks after every broadcast. When a campaign underperforms, the activity trail makes it easier to separate message quality, audience fit, and delivery problems before the next send goes out.'],
                        ['title' => 'Know who engaged', 'description' => 'A clear activity view turns raw sends into people: replies, reads and deliveries per contact, so sales and support teams can prioritize the conversations most likely to convert. Use those signals to segment warm leads, re-engage quiet contacts, and hand active conversations to the right teammate faster. Instead of treating every contact the same, teams can build follow-up lists from real behavior and keep customer conversations moving with better context.'],
                        ['title' => 'Test & auto-pick the winner', 'description' => 'A/B test templates, compare reply rates, and let the winner promote itself automatically. Export the full report when the team needs proof for the next campaign plan, then reuse the strongest message style for future launches, reminders, and follow-up sequences. Over time, the reporting view becomes a playbook of what your audience responds to, helping every new campaign start from proven messaging instead of guesswork.'],
                    ],
                ],
            ],
            [
                'slug' => 'how-it-works',
                'name' => 'How It Works',
                'type' => 'marketing_how_works',
                'description' => 'How it works steps for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'How it works',
                    'heading' => 'Designed as one system, not a pile of tools',
                    'subheading' => "Contacts feed campaigns, bots reduce manual load, and reports show what's actually working.",
                    'steps' => [
                        ['number' => '01', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h12"/></svg>', 'title' => 'Import & segment', 'description' => 'Bring contacts in via CSV or sync, then group them into reusable audiences and tags.'],
                        ['number' => '02', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>', 'title' => 'Automate & send', 'description' => 'Launch bulk campaigns or let chatbots and auto-replies handle inbound conversations.'],
                        ['number' => '03', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>', 'title' => 'Track & grow', 'description' => 'Measure delivery, read and reply outcomes, then export participants for new growth loops.'],
                    ],
                ],
            ],
            [
                'slug' => 'use-cases',
                'name' => 'Use Cases',
                'type' => 'marketing_use_cases',
                'description' => 'Use cases section for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'Use cases',
                    'heading' => 'For growth, support & daily operations',
                    'cases' => [
                        ['eyebrow' => 'Growth', 'title' => 'Outbound Marketing', 'description' => 'Build segmented lists, run bulk campaigns, track responses, and improve conversion using report data.', 'bullets' => ['Segmented lists & reusable audiences', 'Bulk campaigns with delivery tracking'], 'layout_direction' => 'text_left', 'link_text' => 'Learn more', 'link_url' => '/features', 'mockup_data' => ['campaign_name' => 'Campaign · Spring Sale', 'status' => 'Sent', 'stats' => [['value' => '42.1k', 'label' => 'Sent'], ['value' => '86%', 'label' => 'Read'], ['value' => '11.4%', 'label' => 'Replies']]]],
                        ['eyebrow' => 'Support', 'title' => 'Inbound Automation', 'description' => 'Use chatbots and auto-replies to handle repetitive conversations without keeping agents on every message.', 'bullets' => ['Keyword auto-replies, 24/7', 'Chatbot flows that qualify leads'], 'layout_direction' => 'text_right', 'link_text' => 'Learn more', 'link_url' => '/features', 'mockup_data' => ['bot_name' => 'Auto-reply bot', 'status' => 'online', 'messages' => ['Hi! Is the House Blend back in stock? ☕', 'Yes! Reply ORDER to grab a bag ✅', 'Order placed — shipping today 🎉']]],
                        ['eyebrow' => 'Operations', 'title' => 'Performance Visibility', 'description' => 'Use profile lookups, reports and participant export to move from scattered chats to measurable workflows.', 'bullets' => ['Exportable reports for the whole team', 'Participant export for new growth loops'], 'layout_direction' => 'text_left', 'link_text' => 'Learn more', 'link_url' => '/features', 'mockup_data' => ['delivered' => '104.7k', 'change' => '+78%']],
                    ],
                ],
            ],
            [
                'slug' => 'pricing-teaser',
                'name' => 'Pricing Teaser',
                'type' => 'marketing_pricing',
                'description' => 'Pricing teaser with 2 plans for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'Pricing',
                    'heading' => 'Pick the plan that matches your volume',
                    'subheading' => 'Clean pricing, scalable automation, and enough headroom to run sales, support and campaigns from one place.',
                    'show_yearly_toggle' => true,
                    'yearly_save_text' => 'Save 20%',
                    'plans' => [
                        ['name' => 'Starter', 'description' => 'Perfect for WhatsApp automation beginners.', 'monthly_price' => '0', 'yearly_price' => '0', 'features' => ['10,000 messages / month', 'Auto reply & chatbot', '1 WhatsApp number', 'Basic reports'], 'highlighted' => false, 'badge' => '', 'cta_text' => 'Start for free', 'cta_url' => '/login'],
                        ['name' => 'Growth', 'description' => 'For teams running WhatsApp at scale.', 'monthly_price' => '29', 'yearly_price' => '23', 'features' => ['100,000 messages / month', 'AI smart reply & content', '5 WhatsApp numbers', 'Advanced reports & export', 'Team members & roles'], 'highlighted' => true, 'badge' => 'Most popular', 'cta_text' => 'Choose Growth', 'cta_url' => '/login'],
                    ],
                    'footer_text' => 'Need more?',
                    'footer_link_text' => 'Compare all plans →',
                    'footer_link_url' => '/pricing',
                ],
            ],
            [
                'slug' => 'home-faq',
                'name' => 'Home FAQ',
                'type' => 'marketing_faq',
                'description' => 'FAQ section for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'FAQ',
                    'heading' => 'Frequently asked questions',
                    'items' => [
                        ['question' => 'Do I need a WhatsApp Business API account to start?', 'answer' => "You can connect an existing WABA, or we help you provision one via Meta's embedded signup. WaPro uses the official WhatsApp Cloud API only."],
                        ['question' => 'How fast are templates approved?', 'answer' => 'Templates are submitted straight to Meta and typically approve within minutes. Our linter flags likely-rejection issues before you submit.'],
                        ['question' => 'Can I import my existing contacts?', 'answer' => 'Yes — import via CSV with column mapping and de-duplication, then segment and tag for campaigns.'],
                        ['question' => 'Is there a free plan?', 'answer' => 'Yes. The Starter plan is free and includes 10,000 messages per month with auto-reply and chatbot.'],
                    ],
                ],
            ],
            [
                'slug' => 'home-cta',
                'name' => 'Home CTA',
                'type' => 'marketing_cta',
                'description' => 'CTA parallax banner for the WaPro home page.',
                'data' => [
                    'eyebrow' => 'Limited-time onboarding',
                    'heading' => 'Build your WhatsApp workflow on one system',
                    'subheading' => 'Launch campaigns, automate replies, manage contacts, and track performance from one workspace.',
                    'cta_primary_text' => 'Create your workspace',
                    'cta_primary_url' => '/login',
                    'cta_secondary_text' => 'View pricing',
                    'cta_secondary_url' => '/pricing',
                    'background_image' => '/assets/wapro/images/hero-tedy-1.webp',
                ],
            ],
            [
                'slug' => 'page-header-features',
                'name' => 'Page Header - Features',
                'type' => 'marketing_page_header',
                'description' => 'Page header for the Features page.',
                'data' => [
                    'eyebrow' => 'Features',
                    'heading' => 'One platform for everything you do on WhatsApp',
                    'subheading' => 'Campaigns, automation, a shared inbox, contacts, and reporting — built for the way teams run WhatsApp at scale.',
                    'cta_primary_text' => 'Start for free',
                    'cta_primary_url' => '/login',
                    'cta_secondary_text' => 'See pricing',
                    'cta_secondary_url' => '/pricing',
                ],
            ],
            [
                'slug' => 'broadcasting',
                'name' => 'Broadcasting',
                'type' => 'marketing_broadcasting',
                'description' => 'Broadcasting deep-dive section for the Features page.',
                'data' => [
                    'eyebrow' => 'Broadcasting',
                    'heading' => 'Send bulk campaigns that actually get delivered',
                    'subheading' => 'Reach segmented audiences with approved templates, controlled throttling, and scheduling — then watch delivery and replies land in real time.',
                    'bullets' => [
                        'Audience segments and saved lists',
                        'Schedule and throttle for safe sending',
                        'Live delivered / read / replied tracking',
                    ],
                    'cta_text' => 'Launch a campaign',
                    'cta_url' => '/login',
                    'visual_cards' => [
                        [
                            'type' => 'stats',
                            'heading' => 'Messages delivered',
                            'value' => '104.7k',
                            'badge' => '+78%',
                            'chart_bars' => [
                                ['height' => '40%', 'accent' => false],
                                ['height' => '30%', 'accent' => false],
                                ['height' => '95%', 'accent' => true],
                                ['height' => '55%', 'accent' => false],
                                ['height' => '48%', 'accent' => false],
                                ['height' => '70%', 'accent' => true],
                            ],
                            'stats' => [
                                ['value' => '86%', 'label' => 'Read'],
                                ['value' => '11.4%', 'label' => 'CTR'],
                                ['value' => '0.04%', 'label' => 'Failed'],
                            ],
                        ],
                        [
                            'type' => 'rows',
                            'heading' => 'Campaign status',
                            'rows' => [
                                ['label' => 'Spring promo · Segment A', 'status' => 'Delivered', 'status_type' => 'soft'],
                                ['label' => 'Re-engagement · Inactive', 'status' => 'Scheduled', 'status_type' => 'warning'],
                                ['label' => 'Order updates · All', 'status' => 'Sending', 'status_type' => 'info'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'automation-ai',
                'name' => 'Automation & AI',
                'type' => 'marketing_automation',
                'description' => 'Automation deep-dive section for the Features page.',
                'data' => [
                    'eyebrow' => 'Automation & AI',
                    'heading' => 'Automate replies and let AI handle the routine',
                    'subheading' => 'Set keyword rules, build chatbot flows, and add AI smart replies so common questions get answered instantly — day or night — while your team focuses on the conversations that matter.',
                    'bullets' => [
                        'Keyword auto-replies with match rules',
                        'Visual chatbot flows that qualify leads',
                        'AI smart replies from your knowledge base',
                    ],
                    'cta_text' => 'Build an automation',
                    'cta_url' => '/login',
                    'visual_cards' => [
                        [
                            'type' => 'rule',
                            'heading' => 'Auto-reply rule',
                            'rule_body' => '"pricing" · "plans" · "cost"',
                            'reply_preview' => 'Here are our plans 👇 wapro.com/pricing — want a recommendation?',
                        ],
                        [
                            'type' => 'progress',
                            'heading' => 'Chatbot resolution rate',
                            'badge' => 'Live',
                            'progress_value' => '64%',
                            'progress_percentage' => '64%',
                            'progress_label' => 'Resolved before reaching an agent',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'all-modules-grid',
                'name' => 'All Modules Grid',
                'type' => 'marketing_modules_grid',
                'description' => 'Full modules grid for the Features page.',
                'data' => [
                    'eyebrow' => 'Every module',
                    'heading' => 'A complete WhatsApp toolkit',
                    'subheading' => 'Each module supports a real operational job — pick what you need today and grow into the rest.',
                    'cards' => [
                        ['number' => '01', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>', 'title' => 'Bulk Campaigns', 'description' => 'Launch high-volume campaigns to segmented audiences with timing and delivery under control.'],
                        ['number' => '02', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>', 'title' => 'Auto Reply', 'description' => 'Trigger automatic responses for inbound messages, off-hours support, and lead capture by keyword.'],
                        ['number' => '03', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 0 1 0 4h-1v3a2 2 0 0 1-2 2h-3v1a2 2 0 0 1-4 0v-1H7a2 2 0 0 1-2-2v-3H4a2 2 0 0 1 0-4h1V7a2 2 0 0 1 2-2h3V4a2 2 0 0 1 2-2z"/></svg>', 'title' => 'AI Smart Reply', 'description' => 'Generate fast, context-aware replies so agents handle conversations with less manual effort.'],
                        ['number' => '04', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4m0 4a4 4 0 0 0-4 4v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a4 4 0 0 0-4-4zM9 14h.01M15 14h.01"/></svg>', 'title' => 'Chatbot', 'description' => 'Build conversational flows that qualify leads and move contacts to the next step automatically.'],
                        ['number' => '05', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>', 'title' => 'Contacts', 'description' => 'Manage lists, segments, and campaign targets from one structured database built for WhatsApp.'],
                        ['number' => '06', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>', 'title' => 'Export Participants', 'description' => 'Extract participants from WhatsApp groups for outreach, qualification, and audience building.'],
                        ['number' => '07', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V6zM4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9zM8 13h8M8 16h5"/></svg>', 'title' => 'Templates', 'description' => 'Create, submit, and reuse approved message templates with variables, buttons, and live previews.'],
                        ['number' => '08', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 13h4l2 3h4l2-3h4M4 6h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1z"/></svg>', 'title' => 'Shared Inbox', 'description' => 'Handle every conversation as a team with labels, assignments, and quick replies in one place.'],
                        ['number' => '09', 'icon_svg' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>', 'title' => 'Reports', 'description' => 'Track campaign output, reply activity, and workflow performance with operational reporting.'],
                    ],
                ],
            ],
            [
                'slug' => 'why-wapro',
                'name' => 'Why WaPro',
                'type' => 'marketing_why_wapro',
                'description' => 'Why choose WaPro section for the Features page.',
                'data' => [
                    'eyebrow' => 'Why WaPro',
                    'heading' => 'Built to run WhatsApp like a system',
                    'subheading' => 'Every module shares the same contacts, automation, and reporting — so the whole operation moves together.',
                    'reasons' => [
                        ['number' => '01', 'icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>', 'title' => 'Live in minutes', 'description' => 'Connect your number, import contacts, and send your first campaign the same day.'],
                        ['number' => '02', 'icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', 'title' => 'Safe by design', 'description' => 'Throttling and template compliance keep your number healthy as you scale sends.'],
                        ['number' => '03', 'icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>', 'title' => 'Decisions from data', 'description' => 'Delivery, read, and reply analytics turn raw sends into measurable outcomes.'],
                        ['number' => '04', 'icon_svg' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>', 'title' => 'Built for teams', 'description' => 'Shared inbox, roles, and assignments keep every agent on the same page.'],
                    ],
                    'center_label' => 'This month',
                    'center_value' => '104.7k',
                    'center_subtitle' => 'messages delivered',
                    'center_bottom_stats' => [
                        ['value' => '98%', 'label' => 'Delivery'],
                        ['value' => '86%', 'label' => 'Read'],
                        ['value' => '64%', 'label' => 'Bot solved'],
                    ],
                ],
            ],
            [
                'slug' => 'features-cta',
                'name' => 'Features CTA',
                'type' => 'marketing_cta',
                'description' => 'CTA parallax banner for the Features page.',
                'data' => [
                    'eyebrow' => 'Ready when you are',
                    'heading' => 'Put every WhatsApp module to work today',
                    'subheading' => 'Start free, connect your number, and send your first campaign in minutes.',
                    'cta_primary_text' => 'Create your workspace',
                    'cta_primary_url' => '/login',
                    'cta_secondary_text' => 'View pricing',
                    'cta_secondary_url' => '/pricing',
                    'background_image' => '/assets/wapro/images/hero-tedy-2.webp',
                ],
            ],
            [
                'slug' => 'page-header-pricing',
                'name' => 'Page Header - Pricing',
                'type' => 'marketing_page_header',
                'description' => 'Page header for the Pricing page.',
                'data' => [
                    'eyebrow' => 'Pricing',
                    'heading' => 'Simple pricing that scales with your volume',
                    'subheading' => 'Start free, then choose monthly or yearly billing as you grow. No hidden fees, cancel anytime.',
                ],
            ],
            [
                'slug' => 'pricing-full',
                'name' => 'Pricing Full',
                'type' => 'marketing_pricing',
                'description' => 'Full pricing section with 3 plans for the Pricing page.',
                'data' => [
                    'eyebrow' => 'Pricing',
                    'heading' => 'Simple pricing that scales with your volume',
                    'subheading' => 'Start free, then choose monthly or yearly billing as you grow. No hidden fees, cancel anytime.',
                    'show_billing_cycle_tabs' => true,
                    'billing_cycles' => [
                        ['key' => 'monthly', 'label' => 'Monthly', 'save_text' => ''],
                        ['key' => 'yearly', 'label' => 'Yearly', 'save_text' => 'Save 20%'],
                    ],
                    'yearly_save_text' => 'Save 20%',
                    'plans' => [
                        ['name' => 'Starter', 'description' => 'Perfect for WhatsApp automation beginners.', 'monthly_price' => '0', 'yearly_price' => '0', 'features' => ['10,000 messages / month', 'Auto reply & chatbot', '1 WhatsApp number', 'Basic reports'], 'highlighted' => false, 'badge' => '', 'cta_text' => 'Start for free', 'cta_url' => '/login'],
                        ['name' => 'Growth', 'description' => 'For teams running WhatsApp at scale.', 'monthly_price' => '29', 'yearly_price' => '23', 'features' => ['100,000 messages / month', 'AI smart reply & content', '5 WhatsApp numbers', 'Advanced reports & export', 'Team members & roles'], 'highlighted' => true, 'badge' => 'Most popular', 'cta_text' => 'Choose Growth', 'cta_url' => '/login'],
                        ['name' => 'Scale', 'description' => 'For high-volume senders and agencies.', 'monthly_price' => '79', 'yearly_price' => '63', 'features' => ['Unlimited messages', 'Everything in Growth', '20 WhatsApp numbers', 'API access & webhooks', 'Priority support'], 'highlighted' => false, 'badge' => '', 'cta_text' => 'Choose Scale', 'cta_url' => '/login'],
                    ],
                    'footer_text' => 'All plans include a 14-day money-back guarantee. Prices in USD, excluding tax.',
                ],
            ],
            [
                'slug' => 'compare-plans',
                'name' => 'Compare Plans',
                'type' => 'marketing_compare_table',
                'description' => 'Feature comparison table for the Pricing page.',
                'data' => [
                    'eyebrow' => 'Compare plans',
                    'heading' => 'Everything in each plan',
                    'subheading' => "A side-by-side look at what's included as you scale.",
                    'columns' => [
                        ['key' => 'Starter', 'label' => 'Starter', 'highlighted' => false],
                        ['key' => 'Growth', 'label' => 'Growth', 'highlighted' => true],
                        ['key' => 'Scale', 'label' => 'Scale', 'highlighted' => false],
                    ],
                    'rows' => [
                        ['feature' => 'Monthly messages', 'values' => [
                            ['plan_key' => 'Starter', 'value' => '10,000'],
                            ['plan_key' => 'Growth', 'value' => '100,000'],
                            ['plan_key' => 'Scale', 'value' => 'Unlimited'],
                        ]],
                        ['feature' => 'WhatsApp numbers', 'values' => [
                            ['plan_key' => 'Starter', 'value' => '1'],
                            ['plan_key' => 'Growth', 'value' => '5'],
                            ['plan_key' => 'Scale', 'value' => '20'],
                        ]],
                        ['feature' => 'Auto reply & chatbot', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'yes'],
                            ['plan_key' => 'Growth', 'value' => 'yes'],
                            ['plan_key' => 'Scale', 'value' => 'yes'],
                        ]],
                        ['feature' => 'AI smart reply', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'no'],
                            ['plan_key' => 'Growth', 'value' => 'yes'],
                            ['plan_key' => 'Scale', 'value' => 'yes'],
                        ]],
                        ['feature' => 'Advanced reports & export', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'no'],
                            ['plan_key' => 'Growth', 'value' => 'yes'],
                            ['plan_key' => 'Scale', 'value' => 'yes'],
                        ]],
                        ['feature' => 'Team members & roles', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'no'],
                            ['plan_key' => 'Growth', 'value' => 'yes'],
                            ['plan_key' => 'Scale', 'value' => 'yes'],
                        ]],
                        ['feature' => 'API access & webhooks', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'no'],
                            ['plan_key' => 'Growth', 'value' => 'no'],
                            ['plan_key' => 'Scale', 'value' => 'yes'],
                        ]],
                        ['feature' => 'Support', 'values' => [
                            ['plan_key' => 'Starter', 'value' => 'Email'],
                            ['plan_key' => 'Growth', 'value' => 'Priority email'],
                            ['plan_key' => 'Scale', 'value' => 'Priority + chat'],
                        ]],
                    ],
                ],
            ],
            [
                'slug' => 'pricing-faq',
                'name' => 'Pricing FAQ',
                'type' => 'marketing_faq',
                'description' => 'FAQ section for the Pricing page.',
                'data' => [
                    'eyebrow' => 'FAQ',
                    'heading' => 'Pricing questions',
                    'items' => [
                        ['question' => 'Can I change plans later?', 'answer' => 'Yes — upgrade or downgrade anytime. Changes are prorated, and you keep all your contacts, templates, and history.'],
                        ['question' => 'How does yearly billing work?', 'answer' => 'Yearly billing gives you the same plan limits at a lower monthly equivalent, billed once per year.'],
                        ['question' => 'Is there a free plan?', 'answer' => 'Yes. The Starter plan is free forever and includes 10,000 messages per month with auto-reply and chatbot.'],
                        ['question' => 'Do you offer refunds?', 'answer' => "Every paid plan comes with a 14-day money-back guarantee. If it's not the right fit, contact us for a full refund."],
                    ],
                ],
            ],
            [
                'slug' => 'pricing-cta',
                'name' => 'Pricing CTA',
                'type' => 'marketing_cta',
                'description' => 'CTA parallax banner for the Pricing page.',
                'data' => [
                    'eyebrow' => 'Start free',
                    'heading' => 'Try every feature free for 14 days',
                    'subheading' => 'No credit card required. Connect your number and send your first campaign in minutes.',
                    'cta_primary_text' => 'Create your workspace',
                    'cta_primary_url' => '/login',
                    'cta_secondary_text' => 'Talk to sales',
                    'cta_secondary_url' => '/contact',
                    'background_image' => '/assets/wapro/images/hero-tedy-1.webp',
                ],
            ],
            [
                'slug' => 'page-header-faqs',
                'name' => 'Page Header - FAQs',
                'type' => 'marketing_page_header',
                'description' => 'Page header for the FAQs page.',
                'data' => [
                    'eyebrow' => 'Help center',
                    'heading' => 'Frequently asked questions',
                    'subheading' => "Everything you need to know about WaPro — from getting set up to billing and the API. Can't find an answer? We're a message away.",
                ],
            ],
            [
                'slug' => 'faq-categories',
                'name' => 'FAQ Categories',
                'type' => 'marketing_faq_categories',
                'description' => 'Grouped FAQ categories for the FAQs page.',
                'data' => [
                    'categories' => [
                        [
                            'heading' => 'Getting started',
                            'items' => [
                                ['question' => 'How do I connect my WhatsApp number?', 'answer' => 'Head to Channel Setup, connect your WhatsApp Business Account through Meta, verify your number, and sync your phone numbers — the whole flow takes a few minutes.'],
                                ['question' => 'Do I need a Meta Business account?', 'answer' => "Yes. WhatsApp's Business API requires a verified Meta Business account. We guide you through verification during setup if you don't have one yet."],
                                ['question' => 'Can I import my existing contacts?', 'answer' => 'Absolutely. Upload a CSV from the Contacts page and map your columns — names, numbers and tags come across in one step.'],
                            ],
                        ],
                        [
                            'heading' => 'Billing & plans',
                            'items' => [
                                ['question' => 'Can I change plans later?', 'answer' => 'Yes — upgrade or downgrade anytime from the Subscription page. Changes are prorated and you keep all your data.'],
                                ['question' => 'Which payment methods do you accept?', 'answer' => 'All major cards via Stripe, plus PayPal. Annual plans can also be paid by bank transfer — contact us to arrange it.'],
                                ['question' => 'Do you offer refunds?', 'answer' => "Every paid plan includes a 14-day money-back guarantee. If it's not the right fit, reach out for a full refund."],
                            ],
                        ],
                        [
                            'heading' => 'Messaging',
                            'items' => [
                                ['question' => 'Why do templates need approval?', 'answer' => 'WhatsApp reviews message templates to prevent spam. Most are approved within minutes — we show the status live and explain any rejection so you can fix it fast.'],
                                ['question' => 'Is there a limit on bulk campaigns?', 'answer' => "Limits depend on your WhatsApp messaging tier and your plan's monthly allowance. Campaigns respect both automatically and queue the rest."],
                            ],
                        ],
                        [
                            'heading' => 'API & technical',
                            'items' => [
                                ['question' => 'Do you have a REST API and webhooks?', 'answer' => 'Yes. Generate API tokens, send messages programmatically, and subscribe to webhook events for delivery and inbound updates. Full reference lives in our API docs.'],
                                ['question' => 'How is my data secured?', 'answer' => 'All traffic is encrypted in transit over HTTPS, tokens are scoped and revocable, and you can review every account action in the activity log.'],
                            ],
                        ],
                    ],
                    'cta_title' => 'Still have a question?',
                    'cta_subtitle' => 'Our team is happy to help. Start a conversation and we\'ll get back to you within a few hours on business days.',
                    'icon_class' => 'ph-headset',
                    'cta_primary_text' => 'Contact us',
                    'cta_primary_url' => '/contact',
                    'cta_secondary_text' => 'Start free',
                    'cta_secondary_url' => '/login',
                ],
            ],
            [
                'slug' => 'cta-card-faqs',
                'name' => 'CTA Card - FAQs',
                'type' => 'marketing_cta_card',
                'description' => 'CTA card for the FAQs page.',
                'data' => [
                    'icon_class' => 'ph-headset',
                    'heading' => 'Still have a question?',
                    'subheading' => "Our team is happy to help. Start a conversation and we'll get back to you within a few hours on business days.",
                    'cta_primary_text' => 'Contact us',
                    'cta_primary_url' => '/contact',
                    'cta_secondary_text' => 'Start free',
                    'cta_secondary_url' => '/login',
                ],
            ],
            [
                'slug' => 'page-header-contact',
                'name' => 'Page Header - Contact',
                'type' => 'marketing_page_header',
                'description' => 'Page header for the Contact page.',
                'data' => [
                    'eyebrow' => 'Contact',
                    'heading' => 'Talk to the WaPro team',
                    'subheading' => "Questions about features, pricing, or getting set up? Send us a message and we'll get back within one business day.",
                ],
            ],
            [
                'slug' => 'contact-info',
                'name' => 'Contact Info',
                'type' => 'marketing_contact_info',
                'description' => 'Contact information section for the Contact page.',
                'data' => [
                    'eyebrow' => 'Get in touch',
                    'heading' => "We're here to help",
                    'subheading' => 'Reach us by email or phone, or drop by the office. Prefer chat? Message us on WhatsApp.',
                    'channels' => [
                        ['label' => 'Email', 'value' => 'hello@wapro.com', 'link_type' => 'email', 'link_url' => 'hello@wapro.com', 'icon_class' => 'ph-envelope-simple'],
                        ['label' => 'Phone', 'value' => '+1 (406) 555-0120', 'link_type' => 'phone', 'link_url' => '+1 (406) 555-0120', 'icon_class' => 'ph-phone'],
                        ['label' => 'Address', 'value' => "123 Business Street, Suite 456\nNew York, NY 10001, USA", 'link_type' => 'none', 'link_url' => '', 'icon_class' => 'ph-map-pin'],
                    ],
                    'whatsapp_title' => 'Message us on WhatsApp',
                    'whatsapp_hours' => 'Mon–Fri, 9am–6pm ET. Average reply under 10 minutes.',
                    'form_heading' => 'Send us a message',
                    'submit_text' => 'Send message',
                    'success_message' => "Thanks! Your message is on its way. We'll reply within one business day.",
                    'fields' => [
                        ['name' => 'first_name', 'label' => 'First name', 'type' => 'text', 'placeholder' => 'Jane', 'required' => true],
                        ['name' => 'last_name', 'label' => 'Last name', 'type' => 'text', 'placeholder' => 'Doe', 'required' => true],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'placeholder' => 'jane@company.com', 'required' => true],
                        ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'placeholder' => 'Acme Inc.', 'required' => true],
                        ['name' => 'interest', 'label' => 'How can we help?', 'type' => 'select', 'placeholder' => 'How can we help?'],
                        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'placeholder' => 'Tell us a bit about what you need...', 'required' => true],
                    ],
                    'interest_options' => [
                        ['value' => 'sales', 'label' => 'Sales & pricing'],
                        ['value' => 'support', 'label' => 'Technical support'],
                        ['value' => 'partnership', 'label' => 'Partnership'],
                        ['value' => 'other', 'label' => 'Something else'],
                    ],
                ],
            ],
            [
                'slug' => 'legal-privacy-policy',
                'name' => 'Legal - Privacy Policy',
                'type' => 'legal_content',
                'description' => 'Editable privacy policy content.',
                'data' => $this->legalSectionData(
                    'Privacy Policy',
                    'How WaPro collects, uses, and protects personal information when you use our website and services.',
                    [
                        ['heading' => 'Information we collect', 'body' => 'We may collect account details, contact information, workspace activity, billing details, support messages, and technical data needed to operate WaPro. We only ask for information that helps us provide, secure, improve, or support the service.'],
                        ['heading' => 'How we use information', 'body' => 'We use information to create and manage accounts, deliver product features, process payments, respond to support requests, prevent abuse, improve performance, and communicate service updates. We do not sell personal information.'],
                        ['heading' => 'Data sharing', 'body' => 'We may share information with trusted service providers that help us host, secure, analyze, bill, or support WaPro. These providers may only use the information to perform services for us and must protect it appropriately.'],
                        ['heading' => 'Your choices', 'body' => 'You may request access, correction, export, or deletion of personal information where applicable. Some data may be retained when required for security, legal, billing, or legitimate business records.'],
                    ]
                ),
            ],
            [
                'slug' => 'legal-terms-and-conditions',
                'name' => 'Legal - Terms & Conditions',
                'type' => 'legal_content',
                'description' => 'Editable terms and conditions content.',
                'data' => $this->legalSectionData(
                    'Terms & Conditions',
                    'The basic terms that govern access to and use of WaPro, including accounts, acceptable use, billing, and service availability.',
                    [
                        ['heading' => 'Using WaPro', 'body' => 'You are responsible for maintaining accurate account information, protecting login credentials, and ensuring your use of WaPro complies with applicable laws, platform policies, and messaging consent requirements.'],
                        ['heading' => 'Acceptable use', 'body' => 'You may not use WaPro to send unlawful, harmful, misleading, abusive, or unsolicited communications. We may suspend access when usage creates risk for users, recipients, the platform, or our infrastructure.'],
                        ['heading' => 'Subscriptions and billing', 'body' => 'Paid plans renew according to the selected billing cycle unless canceled. Plan limits, pricing, and included features may vary by subscription and will be shown before purchase or renewal.'],
                        ['heading' => 'Service changes', 'body' => 'We may update features, integrations, policies, and these terms as the service evolves. Continued use of WaPro after changes take effect means you accept the updated terms.'],
                    ]
                ),
            ],
            [
                'slug' => 'legal-confidentiality-privacy',
                'name' => 'Legal - Confidentiality & Privacy',
                'type' => 'legal_content',
                'description' => 'Editable confidentiality and privacy content.',
                'data' => $this->legalSectionData(
                    'Confidentiality & Privacy',
                    'How WaPro treats customer workspace information, message data, and confidential operational details.',
                    [
                        ['heading' => 'Confidential information', 'body' => 'Customer lists, campaign details, conversations, templates, account settings, and business records are treated as confidential customer information. We use this information only to provide and support the service.'],
                        ['heading' => 'Access controls', 'body' => 'Access to customer information is limited to authorized personnel and systems that need it for operations, security, support, or compliance. Administrative access is reviewed and restricted based on job responsibilities.'],
                        ['heading' => 'Message and contact privacy', 'body' => 'Message and contact data belongs to the customer workspace. Customers are responsible for collecting required consent and honoring recipient preferences, while WaPro provides tools to manage campaigns and communication workflows.'],
                        ['heading' => 'Security practices', 'body' => 'We use reasonable technical and organizational safeguards to protect confidential information, including encrypted transport, scoped access, monitoring, and operational controls designed to reduce unauthorized access.'],
                    ]
                ),
            ],
            [
                'slug' => 'legal-information',
                'name' => 'Legal - Legal Information',
                'type' => 'legal_content',
                'description' => 'Editable legal information content.',
                'data' => $this->legalSectionData(
                    'Legal Information',
                    'General company, compliance, and legal contact information for WaPro customers and website visitors.',
                    [
                        ['heading' => 'Company information', 'body' => 'WaPro provides software for WhatsApp marketing, automation, customer messaging, and reporting. Company registration, tax, and billing details may be provided in invoices, account records, or direct legal correspondence.'],
                        ['heading' => 'Platform relationship', 'body' => 'WaPro uses official provider integrations where applicable. Product names, trademarks, and platform policies remain the property and responsibility of their respective owners.'],
                        ['heading' => 'Legal notices', 'body' => 'Formal legal notices should include the account owner name, workspace identifier where relevant, a clear description of the request, and contact details for follow-up.'],
                        ['heading' => 'Contact', 'body' => 'For legal, privacy, or compliance questions, contact hello@wapro.com. We will route your request to the appropriate team and respond as soon as reasonably possible.'],
                    ]
                ),
            ],
            [
                'slug' => 'legal-cookie-policy',
                'name' => 'Legal - Cookie Policy',
                'type' => 'legal_content',
                'description' => 'Editable cookie policy content.',
                'data' => $this->legalSectionData(
                    'Cookie Policy',
                    'How WaPro uses cookies and similar technologies on our website and services.',
                    [
                        ['heading' => 'What cookies are', 'body' => 'Cookies are small text files stored on your device when you visit a website. They help remember preferences, support secure sessions, and improve how pages work.'],
                        ['heading' => 'How we use cookies', 'body' => 'We use necessary cookies to operate the website and may use analytics or preference cookies to understand usage, improve performance, and personalize your experience.'],
                        ['heading' => 'Managing cookies', 'body' => 'You can accept our cookie notice or control cookies through your browser settings. Blocking some cookies may affect website functionality or saved preferences.'],
                        ['heading' => 'Updates to this policy', 'body' => 'We may update this Cookie Policy when our website, services, or legal requirements change. The effective date on this page reflects the latest version.'],
                    ]
                ),
            ],
        ];

        $sectionIdsBySlug = [];

        foreach ($definitions as $definition) {
            if ($definition['type'] === 'marketing_pricing' || str_starts_with($definition['slug'], 'pricing-') || $definition['slug'] === 'page-header-pricing' || $definition['slug'] === 'compare-plans') {
                continue;
            }

            $section = FrontendSection::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'status' => 'published',
                    'description' => $definition['description'],
                    'data' => $definition['data'],
                    'theme_overrides' => [],
                    'preview_image_media_id' => null,
                ]
            );

            $sectionIdsBySlug[$definition['slug']] = $section->id;
        }

        return $sectionIdsBySlug;
    }

    private function seedPages(array $sectionIdsBySlug): void
    {
        /** @var PageComposerService $composer */
        $composer = app(PageComposerService::class);

        $home = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Home',
                'status' => 'published',
                'excerpt' => 'WaPro — WhatsApp marketing, automation, and CRM platform.',
                'default_layout' => 'landing',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => true,
                'meta_title' => 'WhatsApp Marketing, Automation & CRM - WaPro',
                'meta_description' => 'Run WhatsApp Cloud API campaigns, smart replies, chatbots, contacts, automations, and reports from one SaaS workspace.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($home, $this->orderedSectionIds($sectionIdsBySlug, [
            'home-hero',
            'brand-marquee',
            'product-modules',
            'feature-spotlight',
            'how-it-works',
            'use-cases',
            'home-faq',
            'home-cta',
        ]));

        $features = Page::updateOrCreate(
            ['slug' => 'features'],
            [
                'title' => 'Features',
                'status' => 'published',
                'excerpt' => 'Explore every WaPro module — broadcasting, automation, chatbot, contacts, and more.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Features - WaPro',
                'meta_description' => 'Campaigns, automation, a shared inbox, contacts, and reporting — built for the way teams run WhatsApp at scale.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($features, $this->orderedSectionIds($sectionIdsBySlug, [
            'page-header-features',
            'broadcasting',
            'automation-ai',
            'all-modules-grid',
            'why-wapro',
            'features-cta',
        ]));

        Page::query()->where('slug', 'pricing')->delete();

        $faqs = Page::updateOrCreate(
            ['slug' => 'faqs'],
            [
                'title' => 'FAQs',
                'status' => 'published',
                'excerpt' => 'Everything you need to know about WaPro — from getting set up to billing and the API.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'FAQs - WaPro',
                'meta_description' => 'Answers to the most common questions about WaPro — setup, billing, messaging, and API.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($faqs, $this->orderedSectionIds($sectionIdsBySlug, [
            'page-header-faqs',
            'faq-categories',
        ]));

        $contact = Page::updateOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact',
                'status' => 'published',
                'excerpt' => 'Talk to the WaPro team. Questions about features, pricing, or getting set up.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Contact - WaPro',
                'meta_description' => 'Questions about features, pricing, or getting set up? Send us a message and we\'ll get back within one business day.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($contact, $this->orderedSectionIds($sectionIdsBySlug, [
            'page-header-contact',
            'contact-info',
        ]));

        $legalPages = [
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'section_slug' => 'legal-privacy-policy',
                'excerpt' => 'How WaPro collects, uses, and protects personal information.',
                'meta_description' => 'Learn how WaPro collects, uses, shares, and protects personal information.',
            ],
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms & Conditions',
                'section_slug' => 'legal-terms-and-conditions',
                'excerpt' => 'The terms that govern access to and use of WaPro.',
                'meta_description' => 'Review the terms and conditions that apply when using WaPro.',
            ],
            [
                'slug' => 'confidentiality-privacy',
                'title' => 'Confidentiality & Privacy',
                'section_slug' => 'legal-confidentiality-privacy',
                'excerpt' => 'How WaPro treats customer workspace information and confidential data.',
                'meta_description' => 'Learn how WaPro handles customer workspace information, message data, and confidential operational details.',
            ],
            [
                'slug' => 'legal-information',
                'title' => 'Legal Information',
                'section_slug' => 'legal-information',
                'excerpt' => 'General company, compliance, and legal contact information for WaPro.',
                'meta_description' => 'Find general company, compliance, and legal contact information for WaPro.',
            ],
            [
                'slug' => 'cookie-policy',
                'title' => 'Cookie Policy',
                'section_slug' => 'legal-cookie-policy',
                'excerpt' => 'How WaPro uses cookies and similar technologies.',
                'meta_description' => 'Learn how WaPro uses cookies and similar technologies on its website and services.',
            ],
        ];

        foreach ($legalPages as $legalPage) {
            $page = Page::updateOrCreate(
                ['slug' => $legalPage['slug']],
                [
                    'title' => $legalPage['title'],
                    'status' => 'published',
                    'excerpt' => $legalPage['excerpt'],
                    'default_layout' => 'default',
                    'theme_overrides' => [],
                    'is_system' => true,
                    'is_home' => false,
                    'meta_title' => $legalPage['title'].' - WaPro',
                    'meta_description' => $legalPage['meta_description'],
                    'meta_image_media_id' => null,
                    'published_at' => now(),
                ]
            );

            $composer->syncSections($page, $this->orderedSectionIds($sectionIdsBySlug, [
                $legalPage['section_slug'],
            ]));
        }
    }

    private function orderedSectionIds(array $sectionIdsBySlug, array $slugs): array
    {
        return collect($slugs)
            ->map(fn (string $slug): ?int => $sectionIdsBySlug[$slug] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function seedMenus(): void
    {
        /** @var MenuService $menus */
        $menus = app(MenuService::class);

        $homeUrl = '/';
        $featuresUrl = '/features';
        $faqsUrl = '/faqs';
        $blogUrl = '/blog';
        $contactUrl = '/contact';
        $registerUrl = '/login';
        $loginUrl = '/login';
        $privacyPolicy = Page::query()->where('slug', 'privacy-policy')->first();
        $termsAndConditions = Page::query()->where('slug', 'terms-and-conditions')->first();
        $cookiePolicy = Page::query()->where('slug', 'cookie-policy')->first();

        $headerPayload = json_encode([
            ['temp_key' => 'h-home', 'depth' => 0, 'item_type' => 'external', 'label' => 'Home', 'url' => $homeUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'h-features', 'depth' => 0, 'item_type' => 'external', 'label' => 'Features', 'url' => $featuresUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'h-faqs', 'depth' => 0, 'item_type' => 'external', 'label' => 'FAQs', 'url' => $faqsUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'h-blog', 'depth' => 0, 'item_type' => 'external', 'label' => 'Blog', 'url' => $blogUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'h-contact', 'depth' => 0, 'item_type' => 'external', 'label' => 'Contact', 'url' => $contactUrl, 'target' => '_self', 'is_visible' => true],
        ], JSON_THROW_ON_ERROR);

        $existingHeader = FrontendMenu::query()->where('slug', 'header-menu')->first();

        if ($existingHeader) {
            $menus->update($existingHeader, [
                'name' => 'Header Menu',
                'slug' => 'header-menu',
                'status' => 'published',
                'items_payload' => $headerPayload,
            ]);
        } else {
            $menus->create([
                'name' => 'Header Menu',
                'slug' => 'header-menu',
                'status' => 'published',
                'items_payload' => $headerPayload,
            ]);
        }

        $footerPayload = json_encode([
            ['temp_key' => 'f-product', 'depth' => 0, 'item_type' => 'group', 'label' => 'Product', 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-features', 'depth' => 1, 'item_type' => 'external', 'label' => 'Features', 'url' => $featuresUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-faqs', 'depth' => 1, 'item_type' => 'external', 'label' => 'FAQs', 'url' => $faqsUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-get-started', 'depth' => 1, 'item_type' => 'external', 'label' => 'Get started', 'url' => $registerUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-company', 'depth' => 0, 'item_type' => 'group', 'label' => 'Company', 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-contact', 'depth' => 1, 'item_type' => 'external', 'label' => 'Contact', 'url' => $contactUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-sign-in', 'depth' => 1, 'item_type' => 'external', 'label' => 'Sign in', 'url' => $loginUrl, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-privacy', 'depth' => 1, 'item_type' => 'internal', 'label' => 'Privacy Policy', 'linkable_type' => Page::class, 'linkable_id' => $privacyPolicy?->id, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-terms', 'depth' => 1, 'item_type' => 'internal', 'label' => 'Terms & Conditions', 'linkable_type' => Page::class, 'linkable_id' => $termsAndConditions?->id, 'target' => '_self', 'is_visible' => true],
            ['temp_key' => 'f-cookies', 'depth' => 1, 'item_type' => 'internal', 'label' => 'Cookie Policy', 'linkable_type' => Page::class, 'linkable_id' => $cookiePolicy?->id, 'target' => '_self', 'is_visible' => true],
        ], JSON_THROW_ON_ERROR);

        $existingFooter = FrontendMenu::query()->where('slug', 'footer-menu')->first();

        if ($existingFooter) {
            $menus->update($existingFooter, [
                'name' => 'Footer Menu',
                'slug' => 'footer-menu',
                'status' => 'published',
                'items_payload' => $footerPayload,
            ]);
        } else {
            $menus->create([
                'name' => 'Footer Menu',
                'slug' => 'footer-menu',
                'status' => 'published',
                'items_payload' => $footerPayload,
            ]);
        }

        $headerMenu = FrontendMenu::query()->where('slug', 'header-menu')->first();
        $footerMenu = FrontendMenu::query()->where('slug', 'footer-menu')->first();

        if ($headerMenu) {
            FrontendThemeSetting::updateOrCreate(
                ['key' => 'theme.classic.menu.header'],
                ['value' => (string) $headerMenu->id]
            );
        }

        if ($footerMenu) {
            FrontendThemeSetting::updateOrCreate(
                ['key' => 'theme.classic.menu.footer'],
                ['value' => (string) $footerMenu->id]
            );
        }
    }

    private function legalSectionData(string $heading, string $subheading, array $contentBlocks): array
    {
        return [
            'eyebrow' => 'Legal',
            'heading' => $heading,
            'subheading' => $subheading,
            'effective_date' => 'July 12, 2026',
            'content_blocks' => $contentBlocks,
        ];
    }
}
