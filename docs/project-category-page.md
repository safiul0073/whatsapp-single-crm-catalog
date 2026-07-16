# Adding a Project Category Page (Frontend)

This guide explains how to build a public **Project Category** detail page that mirrors the
existing **Service Category** page pattern. It uses the same CMS-driven section system: each
category gets its own editable `Page` + `FrontendSection` records, rendered through the
`softivus` theme.

> **Reference implementation:** The Service Category page is the blueprint for everything below.
> When in doubt, copy the equivalent service file and adapt it. Key files:
> - Controller — `app/Modules/Frontend/Http/Controllers/ServiceCategoryController.php`
> - Section factory — `app/Modules/Services/Services/ServiceCategorySectionFactory.php`
> - Page blade — `resources/views/frontend/themes/softivus/pages/service-category.blade.php`
> - Layout — `resources/views/frontend/themes/softivus/layouts/service-category.blade.php`
> - Sections — `resources/views/frontend/themes/softivus/sections/service_category_*.blade.php`
> - Section config — `config/frontend-sections.php`
> - Theme config — `config/frontend-themes.php`

> **HTML resource:** The static HTML/markup for each project-category section will be provided
> separately. This doc defines *where* that markup goes and *how* it is wired. When the HTML
> arrives, paste each section's markup into the matching `project_category_*.blade.php` file
> (Step 5) and replace the placeholder `@php` data bindings with the section's `$section->data`
> fields (Step 4).

---

## What already exists (Projects module)

The `Projects` module already ships the admin/data side:

- Models: `App\Modules\Projects\Models\ProjectCategory` (hasMany `Project`) and
  `App\Modules\Projects\Models\Project` (belongsTo `ProjectCategory`).
  - `ProjectCategory` fillable: `name, slug, description, icon, sort_order, active`; has
    `scopeActive()` and a `projects()` relation ordered by `sort_order` then `name`.
  - `Project` fillable: `project_category_id, name, slug, description, excerpt, icon,
    highlights (array), project_url, sort_order, active`; has `scopeActive()`.
- Admin CRUD: `ProjectCategoriesController`, `ProjectsController`, tables, requests, views.
- Admin routes: `app/Modules/Projects/Routes/admin.php`.
- Seeders/factories: `ProjectCategorySeeder`, `ProjectSeeder`, factories.
- Header card service: `App\Modules\Frontend\Services\HeaderProjectCategoryCardService`.

What is **missing** (and what this guide adds) is the **public-facing category page**:
the frontend controller, route, page/layout blades, theme sections, section config, and the
section-provisioning factory.

---

## How the Service Category pattern works (mental model)

1. A category row exists in the DB (`service_categories` / `project_categories`).
2. When a category is **created** (admin `store()` or seeder), a **section factory**
   provisions:
   - One `FrontendSection` per section type (hero, list, why-us, process, …), each with a
     deterministic slug like `service-category-hero-{categorySlug}`.
   - One `Page` with slug `service-category-{categorySlug}`, `default_layout =
     service_category`, `is_system = true`, and the sections attached via
     `PageComposerService::syncSections()`.
3. The public **controller** (`ServiceCategoryController::show($slug)`):
   - Loads the active category with its active children eager-loaded.
   - Looks up a `Page` named `service-category-{slug}` (falls back to
     `service-category-template`). If found, it renders the composed, editable payload via
     `PageRenderService::payload()` → the **layout** (`layouts/service-category.blade.php`),
     which loops `$resolvedSections` and `@include`s each section view.
   - If no Page exists, it falls back to the **static page blade**
     (`pages/service-category.blade.php`), which `@include`s the section blades directly.
4. Each section view reads its editable content from `$section->data` (defined in
   `config/frontend-sections.php`) and falls back to the category's own fields when empty.
5. `config/frontend-themes.php` registers the section types under the theme's `sections` array
   and maps the `service_category` layout key to its view.

You will replicate every numbered piece above, swapping `service`→`project`.

---

## Step-by-step

### Step 1 — Add the public route

In `routes/web.php`, add a route alongside the existing service route:

```php
use App\Modules\Frontend\Http\Controllers\ProjectCategoryController;

// existing:
// Route::get('services/{slug}', [ServiceCategoryController::class, 'show'])->name('home.service');

Route::get('projects/{slug}', [ProjectCategoryController::class, 'show'])->name('home.project');
```

> Pick the URL prefix you want (`projects/{slug}` is the natural match). Keep the route name
> consistent with the `home.*` convention.

---

### Step 2 — Create the frontend controller

Create `app/Modules/Frontend/Http/Controllers/ProjectCategoryController.php` as a near-copy of
`ServiceCategoryController`. Swap the model, the eager-loaded relation
(`services` → `projects`), and the page/section slug prefixes.

