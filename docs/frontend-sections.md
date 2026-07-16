# Frontend Sections & Implementing a New Page

Sections are typed, reusable content blocks stored in `frontend_sections`. Pages are composed
from ordered lists of sections. This document covers both the section system itself and the
full step-by-step process for adding a new CMS-driven page.

> **Concrete reference:** `docs/project-category-page.md` shows this pattern applied to a real
> module. Read it alongside this guide when you want a working example.

---

## How it works

A public page is the product of three layers wired together by a shared **section type key**:

```
config/frontend-sections.php   ← defines what fields a section has
config/frontend-themes.php     ← registers which section types the theme supports
database (pages + sections)    ← stores editable content instances
Blade views                    ← render that content
```

At request time:

```
Controller
  └─ PageRenderService::payload($page, $themeKey)
       └─ foreach section → ThemeRenderService::sectionView()
            ├─ try  frontend.themes.softivus.sections.{type}
            ├─ try  frontend.shared.sections.{type}
            └─ fall frontend.shared.sections.unsupported
  └─ response()->view($layoutView, $payload)
       └─ @foreach $resolvedSections → @include($resolved['view'])
```

---

## Section registry

The registry lives in `config/frontend-sections.php`. Each section type defines:

| Key | Description |
|---|---|
| `type` | Unique string identifier — the dispatch key used everywhere |
| `label` | Human-readable name shown in the admin |
| `icon` | Phosphor icon class (e.g. `ph ph-star`) |
| `description` | Short description for the admin section picker |
| `category` | Grouping label in the admin UI |
| `supported_themes` | Array of theme keys that can render this section |
| `fallback_renderer` | View to use when the active theme doesn't support the type |
| `fields` | Schema of editable fields (see below) |

---

## Field model

Section fields are schema-driven. Each field entry:

```php
'heading' => [
    'type'    => 'text',
    'label'   => 'Heading',
    'default' => '',
    'rules'   => 'nullable|string|max:255',
],
```

**Supported field types:**

| Type | Use for |
|---|---|
| `text` | Single-line strings |
| `textarea` | Multi-line plain text |
| `editor` | Rich text / HTML |
| `select` | Enum options |
| `boolean` / `feature` | On/off toggles |
| `media` | Image or file picker (add `accept` and `recommended_size`) |
| `color` | Colour picker |
| `checkbox` | Multi-select set (stored as array) |
| `tags` | Free-form tag list (stored as array) |
| `date` / `date_range` / `datetime` / `time` | Date/time pickers |
| `repeater` | Ordered list of sub-items (see below) |

### Repeater fields

Repeaters are stored as JSON arrays inside the section's `data` payload. Use them for FAQ
items, feature lists, testimonial entries, process steps, and similar repeated structures.

```php
'items' => [
    'type'   => 'repeater',
    'label'  => 'Feature Items',
    'schema' => ['icon', 'title', 'description'],
    'default'=> [],
],
```

In Blade, loop the array:

```blade
@foreach ($d['items'] ?? [] as $item)
    <div>{{ $item['title'] ?? '' }}</div>
@endforeach
```

---

## Theme compatibility

Each section type declares `supported_themes`. This is used to:

- Show which themes can render the section cleanly in the admin.
- Warn editors when the active theme would fall back to the unsupported view.
- Help editors choose portable sections across themes.

The theme's own list of supported types lives in `config/frontend-themes.php` under
`supported_section_types`. Both sides must include the type key for it to render correctly.

### Fallback behavior

If a section type is unsupported by the current theme:

1. The section resolves to the theme's `fallback_renderer`.
2. The fallback view (`frontend.shared.sections.unsupported`) displays a styled notice
   explaining that the section needs a compatible theme or a theme-specific renderer.

---

## Naming conventions

Choose a short prefix for your page (e.g. `pricing`, `about`). Use it identically across
every artifact — a mismatch causes "view not found" or silent section drops.

