# Frontend Management Integration Plan

## Goal

Bring the old boilerplate's `Manage Frontend` and `Manage Pages` capabilities into the current `admin-panel` boilerplate without changing the existing project architecture style.

The new solution should:

- preserve the current `Modules + Panels + config-driven settings + docs + generators` structure
- feel production-grade for admins/editors
- stay easy to understand for developers
- be scaffoldable in one shot with minimal manual steps
- avoid the old system's hidden coupling and hard-coded special cases

---

## Current Baseline

### What the old project already has

The old project at `D:\xampp\htdocs\boilerplate\boilerplate` includes:

- `Manage Frontend` menu entry pointing to `frontend-sections`
- `Manage Pages` menu entry pointing to `pages`
- a `FrontendSection` model storing `content` as JSON
- a `Page` model linked to sections through `page_sections`
- template-aware page composition using `template` + `order` on the pivot
- a frontend catch-all route that renders a page by slug

Relevant old files:

- `app/Http/Controllers/Admin/Settings/FrontendSection/FrontendSectionController.php`
- `app/Http/Controllers/Admin/Settings/Pages/PageController.php`
- `app/Models/FrontendSection.php`
- `app/Models/Page.php`
- `app/Models/PageSection.php`
- `database/migrations/2024_12_19_050308_create_frontend_sections_table.php`
- `database/migrations/2024_12_19_051757_create_page_sections_table.php`
- `routes/admin.php`
- `routes/client/main.php`
- `config/menu.php`

### What the current boilerplate already does well

The current `admin-panel` already has strong foundations we should reuse:

- modular domain structure in `app/Modules/*`
- panel-specific controllers and routes in `app/Panels/*`
- config-driven settings pages
- documented field types and settings UX
- permission registration through `config/permissions.php`
- navigation registration through `config/panels.php`
- generator patterns in `app/Console/Commands/*`

Relevant current files:

- `app/Providers/ModuleServiceProvider.php`
- `app/Panels/Admin/routes.php`
- `config/panels.php`
- `config/permissions.php`
- `app/Modules/Settings/Services/SettingsService.php`
- `resources/views/panels/admin/settings/index.blade.php`
- `resources/js/components/settings.js`
- `app/Console/Commands/MakeModuleCommand.php`
- `docs/modules.md`
- `docs/developer-guide.md`
- `docs/settings_fields.md`

---

## What Should Not Be Copied Directly

The old feature works, but it should not be ported 1:1.

### Old-system limitations

1. Section schema is mostly implicit.
   Content lives in JSON, but the rules for that JSON live partly in seeders, partly in views, and partly in custom controller logic.

2. Special-case section handling exists.
   Old code branches for hard-coded slugs like `teams`, `feedback`, and `faq`, which becomes fragile as the product grows.

3. Page composition is useful but under-modeled.
   The page builder supports ordering and templates, but not a clean draft/publish workflow, preview flow, or reusable schema registry.

4. Catch-all routing is risky.
   A generic `/{slug}` route is easy to add, but in this boilerplate it can conflict with auth routes, future public routes, APIs, and extra panels if not carefully constrained.

5. Developer setup is not "one shot".
   The old system depends on hidden assumptions in config, seeders, views, and frontend templates. New developers would still need to manually connect too many pieces.

6. SEO is coupled to old app assumptions.
   The old project has page SEO flow, but the current boilerplate does not yet have a matching SEO subsystem, so this needs an intentional design instead of a blind copy.

---

## Recommended Target Architecture

### Core idea

Rebuild this as a schema-driven frontend content system inside the existing boilerplate patterns:

- data lives in database
- field definitions live in code
- rendering contracts live in code
- editor UI is generated from schema definitions
- page composition stays in admin
- frontend rendering stays optional and cleanly isolated

This is the most maintainable fit for the current project.

### Recommended module shape

Create one composite module:

`app/Modules/Frontend/`

Inside it:

- `Models/Page.php`
- `Models/FrontendSection.php`
- `Models/PageSection.php`
- `Services/FrontendPageService.php`
- `Services/FrontendSectionService.php`
- `Services/PageComposerService.php`
- `Services/SectionRegistry.php`
- `Services/PageRenderService.php`
- `Providers/FrontendServiceProvider.php`
- `Support/SectionTypes/*`
- `Database/Migrations/*`
- `Database/Seeders/*`
- `Helpers/FrontendHelper.php`

