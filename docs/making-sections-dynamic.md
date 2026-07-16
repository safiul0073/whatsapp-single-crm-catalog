# Making Frontend Sections Dynamic

This guide explains the exact pattern used by `home_hero` so you can apply it to any other section in `resources/views/frontend/themes/softivus/sections/`.

---

## Overview

Every section blade receives a `$section` variable (a `FrontendSection` model). Its `data` column is a JSON array cast to PHP. The pattern is:

1. **Declare field schema** in `config/frontend-sections.php`
2. **Resolve variables** at the top of the blade using `$d = $section->data ?? []`
3. **Seed defaults** in `FrontendSectionSeeder.php`
4. **Replace hardcoded markup** with the resolved PHP variables

---

## Step 1 — Declare fields in `config/frontend-sections.php`

Find the entry matching your section type (e.g. `home_about`) and populate its `fields` array. The entry already exists with `'fields' => []`.

```php
'home_about' => [
    'type'              => 'home_about',
    'label'             => 'Home About',
    'icon'              => 'ph ph-info',
    'description'       => 'Softivus homepage about section.',
    'category'          => 'Home',
    'supported_themes'  => ['softivus'],
    'fallback_renderer' => 'frontend.shared.sections.unsupported',
    'fields' => [

        // Plain text field
        'eyebrow_text' => [
            'type'    => 'text',
            'label'   => 'Eyebrow Text',
            'default' => 'About our company',
            'rules'   => 'nullable|string|max:120',
        ],

        // Long text / rich prose
        'body' => [
            'type'    => 'textarea',
            'label'   => 'Body Copy',
            'default' => 'We are a senior product team…',
            'rules'   => 'nullable|string|max:2000',
        ],

        // Single media upload
        'hero_image_media_id' => [
            'type'             => 'media',
            'label'            => 'Hero Image',
            'accept'           => 'image',
            'recommended_size' => 'Recommended: 1200×800px',
            'rules'            => 'nullable|integer|exists:media,id',
        ],

        // CTA pair — group them so the UI renders them side-by-side
        'cta_text' => [
            'type'        => 'text',
            'label'       => 'CTA Text',
            'default'     => 'Learn More',
            'group'       => 'cta',
            'group_label' => 'Call to Action',
            'rules'       => 'nullable|string|max:100',
        ],
        'cta_link' => [
            'type'        => 'text',
            'label'       => 'CTA Link',
            'default'     => '#about',
            'group'       => 'cta',
            'group_label' => 'Call to Action',
            'rules'       => 'nullable|string|max:255',
        ],

        // Repeater — list of items each with their own sub-fields
        'highlights' => [
            'type'    => 'repeater',
            'label'   => 'Highlight Items',
            'default' => [],
            'rules'   => 'nullable',
            'schema'  => [
                'icon'  => ['type' => 'text',  'label' => 'Phosphor icon class (e.g. ph ph-star)'],
                'title' => ['type' => 'text',  'label' => 'Title'],
                'body'  => ['type' => 'textarea', 'label' => 'Description'],
            ],
        ],

        // Repeater with media upload per row
        'logos' => [
            'type'    => 'repeater',
            'label'   => 'Partner Logos',
            'default' => [],
            'rules'   => 'nullable',
            'schema'  => [
                'logo_media_id' => [
                    'type'             => 'media',
                    'label'            => 'Logo Image',
                    'accept'           => 'image',
                    'recommended_size' => 'SVG or PNG with transparent background',
                ],
            ],
        ],

    ],
],
```

### Supported field types

| `type`       | Renders as                          | Notes |
|--------------|-------------------------------------|-------|
| `text`       | Single-line input                   | |
| `textarea`   | Multi-line textarea                 | |
| `media`      | Media library picker                | Use `accept: 'image'` to restrict to images |
| `repeater`   | Add-more rows, each with sub-fields | Sub-fields follow the same type rules |
| `select`     | Dropdown                            | Pass `options: [['value'=>'','label'=>'']]` |
| `toggle`     | Boolean on/off                      | |
| `url`        | URL input with validation hint      | |

### Field options

| Key               | Purpose |
|-------------------|---------|
| `type`            | Field type (see table above) |
| `label`           | Admin UI label |
| `default`         | Value used when field is empty |
| `rules`           | Laravel validation rules string |
| `group`           | Groups adjacent fields visually in the UI |
| `group_label`     | Heading shown above the group |
| `group_hint`      | Helper text shown below the group heading |
| `recommended_size`| Hint shown in the media picker (media fields only) |
| `schema`          | Sub-field definitions (repeater only) |