| Artifact | Pattern | Example |
|---|---|---|
| Section type keys | `{prefix}_{section}` | `pricing_hero` |
| Section slugs | `{prefix}-{section}` | `pricing-hero` |
| Page slug | `{prefix}` | `pricing` |
| Layout view | `layouts/{prefix}` | `layouts/pricing` |
| Section blades | `sections/{prefix}_{section}.blade.php` | `sections/pricing_hero.blade.php` |
| Static page blade | `pages/{prefix}.blade.php` | `pages/pricing.blade.php` |

---

## Step 1 — Decide your sections

List the sections the page needs in render order. Write down the type keys now — they are
used identically in every subsequent step.

| Order | Type key | Purpose |
|---|---|---|
| 1 | `{prefix}_hero` | Headline, sub-copy, primary CTA |
| 2 | `{prefix}_features` | Feature list or grid |
| … | … | … |

Reuse global section types (`global_faq`, `global_contact`) where they fit — they already
have blades and config entries.

---

## Step 2 — Register sections in `config/frontend-sections.php`

Add one block per new section type. Copy an existing block of a similar layout as a starting
point (e.g. `service_category_hero` for a hero section).

```php
'{prefix}_hero' => [
    'type'             => '{prefix}_hero',
    'label'            => 'My Page Hero',
    'icon'             => 'ph ph-star',
    'description'      => 'Hero section for my page.',
    'category'         => 'My Page',
    'supported_themes' => ['softivus'],
    'fallback_renderer'=> 'frontend.shared.sections.unsupported',
    'fields'           => [
        'eyebrow'          => ['type' => 'text',     'label' => 'Eyebrow',    'default' => '',           'rules' => 'nullable|string|max:80'],
        'heading'          => ['type' => 'text',     'label' => 'Heading',    'default' => '',           'rules' => 'nullable|string|max:255'],
        'subheading'       => ['type' => 'textarea', 'label' => 'Subheading', 'default' => '',           'rules' => 'nullable|string'],
        'primary_cta_text' => ['type' => 'text',     'label' => 'CTA Text',   'default' => 'Get started','rules' => 'nullable|string|max:100'],
        'primary_cta_link' => ['type' => 'text',     'label' => 'CTA Link',   'default' => '#',          'rules' => 'nullable|string|max:255'],
        'hero_image'       => ['type' => 'media',    'label' => 'Hero Image', 'default' => null,         'accept' => 'image/*', 'recommended_size' => '1200×600'],
    ],
],
```

---

## Step 3 — Register in `config/frontend-themes.php`

Inside the `softivus` theme array:

**Append to `supported_section_types`:**

```php
'{prefix}_hero',
'{prefix}_features',
```

**Add a layout entry under `page_layouts`** (skip if using the default layout):

```php
'{prefix}' => [
    'label' => 'My Page',
    'view'  => 'layouts.{prefix}',
],
```

---

## Step 4 — Create the Blade views

All files go under `resources/views/frontend/themes/softivus/`.

### Layout (`layouts/{prefix}.blade.php`)

Only needed when the section loop must receive extra variables (e.g. a `$category` object).
If the default layout is sufficient, skip this file entirely.

```blade
@extends('frontend.themes.softivus.layouts.page')

@section('title', $page->meta_title ?? config('app.name'))
@section('meta_description', $page->meta_description ?? '')

@section('main')
    @foreach ($resolvedSections as $resolved)
        @include($resolved['view'], [
            'section'   => $resolved['section'],
            'themeKey'  => $themeKey,
            'themeVars' => $themeVars,
            'supported' => $resolved['supported'],
            {{-- pass any extra variables your section blades need --}}
        ])
    @endforeach
@endsection
```

### Static fallback page (`pages/{prefix}.blade.php`)

Used when no `Page` DB record exists yet.

```blade
@extends('frontend.themes.softivus.layouts.page')

@section('title', 'My Page — ' . ($themeVars['logo_text'] ?? config('app.name')))
@section('meta_description', 'My page description.')

@section('main')
    @include('frontend.themes.softivus.sections.{prefix}_hero')
    @include('frontend.themes.softivus.sections.{prefix}_features')
    @include('frontend.themes.softivus.sections.global_contact')
@endsection
```

