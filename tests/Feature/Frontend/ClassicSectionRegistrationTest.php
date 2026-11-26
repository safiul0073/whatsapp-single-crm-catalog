<?php

use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;

test('classic home sections are registered across theme config and seeders', function () {
    $supportedTypes = config('frontend-themes.classic.supported_section_types');
    $sectionDefinitions = FrontendSectionSeeder::definitions();
    $sectionTypes = array_column($sectionDefinitions, 'type');
    $sectionSlugs = array_column($sectionDefinitions, 'slug');

    expect($supportedTypes)->toBe([
        'home_hero',
        'home_about',
        'home_services',
        'home_stats_number',
        'home_solutions',
        'home_marquee',
        'home_why_us',
        'home_how_work',
        'home_stack',
        'home_testimonials',
        'global_faq',
        'faq_hero',
        'home_blog',
        'blog_hero',
        'blog_featured',
        'blog_archive',
        'blog_newsletter',
        'global_contact',
        'service_category_hero',
        'service_category_services',
        'service_category_why_us',
        'service_category_process',
        'service_category_testimonials',
        'service_category_faq',
        'project_category_hero',
        'project_category_projects',
        'project_category_stack',
        'project_category_process',
        'team_hero',
        'team_members',
        'team_culture',
        'team_presence',
        'team_roles',
        'careers_hero',
        'careers_life',
        'careers_perks',
        'careers_process',
        'careers_roles',
        'about_hero',
        'about_story',
        'about_values',
        'about_team',
        'contact_page_hero',
        'contact_page_form',
        'contact_page_offices',
        'support_hero',
        'support_channels',
        'support_why',
        'support_cta',
        'marketing_hero',
        'marketing_marquee',
        'marketing_modules',
        'marketing_spotlight',
        'marketing_how_works',
        'marketing_use_cases',
        'marketing_pricing',
        'marketing_faq',
        'marketing_cta',
        'marketing_page_header',
        'marketing_compare_table',
        'marketing_modules_grid',
        'marketing_broadcasting',
        'marketing_automation',
        'marketing_why_wapro',
        'marketing_contact_info',
        'marketing_contact_form',
        'marketing_faq_categories',
        'marketing_cta_card',
        'legal_content',
    ]);

    expect($sectionTypes)->toContain(
        'home_hero',
        'home_about',
        'home_services',
        'home_stats_number',
        'home_solutions',
        'home_marquee',
        'home_why_us',
        'home_how_work',
        'home_stack',
        'home_testimonials',
        'global_faq',
        'home_blog',
        'blog_hero',
        'blog_featured',
        'blog_archive',
        'blog_newsletter',
        'global_contact',
        'service_category_hero',
        'service_category_services',
        'service_category_why_us',
        'service_category_process',
        'project_category_hero',
        'project_category_projects',
        'project_category_stack',
        'project_category_process',
        'team_hero',
        'team_members',
        'team_culture',
        'team_presence',
        'team_roles',
        'careers_hero',
        'careers_life',
        'careers_perks',
        'careers_process',
        'careers_roles',
        'about_hero',
        'about_story',
        'about_values',
        'about_team',
    );

    expect(FrontendPageSeeder::homeSectionSlugs())->toBe([
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
    ])->and($sectionSlugs)->toContain(...FrontendPageSeeder::homeSectionSlugs());

    expect(FrontendPageSeeder::blogSectionSlugs())->toBe([
        'blog-hero',
        'blog-featured',
        'blog-archive',
    ])->and($sectionSlugs)->toContain(...FrontendPageSeeder::blogSectionSlugs());

    expect($sectionSlugs)->toContain(...FrontendPageSeeder::projectCategorySectionSlugs());

    foreach ($supportedTypes as $type) {
        expect(config("frontend-sections.{$type}.supported_themes"))->toBe(['classic']);
    }
});

test('classic home page section ids are ordered by the declared slug list', function () {
    $orderedIds = FrontendPageSeeder::orderedHomeSectionIdsBySlugMap([
        'homepage-blog' => 13,
        'homepage-about' => 2,
        'homepage-hero' => 1,
        'homepage-stack' => 9,
        'global-faq' => 11,
        'global-contact' => 12,
        'homepage-services' => 3,
        'homepage-how-work' => 8,
        'homepage-stats' => 4,
        'homepage-testimonials' => 10,
        'homepage-solutions' => 5,
        'homepage-why-us' => 7,
        'homepage-marquee' => 6,
    ]);

    expect($orderedIds)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);
});

test('home testimonials section exposes configurable copy fields', function () {
    $fields = config('frontend-sections.home_testimonials.fields');

    expect($fields)->toHaveKeys([
        'eyebrow_text',
        'heading_line_one',
        'heading_line_two',
        'subheading',
    ]);

    expect($fields['eyebrow_text']['default'])->toBe('What teams say')
        ->and($fields['heading_line_one']['default'])->toBe('Trusted by founders')
        ->and($fields['heading_line_two']['default'])->toBe('who actually shipped.')
        ->and($fields['subheading']['default'])->toContain('A decade of senior product engineering');
});

test('home blog section exposes configurable copy fields', function () {
    $fields = config('frontend-sections.home_blog.fields');

    expect($fields)->toHaveKeys([
        'eyebrow_text',
        'heading_line_one',
        'heading_highlight',
        'subheading',
        'cta_text',
        'cta_link',
    ]);

    expect($fields['eyebrow_text']['default'])->toBe('From the Blog')
        ->and($fields['heading_line_one']['default'])->toBe('Insights &')
        ->and($fields['heading_highlight']['default'])->toBe('articles')
        ->and($fields['cta_text']['default'])->toBe('View all posts')
        ->and($fields['cta_link']['default'])->toBe('/blog');
});