---

## Step 2 — Resolve variables in the blade

At the very top of the blade file, before any HTML, add a `@php` block:

```blade
@php
    $d = $section->data ?? [];

    // Scalars — use the default as the fallback
    $eyebrowText = $d['eyebrow_text'] ?? 'About our company';
    $body        = $d['body']        ?? 'We are a senior product team…';
    $ctaText     = $d['cta_text']    ?? 'Learn More';
    $ctaLink     = $d['cta_link']    ?? '#about';

    // Media — resolve ID to a URL
    $heroImageUrl = media_url($d['hero_image_media_id'] ?? null);

    // Repeater with sub-fields
    $highlights = $d['highlights'] ?? [];

    // Repeater — media-only, flatten to a list of URLs
    $logos = collect($d['logos'] ?? [])
        ->map(fn($item) => media_url($item['logo_media_id'] ?? null))
        ->filter()
        ->values()
        ->toArray();
@endphp
```

Then replace every hardcoded string in the HTML with the corresponding variable:

```blade
{{-- Before --}}
<span>About our company</span>
<p>We are a senior product team…</p>
<a href="#about">Learn More</a>

{{-- After --}}
<span>{{ $eyebrowText }}</span>
<p>{{ $body }}</p>
<a href="{{ $ctaLink }}">{{ $ctaText }}</a>
```

For media:

```blade
@if ($heroImageUrl)
    <img src="{{ $heroImageUrl }}" alt="" />
@endif
```

For repeaters:

```blade
@foreach ($highlights as $item)
    <div>
        <i class="{{ $item['icon'] ?? '' }}"></i>
        <h3>{{ $item['title'] ?? '' }}</h3>
        <p>{{ $item['body'] ?? '' }}</p>
    </div>
@endforeach
```

For logo-only repeaters (flat URL list):

```blade
@foreach ($logos as $url)
    <img src="{{ $url }}" alt="" class="h-7 w-auto" loading="lazy" />
@endforeach
```

---

## Step 3 — Seed defaults in `FrontendSectionSeeder.php`

Find the matching entry in `FrontendSectionSeeder::definitions()` and populate its `data` array. The seeder runs `normalizeData()` which merges your values with the field `default` values from config, so you only need to provide non-default data.

```php
[
    'name'        => 'Homepage About',
    'slug'        => 'homepage-about',
    'type'        => 'home_about',
    'status'      => 'published',
    'description' => 'About section for the homepage.',
    'data'        => [
        'eyebrow_text' => 'About our company',
        'body'         => 'We are a senior product team for founders and operators.',
        'cta_text'     => 'Our Story',
        'cta_link'     => '/about',
        'highlights'   => [
            ['icon' => 'ph ph-rocket-launch', 'title' => 'Fast delivery', 'body' => 'MVPs in 8 weeks.'],
            ['icon' => 'ph ph-shield-check',  'title' => 'Senior team',   'body' => '100% senior engineers.'],
        ],
        'logos' => [
            ['logo_media_id' => null],
            ['logo_media_id' => null],
        ],
    ],
],
```

After editing the seeder, re-seed only the frontend sections:

```bash
php artisan db:seed --class="App\\Modules\\Frontend\\Database\\Seeders\\FrontendSectionSeeder"
```

---

## Checklist per section

- [ ] `config/frontend-sections.php` — `fields` array populated for this section type
- [ ] Blade — `@php $d = $section->data ?? []; …` block at the top
- [ ] Blade — all hardcoded strings/URLs replaced with `$variables`
- [ ] `FrontendSectionSeeder.php` — `data` array seeded with sensible defaults
- [ ] Pint — run `vendor/bin/pint --dirty` after editing PHP files

---

## Notes

- `media_url(null)` returns `null`, so always guard image tags with `@if` or use `??` to provide a placeholder.
- The `$section->data` array is populated by `FrontendSectionService::normalizeData()`, which merges saved DB values with field `default` values from config. You never need to call that helper manually in the blade.
- Repeater rows are stored as plain associative arrays. Always use `$item['key'] ?? ''` when rendering to guard against rows saved before a new sub-field was added.
- Group fields (`group` key) are purely cosmetic for the admin panel; they have no effect on how data is stored or resolved in the blade.