Why one composite module instead of many tiny modules:

- `Pages`, `Sections`, and `PageSections` are tightly coupled
- page composition logic belongs with page rendering logic
- a one-shot scaffolder is much easier to build around one feature module
- this follows the same "feature module" spirit used by broader modules like notifications and payment systems

### Recommended admin controllers

Keep admin presentation in panel space:

- `app/Panels/Admin/Controllers/FrontendPagesController.php`
- `app/Panels/Admin/Controllers/FrontendSectionsController.php`
- `app/Panels/Admin/Controllers/FrontendTemplatesController.php`
- `app/Panels/Admin/Controllers/FrontendPagePreviewController.php`

### Recommended views

- `resources/views/panels/admin/frontend-pages/*`
- `resources/views/panels/admin/frontend-sections/*`
- `resources/views/panels/admin/frontend-templates/*`

### Recommended config files

- `config/frontend-templates.php`
- `config/frontend-sections.php`

Use code-defined registries here, similar to the current settings modules.

---

## Data Model Recommendation

### `pages`

Recommended fields:

- `id`
- `title`
- `slug`
- `status` (`draft`, `published`, `archived`)
- `template`
- `is_system`
- `is_home`
- `meta_title`
- `meta_description`
- `meta_image_media_id`
- `layout_options` JSON
- `published_at`
- timestamps

Why:

- `status` is better than simple active/inactive
- `template` belongs directly on the page
- basic SEO can ship in phase 1 without needing a separate SEO module
- `is_system` protects critical pages from accidental deletion

### `frontend_sections`

Recommended fields:

- `id`
- `name`
- `slug`
- `type`
- `status` (`draft`, `published`, `archived`)
- `data` JSON
- `description`
- `preview_image_media_id`
- timestamps

Important:

- `type` must map to a code-defined section definition
- `data` stores values only
- schema must not be stored in DB

### `page_sections`

Recommended fields:

- `id`
- `page_id`
- `frontend_section_id`
- `sort_order`
- `visibility_rules` JSON nullable
- timestamps

Important change from old system:

- move `template` ownership to the page itself unless you have a real need for per-template pivot duplication
- keep pivot focused on composition and ordering

### Optional later tables

Do not ship these in phase 1 unless needed:

- `page_revisions`
- `section_revisions`
- `page_publish_jobs`
- `redirects`

These are phase 2 or 3 concerns.

---

## Section Registry Recommendation

This is the most important architectural improvement.

Do not let arbitrary JSON shape define the feature.

Instead, create a section registry in code, for example in `config/frontend-sections.php` or `app/Modules/Frontend/Support/SectionTypes/*`.

Each section type should declare:

- `type`
- `label`
- `icon`
- `description`
- `category`
- `fields`
- `defaults`
- `allowed_templates`
- `render_view`
- `preview_view` or preview strategy
- optional validation rules

Example section types:

- `hero`
- `feature_grid`
- `cta`
- `faq`
- `testimonial_grid`
- `team_grid`
- `rich_content`
- `footer`

### Why this is the right fit

It matches the current boilerplate's strongest pattern:

- config defines structure
- service merges defaults and saved values
- view renders fields from definitions

That is already how current settings modules behave, so developers will understand it immediately.

---

## Editor UX Recommendation

### Navigation

Under the current `Settings` group in `config/panels.php`, add:

- `Frontend Templates`
- `Manage Frontend`
- `Manage Pages`

Recommended route patterns:

- `admin.frontend-templates.*`
- `admin.frontend-sections.*`
- `admin.frontend-pages.*`

Recommended permissions:

- `frontend-templates.view`
- `frontend-templates.edit`
- `frontend-sections.view`
- `frontend-sections.create`
- `frontend-sections.edit`
- `frontend-sections.delete`
- `frontend-pages.view`
- `frontend-pages.create`
- `frontend-pages.edit`
- `frontend-pages.delete`
- `frontend-pages.publish`
- `frontend-pages.preview`

### Manage Frontend screen

This should be a section library, not a raw JSON editor.

Recommended UX:

- searchable section list
- filter by `type`, `status`, `used in pages`, `template compatibility`
- create section from section type
- schema-driven edit form
- preview card
- "used in X pages" indicator
- duplicate section action
- publish/unpublish action

### Manage Pages screen

This should be the page composer.

Recommended UX:

- page list with status, slug, template, updated date
- create page flow
- drag-and-drop section composition
- add existing section from library
- duplicate section into page-local copy
- reorder sections
- quick jump to edit selected section
- preview button
- SEO panel
- publish button

### Template management

Keep template selection simple and code-driven.

Recommended:

- templates remain defined in `config/frontend-templates.php`
- admin can choose which templates are enabled
- pages can choose from enabled templates

Do not make template structure database-defined in phase 1.

---

## Reuse Existing Boilerplate Patterns

### Reuse the settings field renderer

The current settings UI already supports a strong set of field types, including:

- text
- textarea
- select
- boolean
- feature
- media
- color
- checkbox
- tags
- date
- date_range
- datetime
- time
- editor

This can become the base renderer for section-type forms.

### Add only the missing field capabilities

For frontend sections, the current boilerplate is still missing a few high-value schema fields:

1. `repeater`
   Needed for FAQ items, feature cards, social links, team members, pricing rows.

2. `group`
   Needed for nested objects like banner settings or CTA bundles.

3. `reference`
   Needed if a section should reference another module record like blogs, products, or media collections.

4. `builder`
   Optional later. Only needed if a section itself contains nested sub-blocks.

Recommendation:

Ship `repeater` first. Do not overbuild a full nested block editor in phase 1.

---

## Frontend Rendering Recommendation

### Public route strategy

Do not blindly copy the old catch-all `/{slug}` route.

In the current boilerplate, this can collide with:

- `/login`
- `/register`
- `/forgot-password`
- `/email/*`
- future public modules
- future API endpoints
- future panel prefixes

Recommended strategies, in order:

1. Keep frontend page rendering opt-in and behind a dedicated public renderer registration.
2. Place dynamic page routes at the very end of `routes/web.php`.
3. Exclude reserved slugs and known prefixes.
4. Keep home-page rendering explicit rather than routing it through the generic slug resolver.

### Rendering contract

Use a render service:

- resolve page by slug
- ensure page is published
- eager load published sections in order
- map each section `type` to a Blade partial or component
- render through a stable frontend layout

Suggested view location:

- `resources/views/frontend/pages/show.blade.php`
- `resources/views/frontend/sections/{type}.blade.php`

This keeps frontend rendering separate from admin views and prevents panel leakage.

---

## Draft, Preview, and Publish Strategy

Phase 1 should include:

- `draft` and `published` page status
- preview route for admins
- preview token or auth-only preview access
- publish timestamp

Phase 2 can include:

- scheduled publishing
- rollback / revision history
- compare drafts vs published

Why this matters:

Modern content systems consistently separate draft editing from published output and provide preview workflows. That is a better long-term fit than the old active/inactive-only model.

---

## Developer Experience Recommendation

### One-shot setup command

Add a dedicated artisan command:

`php artisan make:frontend-stack --panel=admin --parent=Settings`

This command should generate and register:

- the `Frontend` module
- migrations
- seeders
- admin controllers
- requests
- admin views
- route registrations
- navigation entries
- permission definitions
- default section registry config
- default template config
- optional example seeded pages and sections
- implementation notes in command output

### Why not just use `make:module`

`make:module` is excellent for single-resource CRUD and config-style settings pages.

`Manage Frontend + Manage Pages` is a composed feature with:

- multiple models
- pivot ordering
- schema-driven forms
- page builder UI
- frontend rendering hooks

That deserves its own generator instead of stretching `make:module` too far.

### Implementation strategy for the generator

Do not reinvent your scaffolding system.

Build the new command by reusing existing generator conventions:

- same stub directory style
- same config patching style used by `MakeModuleCommand`
- same navigation registration pattern
- same permission registration pattern

Recommended new stubs:

- `stubs/frontend/module/*`
- `stubs/frontend/views/*`
- `stubs/frontend/config/frontend-sections.stub`
- `stubs/frontend/config/frontend-templates.stub`

---

## Recommended Delivery Phases

### Phase 1: Foundation

