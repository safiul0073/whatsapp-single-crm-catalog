<?php

namespace App\Modules\Frontend\Database\Seeders;

use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\PageComposerService;
use Illuminate\Database\Seeder;

class FrontendPageSeeder extends Seeder
{
    public static function homeSectionSlugs(): array
    {
        return [
            'homepage-hero',
            'homepage-about',
            'homepage-services',
            'homepage-stats',
            'homepage-solutions',
            'homepage-marquee',
            'homepage-why-us',
            'homepage-how-work',
            'homepage-stack',
            'homepage-testimonials',
            'global-faq',
            'global-contact',
            'homepage-blog',
        ];
    }

    public static function blogSectionSlugs(): array
    {
        return [
            'blog-hero',
            'blog-featured',
            'blog-archive',
        ];
    }

    public static function serviceCategorySectionSlugs(): array
    {
        return [
            'service-category-hero',
            'service-category-services',
            'service-category-why-us',
            'service-category-process',
            'service-category-testimonials',
            'service-category-contact',
            'service-category-faq',
        ];
    }

    public static function projectCategorySectionSlugs(): array
    {
        return [
            'project-category-hero',
            'project-category-projects',
            'project-category-stack',
            'project-category-process',
            'global-contact',
        ];
    }

    public static function teamSectionSlugs(): array
    {
        return [
            'team-hero',
            'team-members',
            'team-culture',
            'team-presence',
            'careers-roles',
        ];
    }

    public static function careersSectionSlugs(): array
    {
        return [
            'careers-hero',
            'careers-life',
            'careers-perks',
            'careers-process',
            'careers-roles',
        ];
    }

    public static function aboutSectionSlugs(): array
    {
        return [
            'about-hero',
            'about-story',
            'about-values',
            'about-team',
        ];
    }

    public static function contactSectionSlugs(): array
    {
        return [
            'contact-page-hero',
            'contact-page-form',
            'contact-page-offices',
            'global-faq',
        ];
    }

    public static function faqSectionSlugs(): array
    {
        return [
            'faq-hero',
            'global-faq',
        ];
    }

    public static function supportSectionSlugs(): array
    {
        return [
            'support-hero',
            'support-channels',
            'support-why',
            'global-faq',
            'support-cta',
        ];
    }

    public static function orderedSectionIdsBySlugMap(array $sectionIdsBySlug, array $slugs): array
    {
        return collect($slugs)
            ->map(fn (string $slug): ?int => $sectionIdsBySlug[$slug] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /** @deprecated Use orderedSectionIdsBySlugMap instead */
    public static function orderedHomeSectionIdsBySlugMap(array $sectionIdsBySlug): array
    {
        return self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::homeSectionSlugs());
    }

    public function run(): void
    {
        /** @var PageComposerService $composer */
        $composer = app(PageComposerService::class);

        $home = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Home',
                'status' => 'published',
                'excerpt' => 'WaPro WhatsApp marketing, automation, chatbot, and CRM landing page.',
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

        $allSlugs = array_merge(
            self::homeSectionSlugs(),
            self::serviceCategorySectionSlugs(),
            self::projectCategorySectionSlugs(),
            self::teamSectionSlugs(),
            self::careersSectionSlugs(),
            self::aboutSectionSlugs(),
            self::contactSectionSlugs(),
            self::faqSectionSlugs(),
            self::supportSectionSlugs(),
            self::blogSectionSlugs(),
        );
        $sectionIdsBySlug = FrontendSection::whereIn('slug', $allSlugs)
            ->pluck('id', 'slug')
            ->all();

        $composer->syncSections($home, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::homeSectionSlugs()));

        $team = Page::updateOrCreate(
            ['slug' => 'team'],
            [
                'title' => 'Team',
                'status' => 'published',
                'excerpt' => 'Meet the team behind Classic.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Team — Classic',
                'meta_description' => 'Meet the engineers, designers, and product builders behind Classic. A focused team building world-class digital products.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($team, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::teamSectionSlugs()));

        $careers = Page::updateOrCreate(
            ['slug' => 'careers'],
            [
                'title' => 'Careers',
                'status' => 'published',
                'excerpt' => 'Join a tight-knit team building world-class digital products. See open roles at Classic.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Careers — Classic',
                'meta_description' => 'Join a tight-knit team building world-class digital products. See open roles at Classic.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($careers, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::careersSectionSlugs()));

        $about = Page::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About Us',
                'status' => 'published',
                'excerpt' => "Learn who we are, what drives us, and how we've been building world-class digital products since 2018.",
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'About Us — Classic',
                'meta_description' => "Learn who we are, what drives us, and how we've been building world-class digital products since 2018.",
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($about, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::aboutSectionSlugs()));

        $serviceCategoryTemplate = Page::updateOrCreate(
            ['slug' => 'service-category-template'],
            [
                'title' => 'Service Category Template',
                'status' => 'published',
                'excerpt' => 'Template page that defines the sections shown on all service category detail pages.',
                'default_layout' => 'service_category',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Service Category Template',
                'meta_description' => null,
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections(
            $serviceCategoryTemplate,
            self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::serviceCategorySectionSlugs())
        );

        $projectCategoryTemplate = Page::updateOrCreate(
            ['slug' => 'project-category-template'],
            [
                'title' => 'Project Category Template',
                'status' => 'published',
                'excerpt' => 'Template page that defines the sections shown on all project category detail pages.',
                'default_layout' => 'project_category',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Project Category Template',
                'meta_description' => null,
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections(
            $projectCategoryTemplate,
            self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::projectCategorySectionSlugs())
        );

        $contact = Page::updateOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact Us',
                'status' => 'published',
                'excerpt' => 'Get in touch with Classic. Start a project, ask a question, or book a free 30-minute scope call with our team.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Contact Us — Classic',
                'meta_description' => 'Get in touch with Classic. Start a project, ask a question, or book a free 30-minute scope call with our team.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($contact, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::contactSectionSlugs()));

        $faq = Page::updateOrCreate(
            ['slug' => 'faq'],
            [
                'title' => 'FAQ',
                'status' => 'published',
                'excerpt' => 'Answers to the most common questions about Classic — process, pricing, ownership, and support.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'FAQ — Classic',
                'meta_description' => 'Answers to the most common questions about Classic — process, pricing, ownership, and support.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($faq, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::faqSectionSlugs()));

        $support = Page::updateOrCreate(
            ['slug' => 'support'],
            [
                'title' => 'Support',
                'status' => 'published',
                'excerpt' => 'Get help from the Classic support team. Browse FAQs, submit a ticket, or reach out via live chat.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'Support — Classic',
                'meta_description' => 'Get help from the Classic support team. Browse FAQs, submit a ticket, or reach out via live chat. We\'re here Saturday to Thursday, 5AM–2PM GMT.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($support, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::supportSectionSlugs()));

        $blog = Page::updateOrCreate(
            ['slug' => 'blog'],
            [
                'title' => 'Blog',
                'status' => 'published',
                'excerpt' => 'WhatsApp marketing, automation, chatbot, and CRM insights from WaPro.',
                'default_layout' => 'default',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => 'WhatsApp Marketing Blog - WaPro',
                'meta_description' => 'Read WaPro guides on WhatsApp automation, broadcast campaigns, chatbots, CRM workflows, and customer messaging.',
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $composer->syncSections($blog, self::orderedSectionIdsBySlugMap($sectionIdsBySlug, self::blogSectionSlugs()));
    }
}