```php
<?php

namespace App\Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\HeaderProjectCategoryCardService;
use App\Modules\Frontend\Services\HeaderServiceCardService;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\PageRenderService;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeRenderService;
use App\Modules\Projects\Models\ProjectCategory;
use Illuminate\Http\Response;

class ProjectCategoryController extends Controller
{
    public function __construct(
        protected ActiveThemeResolver $activeThemeResolver,
        protected ThemeRegistry $themes,
        protected ThemeRenderService $themeRender,
        protected MenuRenderService $menus,
        protected HeaderServiceCardService $headerServiceCards,
        protected HeaderProjectCategoryCardService $headerProjectCategoryCards,
        protected FrontendPageService $pages,
        protected PageRenderService $pageRender
    ) {}

    public function show(string $slug): Response
    {
        $category = ProjectCategory::query()
            ->active()
            ->where('slug', $slug)
            ->with(['projects' => fn ($q) => $q->active()])
            ->firstOrFail();

        $themeKey = $this->activeThemeResolver->resolve();
        $templatePage = $this->pages->findBySlug("project-category-{$category->slug}")
            ?? $this->pages->findBySlug('project-category-template');

        if ($templatePage) {
            $payload = $this->pageRender->payload($templatePage, $themeKey);
            $payload['category'] = $category;

            return response()->view($payload['layoutView'], $payload);
        }

        $theme = $this->themes->get($themeKey);

        return response()->view('frontend.themes.softivus.pages.project-category', [
            'themeKey' => $themeKey,
            'theme' => $theme,
            'themeVars' => $this->themeRender->themeVariables($themeKey),
            'resolvedMenus' => $this->menus->resolveForTheme($themeKey),
            'headerServiceCards' => $this->headerServiceCards->cards(),
            'headerProjectCategoryCards' => $this->headerProjectCategoryCards->cards(),
            'headerProjectCategoryLinks' => $this->headerProjectCategoryCards->quickLinks(),
            'resolvedSections' => [],
            'category' => $category,
        ]);
    }
}
```

---

### Step 3 — Decide your sections

The service page uses these section types (in order): `hero`, `services`, `why_us`,
`process`, plus optional `testimonials`, `contact`, `faq`. For projects, define the equivalent
list. A sensible default set:

| Order | Section type (key)             | Purpose                                          |
|-------|--------------------------------|--------------------------------------------------|
| 1     | `project_category_hero`        | Category headline, subheading, CTAs, trust stats |
| 2     | `project_category_projects`    | Grid/list of `$category->projects`               |
| 3     | `project_category_why_us`      | Value props / differentiators                    |
| 4     | `project_category_process`     | Numbered process / approach steps                |

> Adjust to match the provided HTML resource. The **section type keys** chosen here must be
> used identically in Steps 4, 5, 6, and 7 — they are the contract that ties config, blades,
> and the factory together.

---

### Step 4 — Register the sections in `config/frontend-sections.php`

For each section type, add a config block (copy the `service_category_*` blocks as templates).
Each block declares the editable fields shown in the admin section editor, their types,
defaults, and validation rules.

```php
'project_category_hero' => [
    'type' => 'project_category_hero',
    'label' => 'Project Category Hero',
    'icon' => 'ph ph-rocket-launch',
    'description' => 'Hero section for a project category detail page.',
    'category' => 'Project Category',
    'supported_themes' => ['softivus'],
    'fallback_renderer' => 'frontend.shared.sections.unsupported',
    'fields' => [
        'eyebrow_label'   => ['type' => 'text',     'label' => 'Eyebrow Label',   'default' => '',  'rules' => 'nullable|string|max:80'],
        'heading'         => ['type' => 'text',     'label' => 'Heading',         'default' => '',  'rules' => 'nullable|string|max:255'],
        'subheading'      => ['type' => 'textarea', 'label' => 'Subheading',      'default' => '',  'rules' => 'nullable|string|max:1000'],
        'primary_cta_text'=> ['type' => 'text',     'label' => 'Primary CTA Text','default' => 'Start a Project', 'rules' => 'nullable|string|max:100'],
        'primary_cta_link'=> ['type' => 'text',     'label' => 'Primary CTA Link','default' => '#contact',        'rules' => 'nullable|string|max:255'],
        // …stats, badge, hero image (type => 'media'), etc. — mirror service_category_hero
    ],
],
// repeat for project_category_projects, project_category_why_us, project_category_process
```