> Always include `global_contact` / `global_faq` directly. Do **not** create
> `{prefix}_contact` partials that would just duplicate global sections.

### Section blades (`sections/{prefix}_{section}.blade.php`)

One file per section type. Extract all fields from `$section->data` with defaults, then
render your markup:

```blade
@php
    $d          = $section->data ?? [];
    $eyebrow    = $d['eyebrow']          ?? '';
    $heading    = $d['heading']          ?? 'Default heading';
    $subheading = $d['subheading']       ?? '';
    $ctaText    = $d['primary_cta_text'] ?? 'Get started';
    $ctaLink    = $d['primary_cta_link'] ?? '#';
@endphp

<section class="...">
    @if ($eyebrow)
        <span>{{ $eyebrow }}</span>
    @endif
    <h1>{{ $heading }}</h1>
    <p>{{ $subheading }}</p>
    <a href="{{ $ctaLink }}">{{ $ctaText }}</a>
</section>
```

**Rules for section blades:**

- Every `$d[...]` read must have a `?? 'fallback'` — never assume `$section->data` is complete.
- Use `$themeVars['logo_text']`, `$themeVars['primary_color']`, etc. for global branding.
- Never query the database inside a blade — all data comes in via `$section->data` or injected variables.
- For repeater fields: `@foreach ($d['items'] ?? [] as $item)`.

When a section also receives an Eloquent model, use the priority order:
**CMS-edited value → model field → hard-coded default**:

```blade
$heading = $d['heading'] ?? ($model->name ?? 'Default');
```

---

## Step 5 — Add the public route

In `routes/web.php`, add the route before the catch-all `{slug}` route:

```php
// Plain content page — the existing catch-all already handles it if you create the Page record:
// GET /{slug} → FrontendPageController@show

// Dedicated controller (needed when extra context like a model is required):
Route::get('/my-page', [MyPageController::class, 'show'])->name('my-page');
```

---

## Step 6 — Controller (context-aware pages only)

Skip this step for plain content pages — `FrontendPageController@show` handles them via the
`{slug}` catch-all. Create a dedicated controller only when an Eloquent model must be injected
alongside the page render.

```php
<?php

namespace App\Http\Controllers;

use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\HeaderProjectCategoryCardService;
use App\Modules\Frontend\Services\HeaderServiceCardService;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\PageRenderService;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeRenderService;
use Illuminate\Http\Response;

class MyPageController extends Controller
{
    public function __construct(
        protected ActiveThemeResolver $activeThemeResolver,
        protected ThemeRegistry $themes,
        protected ThemeRenderService $themeRender,
        protected MenuRenderService $menus,
        protected HeaderServiceCardService $headerServiceCards,
        protected HeaderProjectCategoryCardService $headerProjectCategoryCards,
        protected FrontendPageService $pages,
        protected PageRenderService $pageRender,
    ) {}

    public function show(): Response
    {
        $themeKey = $this->activeThemeResolver->resolve();
        $page     = $this->pages->findBySlug('{prefix}');

        if ($page) {
            $payload = $this->pageRender->payload($page, $themeKey);

            return response()->view($payload['layoutView'], $payload);
        }

        return response()->view('frontend.themes.softivus.pages.{prefix}', [
            'themeKey'                   => $themeKey,
            'theme'                      => $this->themes->get($themeKey),
            'themeVars'                  => $this->themeRender->themeVariables($themeKey),
            'resolvedMenus'              => $this->menus->resolveForTheme($themeKey),
            'headerServiceCards'         => $this->headerServiceCards->cards(),
            'headerProjectCategoryCards' => $this->headerProjectCategoryCards->cards(),
            'headerProjectCategoryLinks' => $this->headerProjectCategoryCards->quickLinks(),
            'resolvedSections'           => [],
        ]);
    }
}
```

---

## Step 7 — Provision the Page and section records

