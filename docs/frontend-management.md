# Frontend Management

The frontend management stack adds a theme-aware, shared-content frontend system to the boilerplate.

## What it includes

- shared `pages` content model
- shared `frontend_sections` content library
- shared `frontend_menus` navigation system
- page composition through `page_sections`
- code-defined theme registry in `config/frontend-themes.php`
- code-defined menu slot registry in `config/frontend-menus.php`
- code-defined section registry in `config/frontend-sections.php`
- admin areas for:
  - `Frontend Themes`
  - `Menu Management`
  - `Manage Frontend`
  - `Manage Pages`
- public rendering through the active theme

## Core idea

Pages and sections are the canonical content layer. Themes do not own their own page trees in v1.

Themes define:

- rendering namespaces
- supported section types
- available page layouts
- theme-scoped settings
- fallback rendering behavior

This keeps content stable while allowing future themes to be added without recreating pages.

## Data model

- `pages`
  - page metadata, publish state, layout, and SEO fields
- `frontend_sections`
  - reusable content blocks with typed data payloads
- `page_sections`
  - page-to-section composition and ordering
- `frontend_theme_settings`
  - active theme, enabled flags, and per-theme settings values
- `frontend_menus`
  - reusable navigation trees shared across themes
- `frontend_menu_items`
  - menu tree items with page links, custom URLs, and group parents

## Admin flow

### Frontend Themes

Use `Frontend Themes` to:

- enable or disable installed themes
- choose the active public theme
- edit theme-scoped settings like colors and branding text

### Manage Frontend

Use `Manage Frontend` to:

- create reusable section instances
- choose a section type from the registry
- edit schema-driven field values
- review theme compatibility
- monitor how many pages use each section

### Manage Pages

Use `Manage Pages` to:

- create pages
- configure slug, layout, publish state, and SEO
- compose pages from shared section instances
- reorder sections with drag and drop

### Menu Management

Use `Menu Management` to:

- create reusable navigation trees
- link to frontend pages or external URLs
- build grouped dropdown structures
- save the full tree atomically
- reuse published menus across theme slots

## Public rendering

- `/` renders the published home page if one exists
- `/{slug}` renders a published frontend page
- when no published home page exists, `/` falls back to `resources/views/welcome.blade.php`
- only published pages are publicly rendered

## Related docs

- [frontend-themes.md](./frontend-themes.md)
- [menu-management.md](./menu-management.md)
- [frontend-menus.md](./frontend-menus.md)
- [frontend-sections.md](./frontend-sections.md)
- [frontend-public-rendering.md](./frontend-public-rendering.md)
- [frontend-generator.md](./frontend-generator.md)
