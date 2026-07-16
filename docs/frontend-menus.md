# Frontend Menus

## Data model

### `frontend_menus`

- `name`
- `slug`
- `status`

Statuses:

- `draft`
- `published`
- `archived`

### `frontend_menu_items`

- `frontend_menu_id`
- `parent_id`
- `item_type`
- `label`
- `linkable_type`
- `linkable_id`
- `url`
- `target`
- `sort_order`
- `is_visible`

Item types:

- `internal`
- `external`
- `group`

## Slot registry

Slot definitions live in [frontend-menus.php](/d:/xampp/htdocs/boilerplate/admin-panel/config/frontend-menus.php).

That registry defines:

- label
- description
- max depth
- whether grouped navigation is allowed
- whether the slot is meant for theme rendering

## Theme assignment

Theme assignment is stored in the existing `frontend_theme_settings` table.

Keys follow this structure:

- `theme.classic.menu.header`
- `theme.classic.menu.footer`
- `theme.classic.menu.mobile`

Equivalent keys exist for every installed theme.

## Rendering

Public rendering resolves menus through the active theme in `PageRenderService`.

The payload includes `resolvedMenus`, which layouts can render using:

- shared partials in `resources/views/frontend/shared/navigation/`
- optional future theme overrides in `resources/views/frontend/themes/{theme}/navigation/`

Internal links resolve from the linked page each time the menu is rendered, so slug changes are reflected automatically.

## Extension path

This version keeps the storage generic so future internal link types can be added without redesigning the schema. Examples:

- blog posts
- products
- category pages

To extend it later:

1. keep using `linkable_type` and `linkable_id`
2. expand the admin builder UI
3. extend the render resolver for the new linkable class