The `Page` and `FrontendSection` records must exist in the database before the page renders
CMS data. Provision them via a seeder or a factory service.

### Option A — Seeder (one-off pages)

```php
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\FrontendSectionService;
use App\Modules\Frontend\Services\PageComposerService;

$sectionService = app(FrontendSectionService::class);
$pageComposer   = app(PageComposerService::class);

$sectionTypes = ['{prefix}_hero', '{prefix}_features'];
$sectionIds   = [];

foreach ($sectionTypes as $type) {
    $section = FrontendSection::updateOrCreate(
        ['slug' => str_replace('_', '-', $type)],
        [
            'name'   => ucwords(str_replace('_', ' ', $type)),
            'type'   => $type,
            'status' => 'published',
            'data'   => $sectionService->normalizeData($type, []),
        ]
    );
    $sectionIds[] = $section->id;
}

$page = Page::updateOrCreate(
    ['slug' => '{prefix}'],
    [
        'title'          => 'My Page',
        'status'         => 'published',
        'default_layout' => '{prefix}',
        'is_system'      => true,
        'is_home'        => false,
        'published_at'   => now(),
    ]
);

$pageComposer->syncSections($page, $sectionIds);
```

### Option B — Factory service (per-model pages)

Create a `{Model}SectionFactory` service and call it from the admin controller's `store()`
and from the seeder. See `docs/project-category-page.md` Step 7 for the full template.

`PageComposerService::syncSections()` uses `updateOrCreate` internally, so re-running the
seeder over existing records is safe — it will not create duplicates.

---

## Step 8 — Tests

```php
it('renders the page', function () {
    $page = Page::factory()->create(['slug' => '{prefix}', 'status' => 'published']);

    $this->get('/{prefix}')
        ->assertOk()
        ->assertSee('Expected heading');
});

it('falls back to the static blade when no page record exists', function () {
    $this->get('/{prefix}')->assertOk();
});
```

Run:

```bash
php artisan test --compact --filter=MyPage
```

---

## Step 9 — Finish up

```bash
# Format changed PHP files
vendor/bin/pint --dirty --format agent

# Rebuild assets if new Tailwind classes were added
npm run build
```

---

## Checklist

- [ ] Section type keys decided and written down
- [ ] `config/frontend-sections.php` — one block per new section type
- [ ] `config/frontend-themes.php` — types appended; layout added if needed
- [ ] `layouts/{prefix}.blade.php` — created, or confirmed default layout is sufficient
- [ ] `pages/{prefix}.blade.php` — static fallback created
- [ ] `sections/{prefix}_{section}.blade.php` — one per type, all fields defaulted
- [ ] Route added in `routes/web.php`
- [ ] Controller created (if context-aware) or confirmed catch-all is enough
- [ ] `Page` + `FrontendSection` records provisioned (seeder or factory)
- [ ] If factory: admin `store()` calls `provisionForCategory()`
- [ ] Feature tests written and passing
- [ ] `vendor/bin/pint --dirty --format agent` run
- [ ] `npm run build` run (if new Tailwind classes added)

---

## Quick reference

| File | Role |
|---|---|
| `config/frontend-sections.php` | Section type definitions and field schemas |
| `config/frontend-themes.php` | Theme layout and supported section type registry |
| `app/Modules/Frontend/Services/PageRenderService.php` | Builds the render payload |
| `app/Modules/Frontend/Services/ThemeRenderService.php` | Resolves the Blade view per section |
| `app/Modules/Frontend/Services/FrontendSectionService.php` | Normalizes and validates section data |
| `app/Modules/Frontend/Services/PageComposerService.php` | Syncs sections to a page |
| `app/Modules/Frontend/Models/Page.php` | Page model |
| `app/Modules/Frontend/Models/FrontendSection.php` | Section model |
| `resources/views/frontend/themes/softivus/layouts/` | Layout blades |
| `resources/views/frontend/themes/softivus/sections/` | Section blades |
| `resources/views/frontend/themes/softivus/pages/` | Static fallback page blades |
