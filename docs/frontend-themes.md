# Frontend Themes

Themes are code-defined contracts stored in `config/frontend-themes.php`.

## Why themes are code-defined

Themes are version-controlled and predictable. Editors manage content and theme settings, but they do not define rendering contracts in the database.

This keeps:

- rendering rules explicit
- future theme additions safer
- theme compatibility easier to validate

## Theme contract

Each theme entry should define:

- `key`
- `label`
- `description`
- `preview_image`
- `default_enabled`
- `view_namespace`
- `supported_section_types`
- `page_layouts`
- `fallback_renderer`
- `theme_settings_schema`

## Current example themes

- `classic`
- `studio`

Each theme has its own layout views in:

- `resources/views/frontend/themes/classic/`
- `resources/views/frontend/themes/studio/`

## Active theme resolution

The active public theme is resolved by:

1. stored `active_theme`
2. first enabled theme
3. registry default fallback

`ActiveThemeResolver` owns this logic.

## Theme settings

Theme settings are stored in `frontend_theme_settings` and keyed by prefix:

- `active_theme`
- `theme.classic.enabled`
- `theme.classic.primary_color`
- `theme.studio.logo_text`

This allows theme-scoped settings without giving themes their own database-defined schema.

## Adding a new theme

1. Add a new entry to `config/frontend-themes.php`
2. Create a view namespace under `resources/views/frontend/themes/{theme-key}/`
3. Add layouts under `layouts/`
4. Add theme-specific section partials only if the shared section partials are not enough
5. Define theme settings schema if the theme needs configurable settings
6. Enable the theme from `Frontend Themes`

## Compatibility rules

- a theme can be enabled without becoming active
- only enabled themes can be activated
- page content remains shared across themes in v1
- unsupported sections use the configured fallback renderer