- create `Frontend` module
- add `pages`, `frontend_sections`, `page_sections`
- add admin CRUD for pages and sections
- add section registry
- add schema-driven section editor
- add page composer with drag reorder
- add preview route
- add basic SEO fields on pages

### Phase 2: Polish

- add `repeater` field type
- add page duplication
- add section duplication
- add publish/unpublish workflow improvements
- add "used in pages" analytics
- add better validation and template compatibility rules

### Phase 3: Production Hardening

- add revision history
- add scheduled publishing
- add tests for page rendering and composer operations
- add reserved slug guardrails
- add performance caching for published pages

### Phase 4: One-shot Scaffolding

- ship `make:frontend-stack`
- add docs
- add demo seeders
- add install checklist

---

## Testing Strategy

Must be included from the start.

### Feature tests

- create/edit/delete section
- create/edit/delete page
- attach/detach/reorder sections
- publish/unpublish page
- preview access rules
- public page render only for published pages
- reserved slug validation

### Unit tests

- section registry resolution
- schema default merging
- field serialization / deserialization
- page render service ordering

### Regression tests

- existing admin routes remain unaffected
- existing auth routes remain unaffected
- existing settings pages still function

---

## Performance and Caching

Recommended:

- cache only published page payloads
- clear page cache on page publish/update
- clear related page caches when a published section changes
- avoid caching draft editor payloads

Do not over-cache in phase 1.

---

## Security and Guardrails

Recommended rules:

- reserved slug validation
- protect system pages from deletion
- separate preview permission from edit permission
- only published pages available publicly
- strict section type validation against registry
- template compatibility validation on save

---

## Production-Grade Decisions

These are the choices I recommend locking in now.

1. Use schema-as-code for section definitions.
   This is the single biggest maintainability win.

2. Store content values in DB, not field definitions.
   This keeps developer control high and migrations predictable.

3. Put pages, sections, and composition in one `Frontend` module.
   This makes the feature coherent and scaffoldable.

4. Keep templates config-driven.
   Template contracts should stay code-owned.

5. Add draft/preview/publish from the beginning.
   This prevents a painful refactor later.

6. Avoid a raw catch-all route until routing constraints are explicit.
   This prevents major regressions in the current boilerplate.

7. Build a dedicated generator instead of overloading `make:module`.
   This is the best path to the "one shot" developer experience you want.

---

## Proposed First Implementation Scope

If we implement this next, I recommend the first shipping slice be:

- `Frontend` module with `Page`, `FrontendSection`, `PageSection`
- admin list/create/edit screens for pages and sections
- schema-driven section forms using existing settings field renderer
- drag-sort page composer
- page status: draft/published
- preview route for admins
- basic SEO fields on `pages`
- settings navigation entries
- permissions
- docs

Not in first slice:

- revision history
- scheduled publishing
- nested section-in-section builders
- full visual inline page editing

---

## External Research Notes

The recommendation above is aligned with current official CMS/editor patterns:

- Storyblok's block model separates content types from reusable nested blocks:
  https://www.storyblok.com/docs/concepts/blocks

- Contentful's CMS-as-code guidance supports code-owned content models, preview flows, and migrations:
  https://www.contentful.com/help/cms-as-code/

- Contentful's timeline guidance recommends versioning pages/sections while keeping reusable components stable:
  https://www.contentful.com/help/timeline/timeline-best-practices/

- Sanity's visual editing guidance highlights draft preview, click-to-edit, and drag-and-drop page rearrangement as editor expectations:
  https://www.sanity.io/docs/visual-editing/introduction-to-visual-editing

These patterns strongly support:

- composable blocks
- schema-defined editors
- draft/preview workflows
- page-level orchestration instead of ad-hoc JSON editing

---

## Final Recommendation

Do not migrate the old feature as a direct copy.

Rebuild it as a dedicated `Frontend` feature module that:

- follows the current `admin-panel` architecture
- uses schema-driven section definitions
- provides a strong page composer UX
- supports draft, preview, and publish
- stays safe around routing
- can later be generated with a dedicated `make:frontend-stack` command

This approach gives you:

- better editor UX than the old boilerplate
- much lower developer friction
- less hidden coupling
- a cleaner path to documentation, testing, and reuse
