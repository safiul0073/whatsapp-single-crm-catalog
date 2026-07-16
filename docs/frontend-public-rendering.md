# Frontend Public Rendering

Public rendering is intentionally lightweight and safe.

## Routes

- `/` uses `FrontendPageController@home`
- `/{slug}` uses `FrontendPageController@show`

The slug route is registered after the auth routes so existing routes win first.

## Reserved route behavior

Reserved auth and system routes are protected by route order and slug validation:

- `login`
- `register`
- `forgot-password`
- `reset-password`
- `locale`
- `email`
- panel prefixes such as `admin` and `dashboard`

## Rendering flow

1. resolve the page
2. ensure it is published
3. resolve the active theme
4. resolve the layout view for that theme
5. resolve theme-assigned navigation menus for `header`, `footer`, and `mobile`
6. resolve each section view
7. render supported sections directly
8. render unsupported sections through the fallback renderer

## Home page behavior

- if a published page has `is_home = true`, `/` renders that page
- otherwise `/` falls back to the existing welcome page

## Caching

The current implementation caches resolved menu payloads per theme slot.

Recommended next step if traffic grows:

- cache only published page payloads
- clear cache when a page, attached section, menu tree, or theme assignment changes