Field `type` values used elsewhere: `text`, `textarea`, `media` (with `accept`/`recommended_size`),
and repeatable structures stored as arrays (e.g. `items`, `steps`) — see how
`service_category_why_us` / `service_category_process` keep `items`/`steps` as arrays in the
seeder rather than as individual fields.

---

### Step 5 — Register sections + layout in `config/frontend-themes.php`

Add the new section types to the `softivus` theme's `sections` list, and add a `project_category`
page layout mapping:

```php
// inside themes.softivus.sections (append):
'project_category_hero',
'project_category_projects',
'project_category_why_us',
'project_category_process',

// inside themes.softivus.page_layouts:
'project_category' => [
    'label' => 'Project Category',
    'view' => 'layouts.project-category',
],
```

---

### Step 6 — Create the theme views

Create the following under `resources/views/frontend/themes/softivus/`. **Paste the provided
HTML resource into the section files**, then bind each editable value to `$section->data` with a
category fallback (follow the `service_category_*` blades exactly).

**`layouts/project-category.blade.php`** (copy of `layouts/service-category.blade.php`, retitled):

```blade
@extends('frontend.themes.softivus.layouts.page')

@section('title', $category->name . ' Projects — ' . ($themeVars['logo_text'] ?? config('app.name')))

@section('meta_description', $category->description ?: $category->name . ' projects by Softivus.')

@section('main')
    @foreach ($resolvedSections as $resolved)
        @include($resolved['view'], [
            'section' => $resolved['section'],
            'themeKey' => $themeKey,
            'themeVars' => $themeVars,
            'supported' => $resolved['supported'],
            'category' => $category,
        ])
    @endforeach
@endsection
```

**`pages/project-category.blade.php`** (static fallback, copy of `pages/service-category.blade.php`):

```blade
@php
    $page = (object) [
        'meta_title' => $category->name . ' Projects — ' . ($themeVars['logo_text'] ?? config('app.name')),
        'title' => $category->name . ' Projects',
        'meta_description' => $category->description ?: $category->name . ' projects by Softivus.',
    ];
@endphp

@extends('frontend.themes.softivus.layouts.page')

@section('title', $page->meta_title)
@section('meta_description', $page->meta_description)

@section('main')
    @include('frontend.themes.softivus.sections.project_category_hero', ['category' => $category])
    @include('frontend.themes.softivus.sections.project_category_projects', ['category' => $category])
    @include('frontend.themes.softivus.sections.project_category_why_us', ['category' => $category])
    @include('frontend.themes.softivus.sections.project_category_process', ['category' => $category])
    @include('frontend.themes.softivus.sections.global_contact', ['category' => $category])
@endsection
```

**Section blades** — create one per type:

- `sections/project_category_hero.blade.php`
- `sections/project_category_projects.blade.php`
- `sections/project_category_why_us.blade.php`
- `sections/project_category_process.blade.php`

Each section blade should start with the same data-binding pattern as the service equivalents:

```blade
@php
    $d = $section->data ?? [];
    $heading    = $d['heading'] ?? $category->name;
    $subheading = $d['subheading'] ?? $category->description;
    // …pull every editable field, fall back to category fields where sensible
@endphp
{{-- paste the provided HTML markup here, replacing static text with the $variables above --}}
```

For the **projects grid**, loop the eager-loaded relation just like
`service_category_services` loops `$category->services`:

```blade
@foreach ($category->projects as $project)
    {{-- card markup; use $project->name, $project->excerpt ?: $project->description,
         $project->icon, $project->highlights (array), $project->project_url --}}
@endforeach
```

> **Caution — known bug to avoid:** The service page's static fallback includes a
> `service_category_contact` partial that does **not** exist (only `global_contact.blade.php`
> does), so that fallback path throws "View not found". For projects, include
> `sections.global_contact` (as shown above) or create the partial — don't repeat the broken
> include.

---

### Step 7 — Create the section-provisioning factory

Create `app/Modules/Projects/Services/ProjectCategorySectionFactory.php` as a copy of
`ServiceCategorySectionFactory`, swapping the model, the `SECTION_TYPES`, the slug/label
prefixes, and the layout key:

```php
<?php

namespace App\Modules\Projects\Services;

use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\FrontendSectionService;
use App\Modules\Frontend\Services\PageComposerService;
use App\Modules\Projects\Models\ProjectCategory;

class ProjectCategorySectionFactory
{
    /** @var array<string> Ordered list of section types to provision per category */
    private const SECTION_TYPES = [
        'project_category_hero',
        'project_category_projects',
        'project_category_why_us',
        'project_category_process',
    ];

    public function __construct(
        protected FrontendSectionService $sectionService,
        protected PageComposerService $pageComposer
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $seedData  Keyed by section type.
     */
    public function provisionForCategory(ProjectCategory $category, array $seedData = []): void
    {
        $sections = [];

        foreach (self::SECTION_TYPES as $type) {
            $slug = "project-category-{$this->typeKey($type)}-{$category->slug}";
            $name = "[{$category->name}] ".$this->typeLabel($type);
            $data = $this->sectionService->normalizeData($type, $seedData[$type] ?? []);

            $section = FrontendSection::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'type' => $type,
                    'status' => 'published',
                    'description' => "Section for the {$category->name} project category page.",
                    'data' => $data,
                    'theme_overrides' => [],
                    'preview_image_media_id' => null,
                ]
            );

            $sections[] = $section->id;
        }

        $page = Page::updateOrCreate(
            ['slug' => "project-category-{$category->slug}"],
            [
                'title' => "{$category->name} — Project Category",
                'status' => 'published',
                'excerpt' => "Project category page for {$category->name}.",
                'default_layout' => 'project_category',
                'theme_overrides' => [],
                'is_system' => true,
                'is_home' => false,
                'meta_title' => $category->name.' Projects',
                'meta_description' => $category->description,
                'meta_image_media_id' => null,
                'published_at' => now(),
            ]
        );

        $this->pageComposer->syncSections($page, $sections);
    }

    private function typeKey(string $type): string
    {
        return str_replace('project_category_', '', $type);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'project_category_hero' => 'Hero',
            'project_category_projects' => 'Projects',
            'project_category_why_us' => 'Why Us',
            'project_category_process' => 'Process',
            default => ucwords(str_replace('_', ' ', $this->typeKey($type))),
        };
    }
}
```

---

### Step 8 — Provision sections on create (admin + seeder)

**Admin controller** — inject the factory into `ProjectCategoriesController` and call it in
`store()` (mirror `ServiceCategoriesController::store()`):

```php
public function __construct(
    protected ProjectCategoriesService $service,
    protected ProjectCategorySectionFactory $sectionFactory
) {}

public function store(StoreProjectCategoryRequest $request): RedirectResponse
{
    $category = $this->service->create($request->validated());
    $this->sectionFactory->provisionForCategory($category);

    return redirect()
        ->route("{$this->routePrefix}.index")
        ->with('success', __('Project category created successfully.'));
}
```

Also load the page's sections in `edit()` so they're manageable (mirror the service edit):

```php
$categorySections = FrontendSection::query()
    ->where('slug', 'like', "project-category-%-{$record->slug}")
    ->orderBy('slug')
    ->get();
```

**Seeder** — update `ProjectCategorySeeder` to call `provisionForCategory($record, $sectionData)`
for each category, passing seed content keyed by section type (copy the structure of
`ServiceCategorySeeder::categoryDefinitions()`).

> **Backfill existing categories:** `provisionForCategory()` uses `updateOrCreate`, so re-running
> the seeder (or a one-off loop in tinker) over already-existing project categories will safely
> create their pages/sections without duplicating.

---

### Step 9 — Tests

Follow the existing `tests/Feature/ServiceCategoryPageTest.php` as the template. Cover at least:

- Visiting `projects/{slug}` for an active category returns `200` and shows the category name +
  its active projects.
- Inactive category (or inactive projects) is excluded (`scopeActive`).
- Unknown slug returns `404`.
- `ProjectCategorySectionFactory::provisionForCategory()` creates one `Page` (slug
  `project-category-{slug}`, layout `project_category`) and one `FrontendSection` per type, and
  attaches them to the page.

Run them:

```bash
php artisan test --compact --filter=ProjectCategory
```

---

## Checklist

- [ ] Route `projects/{slug}` → `ProjectCategoryController@show` (`home.project`)
- [ ] `ProjectCategoryController` created
- [ ] Section types chosen and used consistently everywhere
- [ ] `config/frontend-sections.php` blocks added for each `project_category_*`
- [ ] `config/frontend-themes.php`: section types appended + `project_category` layout mapped
- [ ] `layouts/project-category.blade.php`
- [ ] `pages/project-category.blade.php` (includes `global_contact`, not a missing partial)
- [ ] `sections/project_category_*.blade.php` (provided HTML pasted + data-bound)
- [ ] `ProjectCategorySectionFactory` created
- [ ] `ProjectCategoriesController::store()` calls `provisionForCategory()`; `edit()` loads sections
- [ ] `ProjectCategorySeeder` provisions sections with seed content
- [ ] Existing categories backfilled
- [ ] Feature tests added and passing
- [ ] `npm run build` (or `npm run dev`) if Tailwind classes were added to new blades
- [ ] `vendor/bin/pint --dirty --format agent` run on changed PHP files
