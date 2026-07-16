# Menu Management

## Overview

The admin panel includes a dedicated `Menu Management` area under `Settings`.

This feature belongs to the existing `Frontend` domain and is designed for:

- shared menu content across themes
- theme-specific slot assignment
- one atomic save flow for the full menu tree
- future expansion to more internal linkable models

## What it manages

The system manages public-site navigation only.

It currently supports:

- `header`
- `footer`
- `mobile`

Menus are shared across themes. Themes do not duplicate menu trees. Instead, each theme chooses which published menu is assigned to each slot.

## Supported item types

Version 1 supports:

- internal page links
- custom URLs
- group parents

Internal links store generic `linkable_type` and `linkable_id`, but the UI currently exposes frontend pages only.

## Admin UX

The menu editor is intentionally single-save:

- add items from the side panel
- reorder with drag and drop
- make items children by dropping slightly to the right
- adjust the selected item from the inspector
- save the entire tree at once

This avoids the fragile per-item AJAX save pattern from the older boilerplate.

## Assignment rules

Theme assignment lives in `Frontend Themes`.

Only published menus can be assigned.

Slot depth rules:

- header: up to 2 levels
- mobile: up to 2 levels
- footer: 1 level only

If a menu exceeds the allowed depth for a slot, assignment is blocked until the tree is simplified.

## Delete protection

Menus cannot be deleted while assigned to any theme slot.

The index shows current usage badges such as:

- `Classic Horizon / Header Menu`
- `Studio Pulse / Mobile Menu`

Unassign the menu first, then delete it.
